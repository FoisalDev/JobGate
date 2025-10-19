<?php
session_start();
require_once 'db_connect.php';

// 🔒 Login protection
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

$user_id = $_SESSION['user_id'];

// 👤 Fetch user's avatar
$avatar = './avatar_placeholder.jpg';
try {
  $st = $conn->prepare("SELECT profile_photo_url FROM Users WHERE user_id = ? LIMIT 1");
  $st->bind_param("s", $user_id);
  $st->execute();
  $res = $st->get_result()->fetch_assoc();
  $st->close();

  if (!empty($res['profile_photo_url'])) {
    $avatar = $res['profile_photo_url'];
  }
} catch (Throwable $e) {
  // ignore if error
  $avatar = './avatar_placeholder.jpg';
}

// 🎓 Fetch all courses
$courses = [];
try {
  $sql = "SELECT course_id, title, sector_id, platform, link_url, duration_hours, description 
          FROM Courses 
          ORDER BY sector_id ASC, title ASC";
  $result = $conn->query($sql);
  while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
  }
  $result->close();
} catch (Throwable $e) {
  $courses = [];
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>JobGate — Courses</title>
  <link rel="stylesheet" href="courses.css"/>
  <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js" defer></script>
  <style>
    body {background:#f9fafb;font-family:'Segoe UI',Roboto,sans-serif;}
    .page-title {font-size:38px;font-weight:900;color:#0f172a;margin:18px 0 24px;text-shadow:0 1px 2px rgba(0,0,0,0.06);}
    .avatar {width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid #e2e8f0;cursor:pointer;}
    .avatar:hover {border-color:#3b82f6;transition:0.3s;}
    .controls {display:flex;flex-wrap:wrap;gap:16px;align-items:center;margin:12px 0 24px;}
    .input {display:flex;align-items:center;gap:8px;background:#fff;border:1px solid #e2e8f0;border-radius:999px;padding:10px 16px;min-width:280px;box-shadow:0 3px 6px rgba(0,0,0,0.05);}
    .input input {border:0;outline:0;background:transparent;font-size:15px;width:220px;color:#0f172a;}
    .select {background:#fff;border:1px solid #dbe2ea;border-radius:12px;padding:10px 14px;font-weight:800;color:#0f172a;box-shadow:0 3px 6px rgba(0,0,0,0.05);transition:0.2s;}
    .select:hover {border-color:#93c5fd;}
    .course-section {margin-bottom:36px;}
    .cat-head {font-size:22px;font-weight:900;color:#1e3a8a;margin:12px 0 14px;border-left:5px solid #3b82f6;padding-left:8px;}
    .row {display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:18px;}
    .course-card {background:#fff;border:1px solid rgba(15,23,42,0.08);border-radius:14px;box-shadow:0 10px 24px rgba(2,6,23,0.06);overflow:hidden;display:flex;flex-direction:column;padding:20px 22px;transition:transform 0.2s ease, box-shadow 0.2s ease;}
    .course-card:hover {transform:translateY(-4px);box-shadow:0 14px 30px rgba(2,6,23,0.12);}
    .title {margin:0;font-size:20px;font-weight:900;color:#0f172a;}
    .subtitle {margin:6px 0 10px;color:#475569;font-weight:700;font-size:14px;}
    .blurb {margin:0 0 16px;color:#334155;line-height:1.5;font-size:15px;}
    .footer {display:flex;justify-content:space-between;align-items:center;}
    .note {display:inline-flex;align-items:center;gap:6px;border:1px solid #cbd5e1;padding:8px 12px;border-radius:10px;text-decoration:none;color:#0f172a;font-weight:700;transition:0.2s ease;background:#f8fafc;}
    .note:hover {background:#e0f2fe;color:#1e3a8a;border-color:#60a5fa;}
    .muted {color:#94a3b8;text-align:center;padding:20px;font-weight:700;}
  </style>
</head>
<body>
  <!-- Top Bar -->
  <header class="topbar">
    <div class="topbar-inner">
      <img src="./JobGate_logo.png" alt="JobGate" class="logo"/>
      <nav class="top-actions">
        <a href="home.php" class="tlink">Home</a>
        <a href="profile.php" class="tlink">Profile</a>
        <img src="<?php echo htmlspecialchars($avatar); ?>" alt="avatar" class="avatar" onerror="this.src='./avatar_placeholder.jpg'"/>
      </nav>
    </div>
  </header>

  <div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <button class="sbtn" onclick="window.location.href='home.php'">
        <iconify-icon icon="mdi:home"></iconify-icon>Feed
      </button>
      <button class="sbtn" onclick="window.location.href='career_tips.php'">
        <iconify-icon icon="mdi:lightbulb-on-outline"></iconify-icon>Career Tips
      </button>
      <button class="sbtn" onclick="window.location.href='job_events.php'">
        <iconify-icon icon="mdi:calendar-star"></iconify-icon>Job Events
      </button>
      <button class="sbtn active">
        <iconify-icon icon="mdi:book-open-variant"></iconify-icon>Courses
      </button>
      <button class="sbtn" onclick="window.location.href='skill_assessment.php'">
        <iconify-icon icon="mdi:account-check-outline"></iconify-icon>Skill Assessment
      </button>
      <button class="sbtn" onclick="window.location.href='jobs.php'">
        <iconify-icon icon="mdi:briefcase-outline"></iconify-icon>Jobs
      </button>
      <div class="spacer"></div>
      <button class="sbtn logout" onclick="window.location.href='login.php'">
        <iconify-icon icon="mdi:logout"></iconify-icon>Log out
      </button>
    </aside>

    <!-- Main -->
    <main class="content" style="margin-left:20px;">
      <h1 class="page-title">📘 Skill Up with Courses</h1>

      <!-- Controls -->
      <div class="controls">
        <div class="input">
          <iconify-icon icon="mdi:magnify"></iconify-icon>
          <input id="searchTitle" type="text" placeholder="Search by title or keyword..."/>
        </div>
        <select id="categoryFilter" class="select">
          <option value="all" selected>All Courses</option>
          <option value="SEC_QA">Testing</option>
          <option value="SEC_IT">Programming</option>
        </select>
      </div>

      <!-- Render Target -->
      <div id="sections"></div>
    </main>
  </div>

  <script>
    const COURSES = <?php echo json_encode($courses, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const sectionsWrap = document.getElementById("sections");

    function render() {
      const q = (document.getElementById("searchTitle").value || "").toLowerCase();
      const cat = document.getElementById("categoryFilter").value;

      const filtered = COURSES.filter(c => {
        const byCat = cat === "all" || c.sector_id === cat;
        const byQ = !q || c.title.toLowerCase().includes(q) || c.description.toLowerCase().includes(q);
        return byCat && byQ;
      });

      const groups = {};
      filtered.forEach(c => {
        const head = c.sector_id === "SEC_QA" ? "Testing"
                    : c.sector_id === "SEC_IT" ? "Programming"
                    : c.sector_id;
        (groups[head] ||= []).push(c);
      });

      sectionsWrap.innerHTML = "";
      Object.keys(groups).forEach(head => {
        const html = groups[head].map(cardTpl).join("");
        sectionsWrap.innerHTML += `
          <section class="course-section">
            <h3 class="cat-head">${head}</h3>
            <div class="row">${html}</div>
          </section>
        `;
      });

      if (!filtered.length) {
        sectionsWrap.innerHTML = `<p class="muted">No courses found for your search 🔍</p>`;
      }
    }

    function cardTpl(c) {
      return `
        <article class="course-card">
          <header>
            <h4 class="title">${c.title}</h4>
            <p class="subtitle">${c.platform} • ${c.duration_hours} hrs</p>
          </header>
          <p class="blurb">${c.description}</p>
          <div class="footer">
            <a class="note" href="${c.link_url}" target="_blank">
              View Course <iconify-icon icon="mdi:open-in-new"></iconify-icon>
            </a>
          </div>
        </article>
      `;
    }

    document.getElementById("searchTitle").addEventListener("input", render);
    document.getElementById("categoryFilter").addEventListener("change", render);
    render();
  </script>
</body>
</html>
