<?php
// jobs.php — feed-style jobs list (no salary slider)
session_start();
require_once 'db_connect.php';

if (!is_logged_in()) { redirect('login.php'); }

$user_id    = $_SESSION['user_id'] ?? '';
$user_type  = $_SESSION['user_type'] ?? 'applicant';
$profilePage = ($user_type === 'recruiter') ? 'recruiter_profile.php' : 'profile.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function excerpt($text, $len=260){
  $text = strip_tags((string)$text);
  if (mb_strlen($text) <= $len) return $text;
  return mb_substr($text, 0, $len-1).'…';
}
function slugify($t){
  $t = strtolower(trim((string)$t));
  $t = preg_replace('/[^a-z0-9]+/','-',$t);
  return trim($t,'-');
}

// avatar
$avatar = './avatar_placeholder.jpg';
try {
  if ($user_id) {
    $st = $conn->prepare("SELECT profile_photo_url FROM Users WHERE user_id=? LIMIT 1");
    $st->bind_param("s", $user_id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!empty($row['profile_photo_url'])) $avatar = $row['profile_photo_url'];
  }
} catch (Throwable $e) {}

$jobs = [];
try {
  // only jobs that are still open by deadline (or no deadline)
  $sql = "SELECT j.job_id, j.title, j.description, j.type, j.location,
                 j.posted_at, j.application_deadline, j.job_logo_url,
                 u.full_name AS company_name
          FROM Jobs j
          JOIN Recruiters r ON j.recruiter_id = r.recruiter_id
          JOIN Users u      ON r.user_id      = u.user_id
          WHERE (j.application_deadline IS NULL
                 OR j.application_deadline=''
                 OR j.application_deadline='0000-00-00'
                 OR j.application_deadline >= CURDATE())
          ORDER BY j.posted_at DESC
          LIMIT 200";
  $res = $conn->query($sql);
  while ($r = $res->fetch_assoc()) {
    $dl = trim((string)($r['application_deadline'] ?? ''));
    $deadline = (!$dl || $dl==='0000-00-00') ? 'Open until filled' : date('M d, Y', strtotime($dl));
    $jobs[] = [
      'id'       => $r['job_id'],
      'title'    => (string)($r['title'] ?? 'Untitled'),
      'company'  => (string)($r['company_name'] ?? 'Company'),
      'logo'     => (string)($r['job_logo_url'] ?: './avatar_placeholder.jpg'),
      'type'     => (string)($r['type'] ?? ''),
      'role'     => slugify(($r['type'] ?: $r['title']) ?? ''),
      'location' => (string)($r['location'] ?? ''),
      'deadline' => $deadline,
      'posted'   => !empty($r['posted_at']) ? date('M d, Y', strtotime($r['posted_at'])) : '',
      'desc'     => excerpt($r['description'] ?? '', 260),
    ];
  }
} catch (Throwable $e) {
  $jobs = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>JobGate — Jobs</title>
  <link rel="stylesheet" href="jobs.css"/>
  <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js" defer></script>
</head>
<body class="jobs-page">
  <!-- topbar: same as feed (no search input) -->
  <header class="topbar">
    <div class="topbar-inner">
      <img src="./JobGate_logo.png" alt="JobGate" class="logo"/>
      <nav class="top-actions" aria-label="Top actions">
        <a href="home.php" class="tlink">Home</a>
        <a href="<?php echo h($profilePage); ?>" class="tlink">Profile</a>
        <img src="<?php echo h($avatar); ?>" class="avatar" alt="avatar" onerror="this.src='./avatar_placeholder.jpg'"/>
      </nav>
    </div>
  </header>

  <div class="layout">
    <!-- sidebar: same as other pages -->
    <aside class="sidebar">
      <button class="sbtn" onclick="location.href='home.php'"><iconify-icon icon="mdi:home"></iconify-icon>Feed</button>
      <button class="sbtn" onclick="location.href='career_tips.php'"><iconify-icon icon="mdi:lightbulb-on-outline"></iconify-icon>Career Tips</button>
      <button class="sbtn" onclick="location.href='job_events.php'"><iconify-icon icon="mdi:calendar-star"></iconify-icon>Job Events</button>
      <button class="sbtn" onclick="location.href='courses.php'"><iconify-icon icon="mdi:book-open-variant"></iconify-icon>Courses</button>
      <button class="sbtn" onclick="location.href='skill_assessment.php'"><iconify-icon icon="mdi:account-check-outline"></iconify-icon>Skill Assessment</button>
      <button class="sbtn active"><iconify-icon icon="mdi:briefcase-outline"></iconify-icon>Jobs</button>
      <div class="spacer"></div>
      <a class="sbtn logout" href="logout.php" style="text-decoration:none;"><iconify-icon icon="mdi:logout"></iconify-icon>Log out</a>
    </aside>

    <!-- main -->
    <main class="content">
      <h1 class="page-title">Jobs</h1>

      <!-- simple filters (no salary range) -->
      <section class="controls">
        <div class="filters">
          <label class="lbl">Filter by Job Type:</label>
          <select id="roleFilter" class="select">
            <option value="all" selected>All</option>
          </select>
        </div>

        <div class="input chip-in">
          <iconify-icon icon="mdi:magnify"></iconify-icon>
          <input id="companySearch" type="text" placeholder="Search Company"/>
        </div>
      </section>

      <section id="jobsWrap" class="jobs-list"></section>
    </main>
  </div>

  <script>
    // safe data from PHP
    const JOBS = <?php echo json_encode($jobs, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;

    const wrap   = document.getElementById('jobsWrap');
    const roleEl = document.getElementById('roleFilter');
    const qEl    = document.getElementById('companySearch');

    const esc = s => (s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));
    const line = (icon, txt) => `<li><iconify-icon icon="${icon}"></iconify-icon>${esc(txt||'')}</li>`;

    // fill role options
    (function(){
      const set = new Set(JOBS.map(j=>j.role).filter(Boolean));
      [...set].sort().forEach(r=>{
        const o=document.createElement('option');
        o.value=r;
        o.textContent=r.replace(/-/g,' ').replace(/\b\w/g,c=>c.toUpperCase());
        roleEl.appendChild(o);
      });
    })();

    function card(j){
      return `
      <article class="job-card">
        <div class="poster">
          <img src="${esc(j.logo)}" alt="${esc(j.company)}" onerror="this.src='./avatar_placeholder.jpg'">
        </div>

        <div class="body">
          <h3 class="title">${esc(j.title)} — ${esc(j.company)}</h3>
          <ul class="meta">
            ${j.location ? line('mdi:map-marker-outline', j.location) : ''}
            ${j.type ? line('mdi:briefcase-outline', j.type) : ''}
            ${j.posted ? line('mdi:calendar', 'Posted: '+j.posted) : ''}
            ${line('mdi:calendar-clock', j.deadline)}
          </ul>
          <p class="desc">${esc(j.desc)}</p>
          <div class="cta">
            <a class="btn-primary" href="#" onclick="alert('Apply flow here');return false;">Apply</a>
            <a class="btn-ghost" href="job_details.php?jobId=${encodeURIComponent(j.id)}" target="_blank" rel="noopener">
              <iconify-icon icon="mdi:eye-outline"></iconify-icon> See Details
            </a>
          </div>
        </div>
      </article>`;
    }

    function render(){
      const role = roleEl.value;
      const q    = (qEl.value||'').toLowerCase().trim();
      const list = JOBS.filter(j=>{
        const byRole = role==='all' || j.role===role;
        const byCompany = !q || (j.company||'').toLowerCase().includes(q);
        return byRole && byCompany;
      });
      wrap.innerHTML = list.length ? list.map(card).join('') : `<p class="empty">No jobs found.</p>`;
    }

    roleEl.addEventListener('change', render);
    qEl.addEventListener('input', render);
    render();
  </script>
</body>
</html>
