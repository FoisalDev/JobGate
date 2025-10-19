<?php
session_start();
require_once 'db_connect.php';

/* 🔒 Login protection */
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

/* 🔌 Logout handler */
if (isset($_GET['logout'])) {
  session_unset();
  session_destroy();
  header("Location: login.php");
  exit;
}

$user_id = $_SESSION['user_id'];

/* 👤 Avatar */
$avatar = './avatar_placeholder.jpg';
try {
  $st = $conn->prepare("SELECT profile_photo_url FROM Users WHERE user_id = ? LIMIT 1");
  $st->bind_param("s", $user_id);
  $st->execute();
  $r = $st->get_result()->fetch_assoc();
  $st->close();
  if (!empty($r['profile_photo_url'])) $avatar = $r['profile_photo_url'];
} catch (Throwable $e) {}

/* 📚 Load assessments (+ sector name) */
$assessments = [];
try {
  $sql = "SELECT a.assessment_id, a.title, a.sector_id, a.job_role,
                 a.pass_score_percent, a.duration_minutes, a.total_questions, a.allowed_attempts,
                 COALESCE(js.sector_name, a.sector_id) AS sector_name
          FROM Assessments a
          LEFT JOIN JobSectors js ON js.sector_id = a.sector_id
          ORDER BY a.sector_id ASC, a.title ASC";
  $res = $conn->query($sql);
  while ($row = $res->fetch_assoc()) $assessments[] = $row;
  $res->close();
} catch (Throwable $e) {
  $assessments = [];
}

/* 🏷️ Build category (sector) list for filter */
$sectors = [];
foreach ($assessments as $a) {
  $key = $a['sector_id'];
  if (!isset($sectors[$key])) $sectors[$key] = $a['sector_name'] ?: $key;
}

/* small helper */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>JobGate — Skill Assessment</title>
  <link rel="stylesheet" href="skill_assessment.css"/>
  <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
</head>
<body>
  <!-- 🔝 Top bar -->
  <header class="topbar">
    <div class="topbar-inner">
      <img src="./JobGate_logo.png" alt="JobGate" class="logo"/>
      <nav class="top-actions">
        <a href="home.php" class="tlink">Home</a>
        <a href="profile.php" class="tlink">Profile</a>
        <img src="<?php echo h($avatar); ?>" class="avatar" alt="avatar" onerror="this.src='./avatar_placeholder.jpg'"/>
      </nav>
    </div>
  </header>

  <div class="layout">
    <!-- 📂 Sidebar -->
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
      <button class="sbtn" onclick="window.location.href='courses.php'">
        <iconify-icon icon="mdi:book-open-variant"></iconify-icon>Courses
      </button>
      <button class="sbtn active">
        <iconify-icon icon="mdi:account-check-outline"></iconify-icon>Skill Assessment
      </button>
      <button class="sbtn" onclick="window.location.href='jobs.php'">
        <iconify-icon icon="mdi:briefcase-outline"></iconify-icon>Jobs
      </button>
      <div class="spacer"></div>
      <button class="sbtn logout" onclick="window.location.href='skill_assessment.php?logout=1'">
        <iconify-icon icon="mdi:logout"></iconify-icon>Log out
      </button>
    </aside>

    <!-- 🧠 Main -->
    <main class="content">
      <div class="page-head">
        <a class="back" href="home.php" onclick="if(history.length>1){history.back();return false;}">
          <iconify-icon icon="mdi:arrow-left"></iconify-icon>
        </a>
        <h1 class="page-title">Skill Assessment</h1>
      </div>

      <!-- 🔧 Toolbar -->
      <div class="toolbar">
        <div class="filters">
          <label class="lbl">Filter By Category :</label>
          <select id="typeFilter" class="select">
            <option value="all" selected>All</option>
            <?php foreach ($sectors as $sid => $sname): ?>
              <option value="<?php echo h($sid); ?>"><?php echo h($sname); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="input chip-in">
          <iconify-icon icon="mdi:magnify"></iconify-icon>
          <input id="searchBox" type="text" placeholder="Search by keyword or title..." />
        </div>
      </div>

      <p class="hint" id="hintTxt">Displaying all assessments</p>

      <!-- 🧾 Assessment List -->
      <section id="cardsWrap" class="cards"></section>
    </main>
  </div>

  <script>
    const ASSESSMENTS = <?php echo json_encode($assessments, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
    const wrap = document.getElementById("cardsWrap");
    const typeSel = document.getElementById("typeFilter");
    const searchBox = document.getElementById("searchBox");
    const hintTxt = document.getElementById("hintTxt");

    function paint() {
      const t = typeSel.value;
      const q = (searchBox.value || "").toLowerCase().trim();

      const filtered = ASSESSMENTS.filter(x => {
        const matchType = t === "all" || (x.sector_id || "") === t;
        const hay = ((x.title||'') + ' ' + (x.job_role||'') + ' ' + (x.sector_name||'')).toLowerCase();
        const matchQ = !q || hay.includes(q);
        return matchType && matchQ;
      });

      const label = t === "all" ? "all assessments" : `${t} assessments`;
      hintTxt.textContent = q ? `Filtering ${label} by “${q}”` : `Displaying ${label}`;

      wrap.innerHTML = filtered.map(cardTpl).join("") || `<div class="card"><p class="hint">No assessments found 🔍</p></div>`;
    }

    function cardTpl(x) {
      return `
        <article class="card">
          <header class="card-h">
            <h3 class="title">${escapeHtml(x.title || '')}</h3>
            <div class="actions"><span class="tagline">${escapeHtml(x.sector_name || '')}</span></div>
          </header>
          <a class="tagline" href="#" onclick="return false;">${escapeHtml(x.job_role || 'Assessment overview')}</a>
          <dl class="meta">
            <div><dt>Time</dt><dd>${x.duration_minutes} min</dd></div>
            <div><dt>Passing</dt><dd>${x.pass_score_percent}%</dd></div>
            <div><dt>Questions</dt><dd>${x.total_questions}</dd></div>
            <div><dt>Attempts</dt><dd>${x.allowed_attempts}</dd></div>
          </dl>
          <div class="cta-row">
            <a class="btn-primary" href="skill_exam.php?assessmentId=${encodeURIComponent(x.assessment_id)}">Attempt Exam</a>
          </div>
        </article>
      `;
    }

    function escapeHtml(s){
      return String(s||'').replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }

    typeSel.addEventListener("change", paint);
    searchBox.addEventListener("input", paint);
    paint();
  </script>
</body>
</html>
