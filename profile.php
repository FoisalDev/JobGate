<?php
session_start();
require_once 'db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!is_logged_in()) { redirect('login.php'); exit; }

$user_id   = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? 'applicant';
$full_name = $_SESSION['full_name'] ?? 'Your Name';

/* Ensure Users.profile_photo_url exists (best-effort) */
try {
  $q = $conn->prepare("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='Users' AND COLUMN_NAME='profile_photo_url'");
  $q->execute(); $row = $q->get_result()->fetch_assoc(); $q->close();
  if (empty($row['c'])) { $conn->query("ALTER TABLE Users ADD COLUMN profile_photo_url VARCHAR(255) NULL"); }
} catch (Throwable $e) { /* ignore */ }

/* Load current profile */
$profile_photo_url = null;
try {
  $stmt = $conn->prepare("SELECT full_name, profile_photo_url FROM Users WHERE user_id = ? LIMIT 1");
  $stmt->bind_param("s", $user_id);
  $stmt->execute();
  $u = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if ($u) {
    $full_name = $u['full_name'] ?: $full_name;
    $_SESSION['full_name'] = $full_name; // refresh session
    $profile_photo_url = $u['profile_photo_url'] ?: null;
  }
} catch (Throwable $e) {}

/* Handle avatar upload */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_avatar') {
  try {
    if (!empty($_FILES['avatar_file']['name']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
      $dirFs  = __DIR__ . '/uploads/profile_photos/';
      $dirWeb = 'uploads/profile_photos/';
      if (!is_dir($dirFs)) mkdir($dirFs, 0777, true);

      $info = @getimagesize($_FILES['avatar_file']['tmp_name']);
      if ($info === false) throw new Exception("Invalid image file.");
      $ext = strtolower(pathinfo($_FILES['avatar_file']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) throw new Exception("Only JPG, JPEG, PNG, GIF, WEBP allowed.");
      if ($_FILES['avatar_file']['size'] > 2*1024*1024) throw new Exception("Max 2MB allowed.");

      $fname = bin2hex(random_bytes(8)).'.'.$ext;
      if (!move_uploaded_file($_FILES['avatar_file']['tmp_name'], $dirFs.$fname)) {
        throw new Exception("Failed to save file. Check folder permission (must be 0777 or 0755).");
      }
      $url = $dirWeb.$fname;
      $up = $conn->prepare("UPDATE Users SET profile_photo_url = ? WHERE user_id = ?");
      $up->bind_param("ss", $url, $user_id);
      $up->execute(); $up->close();

      // Refresh session-side (header image)
      $profile_photo_url = $url;
      header("Location: profile.php"); // avoid resubmit
      exit;
    } else {
      throw new Exception("Please choose an image.");
    }
  } catch (Throwable $e) {
    $upload_error = $e->getMessage();
  }
}

/* Handle AJAX name save */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_name') {
  header('Content-Type: application/json');
  try {
    $newName = trim($_POST['full_name'] ?? '');
    if ($newName === '') throw new Exception("Name is required.");
    $st = $conn->prepare("UPDATE Users SET full_name = ? WHERE user_id = ?");
    $st->bind_param("ss", $newName, $user_id);
    $st->execute(); $st->close();
    $_SESSION['full_name'] = $newName;
    echo json_encode(['ok'=>true,'full_name'=>$newName,'avatar'=>$profile_photo_url]);
  } catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
  }
  exit;
}

$profilePage = ($user_type === 'recruiter') ? 'recruiter_profile.php' : 'profile.php';
$avatarSrc = $profile_photo_url ?: './avatar_placeholder.jpg';
?>
<!DOCTYPE html>
<html lang="bn">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>JobGate — Profile</title>

    <link rel="stylesheet" href="profile.css" />
    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    
    <style>
        /* Base */
        * {
          box-sizing: border-box;
        }
        html,
        body {
          margin: 0;
          padding: 0;
          background: #f8fafc;
          color: #0f172a;
          font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica,
            Arial;
          font-size: 16px;
        }
        img {
          max-width: 100%;
          display: block;
        }

        /* Topbar */
        .topbar {
          position: sticky;
          top: 0;
          z-index: 20;
          background: #fff;
          border-bottom: 1px solid #e5e7eb;
        }
        .topbar-inner {
          display: flex;
          align-items: center;
          /* FIX: Gap adjusted */
          gap: 30px; 
          height: 86px;
          width: min(1380px, 96%);
          margin: 0 auto;
          padding: 0 12px;
        }
        .logo {
          height: clamp(80px, 10vw, 120px);
          width: auto;
          object-fit: contain;
        }
        /* REMOVED .search-wrap CSS */
        
        .top-actions {
          display: flex;
          align-items: center;
          gap: 18px;
          /* FIX: Push actions to the right in the absence of search bar */
          margin-left: auto; 
        }
        .tlink {
          color: #0f172a;
          text-decoration: none;
          font-weight: 800;
        }
        .avatar {
          width: 40px;
          height: 40px;
          border-radius: 999px;
          object-fit: cover;
          box-shadow: 0 1px 6px rgba(0, 0, 0, 0.15);
        }

        /* Layout */
        .layout {
          display: grid;
          grid-template-columns: 260px 1fr;
          min-height: calc(100vh - 86px);
        }

        /* Sidebar */
        .sidebar {
          background: #0b1d3a;
          color: #e2e8f0;
          padding: 12px 14px;
          /* FIX: Making the sidebar fixed */
          position: sticky; 
          top: 86px; 
          z-index: 10; 
          display: flex;
          flex-direction: column;
          height: calc(100vh - 86px); 
        }
        
        .sbtn {
          width: 100%;
          display: flex;
          align-items: center;
          gap: 14px;
          background: transparent;
          color: #e2e8f0;
          border: 0;
          text-align: left;
          padding: 14px 12px;
          border-radius: 12px;
          cursor: pointer;
          font-weight: 800;
          margin-bottom: 6px; 
        }
        .sbtn:hover {
          background: rgba(255, 255, 255, 0.06);
        }
        .spacer {
          flex: 1;
        }
        .logout {
          color: #fca5a5;
          margin-top: auto; 
          margin-bottom: 0; 
        }
        .logout:hover {
          background: rgba(252, 165, 165, 0.12);
        }

        /* Main content */
        .content {
          padding: 24px 28px 48px;
          background: #f8fafc;
        }

        /* Profile head */
        .profile-head {
          display: grid;
          grid-template-columns: 220px 1fr 220px;
          gap: 16px;
          align-items: center;
          margin-bottom: 20px;
        }
        .avatar-lg {
          position: relative;
          width: 120px;
          height: 120px;
          border-radius: 999px;
          overflow: hidden;
        }
        .avatar-lg img {
          width: 100%;
          height: 100%;
          object-fit: cover;
        }
        .cam {
          position: absolute;
          right: 6px;
          bottom: 6px;
          width: 34px;
          height: 34px;
          border-radius: 50%;
          background: #fff;
          border: 1px solid #e5e7eb;
          display: grid;
          place-items: center;
          cursor: pointer;
        }
        .ph-mid h1 {
          margin: 0;
          font-size: 26px;
          font-weight: 900;
        }
        .facts {
          list-style: none;
          padding: 0;
          margin: 8px 0 0;
          display: grid;
          gap: 6px;
        }
        .ph-right {
          display: flex;
          flex-direction: column;
          gap: 8px;
        }

        /* Buttons */
        .btn-edit,
        .btn-primary,
        .btn-success,
        .btn-ghost,
        .btn-secondary {
          padding: 10px 14px;
          border-radius: 10px;
          font-weight: 800;
          cursor: pointer;
          border: none;
        }
        .btn-edit {
          background: #e2e8f0;
        }
        .btn-primary {
          background: #3b82f6;
          color: #fff;
        }
        .btn-success {
          background: #16a34a;
          color: #fff;
        }
        .btn-ghost {
          background: #f8fafc;
          border: 1px solid #e2e8f0;
        }
        .btn-secondary {
          background: #e5e7eb;
        }

        /* Grid / Cards */
        .grid {
          display: grid;
          grid-template-columns: 1fr 1fr;
          gap: 18px;
        }
        .card {
          background: #fff;
          border: 1px solid rgba(15, 23, 42, 0.08);
          border-radius: 12px;
          box-shadow: 0 14px 32px rgba(2, 6, 23, 0.06);
          padding: 16px;
        }

        /* Form */
        .form label {
          display: block;
          font-weight: 800;
          margin-top: 10px;
        }
        .form input,
        .form textarea {
          width: 100%;
          border: 1px solid #e2e8f0;
          border-radius: 10px;
          padding: 10px;
          margin-top: 6px;
          font-size: 14px;
        }
        .row2 {
          display: grid;
          grid-template-columns: 1fr 1fr;
          gap: 10px;
        }
        .tagbox {
          display: flex;
          gap: 8px;
          flex-wrap: wrap;
          align-items: center;
          border: 1px dashed #cbd5e1;
          padding: 8px;
          border-radius: 10px;
          margin-top: 6px;
        }
        .tags {
          display: flex;
          gap: 6px;
          flex-wrap: wrap;
        }
        .pill {
          background: #e0f2fe;
          padding: 4px 8px;
          border-radius: 999px;
          font-size: 12px;
        }
        .pill .pill-x {
          margin-left: 6px;
          background: transparent;
          border: 0;
          cursor: pointer;
        }

        .group {
          margin-top: 16px;
        }
        .list .li {
          display: flex;
          align-items: center;
          gap: 8px;
          justify-content: space-between;
          border: 1px solid #e5e7eb;
          border-radius: 8px;
          padding: 8px 10px;
          margin: 6px 0;
        }
        .li .t {
          font-weight: 800;
        }
        .li .s {
          color: #475569;
          font-size: 13px;
        }
        .li .x {
          background: #fee2e2;
          border: 0;
          border-radius: 6px;
          color: #991b1b;
          padding: 4px 8px;
          cursor: pointer;
        }

        .empty-hint::before {
          content: attr(data-hint);
          display: none;
          color: #64748b;
          font-size: 13px;
          background: #f8fafc;
          padding: 10px;
          border: 1px dashed #cbd5e1;
          border-radius: 8px;
        }
        .empty-hint.show-hint::before {
          display: block;
        }

        /* Resume */
        .resume-head {
          display: flex;
          gap: 12px;
          align-items: center;
          margin-bottom: 12px;
        }
        .resume-head img {
          width: 90px;
          height: 90px;
          border-radius: 12px;
          object-fit: cover;
        }
        .resume-head h2 {
          margin: 0;
          font-size: 22px;
          font-weight: 900;
        }
        .resume-body {
          display: grid;
          gap: 14px;
        }
        .r-block h4 {
          margin: 0 0 8px;
          font-size: 16px;
          font-weight: 900;
        }
        .r-skill-list,
        .r-list {
          margin: 0 0 0 18px;
          color: #334155;
        }
        .r-list li {
          margin: 6px 0;
        }
        .address-block {
          border: 1px dashed #cbd5e1;
          background: #f8fafc;
          padding: 10px 12px;
          border-radius: 8px;
          margin: 0 0 10px 0;
        }
        .addr-title {
          font-weight: 900;
          margin-bottom: 6px;
        }
        .addr-text {
          color: #334155;
        }

        /* Responsive */
        @media (max-width: 1100px) {
          .grid {
            grid-template-columns: 1fr;
          }
          .profile-head {
            grid-template-columns: 220px 1fr;
          }
          .ph-right {
            grid-column: 1 / -1;
            flex-direction: row;
            gap: 8px;
          }
        }
        @media (max-width: 820px) {
          .layout {
            grid-template-columns: 1fr;
          }
          .top-actions {
            display: none;
          }
        }

        /* Print — (kept for fallback) */
        @media print {
          body * {
            visibility: hidden;
          }
          #resumeCard,
          #resumeCard * {
            visibility: visible;
          }
          #resumeCard {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            box-shadow: none;
            border: 0;
          }
        }
    </style>
  </head>
  <body>
    <header class="topbar">
      <div class="topbar-inner">
        <img src="./JobGate_logo.png" alt="JobGate" class="logo" />
        
        <nav class="top-actions" aria-label="Top actions">
          <a href="home.php" class="tlink">Home</a>
          <a href="<?php echo htmlspecialchars($profilePage); ?>" class="tlink">Profile</a>
          <img src="<?php echo htmlspecialchars($avatarSrc); ?>" class="avatar" alt="User avatar" />
        </nav>
      </div>
    </header>

    <div class="layout">
      <aside class="sidebar">
        <button class="sbtn" onclick="window.location.href='home.php'">
          <iconify-icon icon="mdi:home" class="sib"></iconify-icon>Feed
        </button>
        <button class="sbtn" onclick="window.location.href='career_tips.php'">
          <iconify-icon icon="mdi:lightbulb-on-outline" class="sib"></iconify-icon>Career Tips
        </button>
        <button class="sbtn" onclick="window.location.href='job_events.php'">
          <iconify-icon icon="mdi:calendar-star" class="sib"></iconify-icon>Job Events
        </button>
        <button class="sbtn" onclick="window.location.href='courses.php'">
          <iconify-icon icon="mdi:book-open-variant" class="sib"></iconify-icon>Courses
        </button>
        <button class="sbtn" onclick="window.location.href='skill_assessment.php'">
          <iconify-icon icon="mdi:account-check-outline" class="sib"></iconify-icon>Skill Assessment
        </button>
        <button class="sbtn" onclick="window.location.href='jobs.php'">
          <iconify-icon icon="mdi:briefcase-outline" class="sib"></iconify-icon>Jobs
        </button>
        <div class="spacer"></div>
        <button class="sbtn logout" onclick="window.location.href='logout.php'">
          <iconify-icon icon="mdi:logout" class="sib"></iconify-icon>Log out
        </button>
      </aside>

      <main class="content">
        <?php if (!empty($upload_error)): ?>
          <div style="background:#fee2e2;color:#7f1d1d;border:1px solid #fecaca;padding:10px;border-radius:10px;margin-bottom:10px;">
            <?php echo htmlspecialchars($upload_error); ?>
          </div>
        <?php endif; ?>

        <section class="profile-head">
          <div class="ph-left">

          <div class="avatar-lg">
  <img id="phAvatar" src="<?= htmlspecialchars($avatarSrc) ?>" alt="avatar" />
  <button class="cam" id="btnAvatar" type="button" title="Upload photo from device">
    <iconify-icon icon="mdi:camera"></iconify-icon>
  </button>

  <form id="avatarForm" method="POST" action="profile.php" enctype="multipart/form-data">
    <input type="hidden" name="action" value="upload_avatar">
    <input type="file" name="avatar_file" id="avatarInput" accept="image/*" hidden />
  </form>
</div>

            <figcaption id="quote">“Life is a full of journey”</figcaption>
          </div>

          <div class="ph-mid">
            <h1 id="fullName"><?php echo htmlspecialchars($full_name); ?></h1>
            <ul class="facts">
              <li><iconify-icon icon="mdi:map-marker-outline"></iconify-icon> Address: <span id="locLine">—</span></li>
              <li><iconify-icon icon="mdi:briefcase-variant-outline"></iconify-icon> Works at: <span id="workLine">—</span></li>
              <li><iconify-icon icon="mdi:school-outline"></iconify-icon> Studies: <span id="eduLine">—</span></li>
              <li><iconify-icon icon="mdi:star-outline"></iconify-icon> Skills: <span id="skillsLine">—</span></li>
              <li><iconify-icon icon="mdi:file-document-outline"></iconify-icon> <span id="resumeLine">Resume — Not generated</span></li>
              <li><iconify-icon icon="mdi:check-decagram-outline"></iconify-icon> Profile complete: <strong id="complete">0%</strong></li>
            </ul>
          </div>

          <div class="ph-right">
            <button id="btnEdit" class="btn-edit">
              <iconify-icon icon="mdi:pencil"></iconify-icon> Edit
            </button>
            <button id="btnGenerate" class="btn-primary">
              <iconify-icon icon="mdi:magic-staff"></iconify-icon> Generate Resume
            </button>
            <button id="btnDownload" class="btn-success" disabled>
              <iconify-icon icon="mdi:download"></iconify-icon> Download PDF
            </button>
          </div>
        </section>

        <section class="grid">
          <form id="form" class="card form">
            <h3 class="card-title">Profile Editor</h3>

            <div class="row2">
              <label
                >Full Name
                <input
                  type="text"
                  id="fName"
                  placeholder="e.g., Md. Foisal Arefin"
                />
              </label>
              <label
                >Location / Address
                <input
                  type="text"
                  id="fLocation"
                  placeholder="e.g., Dhaka, Bangladesh"
                />
              </label>
            </div>

            <div class="row2">
              <label
                >Current Role / Org
                <input
                  type="text"
                  id="fWork"
                  placeholder="e.g., Junior Web Developer at XYZ"
                />
              </label>
              <label
                >Education (short line)
                <input
                  type="text"
                  id="fEdu"
                  placeholder="e.g., BSc in CSE, UIU"
                />
              </label>
            </div>

            <label
              >Short Quote
              <input
                type="text"
                id="fQuote"
                placeholder="e.g., Always learning, always building."
              />
            </label>

            <label
              >About Me
              <textarea
                id="fAbout"
                rows="4"
                placeholder="e.g., Passionate developer with strong problem-solving skills..."
              ></textarea>
            </label>

            <label
              >Skills (press Enter to add)
              <div class="tagbox">
                <input
                  id="fSkillInput"
                  type="text"
                  placeholder="e.g., HTML, CSS, JavaScript, React"
                />
                <div id="skillList" class="tags"></div>
              </div>
            </label>

            <div class="group">
              <h4>Education Entries</h4>
              <div
                id="eduList"
                class="list empty-hint"
                data-hint="No education added yet. Use the inputs below."
              ></div>
              <div class="row2">
                <input
                  id="eduTitle"
                  type="text"
                  placeholder="e.g., BSc in CSE"
                />
                <input
                  id="eduMeta"
                  type="text"
                  placeholder="e.g., United International University, 2021–2025"
                />
              </div>
              <button type="button" class="btn-secondary" id="addEdu">
                Add Education
              </button>
            </div>

            <div class="group">
              <h4>Experience</h4>
              <div
                id="expList"
                class="list empty-hint"
                data-hint="No experience added yet. Use the inputs below."
              ></div>
              <div class="row2">
                <input
                  id="expTitle"
                  type="text"
                  placeholder="e.g., Frontend Intern"
                />
                <input
                  id="expMeta"
                  type="text"
                  placeholder="e.g., ABC Tech, Jun 2024 – Sep 2024"
                />
              </div>
              <button type="button" class="btn-secondary" id="addExp">
                Add Experience
              </button>
            </div>

            <div class="group">
              <h4>Links</h4>
              <div
                id="linkList"
                class="list empty-hint"
                data-hint="No links yet. Add Portfolio, GitHub, LinkedIn…"
              ></div>
              <div class="row2">
                <input
                  id="linkText"
                  type="text"
                  placeholder="e.g., Portfolio"
                />
                <input
                  id="linkUrl"
                  type="url"
                  placeholder="e.g., https://your-portfolio.com"
                />
              </div>
              <button type="button" class="btn-secondary" id="addLink">
                Add Link
              </button>
            </div>

            <div class="actions">
              <button type="button" class="btn-primary" id="saveBtn">
                Save
              </button>
              <button type="button" class="btn-ghost" id="clearBtn">
                Reset
              </button>
            </div>
          </form>

          <section id="resumeCard" class="card resume resume-print">
            <div class="resume-head">
              <div class="r-left">
                <img id="rAvatar" src="./avatar_placeholder.jpg" alt="avatar" />
              </div>
              <div class="r-mid">
                <h2 id="rName">Your Name</h2>
                <p id="rTitle">—</p>
              </div>
            </div>

            <div class="resume-body">
              <div class="r-block">
                <h4>About Me</h4>
                <p id="rAbout">—</p>
              </div>

              <div class="r-block">
                <h4>Skills</h4>
                <ul id="rSkills" class="r-skill-list"></ul>
              </div>

              <div class="r-block">
                <h4>Education</h4>
                <ul id="rEdu" class="r-list"></ul>
              </div>

              <div class="r-block">
                <h4>Experience</h4>

                <div class="address-block">
                  <div class="addr-title">Address</div>
                  <div class="addr-text" id="rAddress">—</div>
                </div>

                <ul id="rExp" class="r-list"></ul>
              </div>

              <div class="r-block" id="rLinksBlock" style="display: none">
                <h4>Links</h4>
                <ul id="rLinks" class="r-list"></ul>
              </div>
            </div>
          </section>
        </section>
      </main>
    </div>

    
       <script>
        
      /* ====== Avatar pick: open file dialog & auto-submit ====== */
      const btnAvatar  = document.getElementById('btnAvatar');
      const avatarIn   = document.getElementById('avatarInput');
      const avatarForm = document.getElementById('avatarForm');
      const headerAvatar = document.querySelector('.top-actions .avatar');
      const bigAvatar    = document.getElementById('phAvatar');

      // Click button opens file dialog
      btnAvatar?.addEventListener('click', () => avatarIn?.click());
      
      // File selected => auto-submit PHP form (this uses the server upload logic)
      avatarIn?.addEventListener('change', () => {
        if (avatarIn.files && avatarIn.files[0]) {
          // This submits the form, which triggers the PHP upload logic and redirects
          avatarForm.submit(); 
        }
      });

      /* ====== Inject DB-backed defaults into your existing state ======
         যদি তোমার আগের স্ক্রিপ্টে state = {...} থাকে,
         সেটার ডিফল্টে নিচের দুটো ভ্যালু বসাও— */
      window.__JOBGATE_DB_DEFAULTS__ = {
        full_name: <?php echo json_encode($full_name); ?>,
        avatar:    <?php echo json_encode($avatarSrc); ?>
      };

      /* ====== Minimal hook: যখন Save চাপবে, DB-তেও নাম আপডেট করবো ======
         তোমার saveBtn onclick যেখানে আছে, তার সাথেই এটা কাজ করবে। */
      (function hookSaveToDB(){
        const saveBtn = document.getElementById('saveBtn');
        const fName   = document.getElementById('fName');
        const fullNameEl = document.getElementById('fullName');
        if (!saveBtn || !fName) return;

        saveBtn.addEventListener('click', async () => {
          const newName = (fName.value || '').trim();
          if (!newName) return;

          try {
            const resp = await fetch('profile.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: new URLSearchParams({ action: 'save_name', full_name: newName })
            });
            const data = await resp.json();
            if (data.ok) {
              fullNameEl.textContent = data.full_name;
              // Header title/anything else if needed
            }
          } catch(e) { /* ignore */ }
        });
      })();

      /* ====== First render: override initial UI with DB values ====== */
      (function applyDBDefaults(){
        try {
          const d = window.__JOBGATE_DB_DEFAULTS__;
          if (!d) return;
          // Top header avatar already set by PHP; also set large avatar/name immediately
          if (d.avatar && bigAvatar) bigAvatar.src = d.avatar;
          if (d.avatar && headerAvatar) headerAvatar.src = d.avatar;

          // আপনার আগের স্ক্রিপ্টে state থাকলে, প্রথমবার সেটার name/avatar override করে দিন:
          if (window.state) {
            if (d.full_name) window.state.name = d.full_name;
            if (d.avatar)    window.state.avatar = d.avatar;
            if (window.renderAll) window.renderAll();
          } else {
            // no global state — কমপক্ষে শিরোনামটা আপডেট থাকুক
            const fullNameEl = document.getElementById('fullName');
            if (fullNameEl && d.full_name) fullNameEl.textContent = d.full_name;
          }
        } catch(e){}
      })();
      // ====== State & storage ======
      const KEY = "jobgate_profile_v9";
      const load = () => JSON.parse(localStorage.getItem(KEY) || "null");
      const save = (d) => localStorage.setItem(KEY, JSON.stringify(d));
      const clear = () => localStorage.removeItem(KEY);

      let state = load() || {
        name: "Your Name",
        location: "",
        work: "",
        eduLine: "",
        quote: "",
        about: "",
        avatar: "./avatar_placeholder.jpg",
        skills: [],
        education: [],
        experience: [],
        links: [],
        generated: false,
      };

      // Apply DB values to Local Storage state if they exist and are newer/valid
      (function syncDBToState(){
          const d = window.__JOBGATE_DB_DEFAULTS__;
          if (d.full_name && state.name === "Your Name") {
              state.name = d.full_name;
          }
          if (d.avatar && d.avatar !== "./avatar_placeholder.jpg") {
              // Only overwrite local storage if the DB has a real URL (not the placeholder)
              state.avatar = d.avatar;
          }
      })();


      // ====== DOM refs ======
      const fullName = document.getElementById("fullName");
      const quote = document.getElementById("quote");
      const phAvatar = document.getElementById("phAvatar");
      const locLine = document.getElementById("locLine");
      const workLine = document.getElementById("workLine");
      const eduLine = document.getElementById("eduLine");
      const skillsLine = document.getElementById("skillsLine");
      const resumeLine = document.getElementById("resumeLine");
      const complete = document.getElementById("complete");

      const fName = document.getElementById("fName");
      const fLocation = document.getElementById("fLocation");
      const fWork = document.getElementById("fWork");
      const fEdu = document.getElementById("fEdu");
      const fQuote = document.getElementById("fQuote");
      const fAbout = document.getElementById("fAbout");
      const fSkillInput = document.getElementById("fSkillInput");
      const skillList = document.getElementById("skillList");
      const eduList = document.getElementById("eduList");
      const expList = document.getElementById("expList");
      const linkList = document.getElementById("linkList");

      const rAvatar = document.getElementById("rAvatar");
      const rName = document.getElementById("rName");
      const rTitle = document.getElementById("rTitle");
      const rAbout = document.getElementById("rAbout");
      const rSkills = document.getElementById("rSkills");
      const rEdu = document.getElementById("rEdu");
      const rExp = document.getElementById("rExp");
      const rAddress = document.getElementById("rAddress");
      const rLinks = document.getElementById("rLinks");
      const rLinksBlock = document.getElementById("rLinksBlock");

      const btnEdit = document.getElementById("btnEdit");
      const btnGenerate = document.getElementById("btnGenerate");
      const btnDownload = document.getElementById("btnDownload");
      const btnSave = document.getElementById("saveBtn");
      const btnReset = document.getElementById("clearBtn");

      const avatarInput = document.getElementById("avatarInput");
      // const btnAvatar = document.getElementById("btnAvatar"); // Already defined above

      // ====== Helpers ======
      const pct = (s) => {
        let n = 0;
        if (s.name && s.name !== "Your Name") n += 20;
        if (s.about) n += 20;
        if (s.skills.length) n += 20;
        if (s.education.length || s.experience.length) n += 20;
        if (s.location) n += 20;
        return n;
      };

      const hintIfEmpty = (el, arr) => {
        if (!arr.length) el.classList.add("show-hint");
        else el.classList.remove("show-hint");
      };

      function pill(text, i) {
        const span = document.createElement("span");
        span.className = "pill";
        span.innerHTML = `${text} <button type="button" class="pill-x" aria-label="Remove">×</button>`;
        span.querySelector("button").onclick = () => {
          state.skills.splice(i, 1);
          syncFormDataToState(); // ADDED: Sync before re-render
          save(state);
          renderAll();
        };
        return span;
      }

      function listItem(text, sub, idx, onRemove) {
        const div = document.createElement("div");
        div.className = "li";
        div.innerHTML = `<span class="t">${text}</span><span class="s">${
          sub || ""
        }</span>`;
        const x = document.createElement("button");
        x.type = "button";
        x.className = "x";
        x.textContent = "×";
        x.onclick = () => {
          onRemove(idx);
          syncFormDataToState(); // ADDED: Sync before re-render
          save(state);
          renderAll();
        };
        div.appendChild(x);
        return div;
      }

      // ** NEW FUNCTION: Syncs form fields to state **
      const syncFormDataToState = () => {
        state.name = fName.value.trim() || "Your Name";
        state.location = fLocation.value.trim();
        state.work = fWork.value.trim();
        state.eduLine = fEdu.value.trim();
        state.quote = fQuote.value.trim();
        state.about = fAbout.value.trim();
      };

      // ====== Renders ======
      function renderProfile() {
        fullName.textContent = state.name;
        quote.textContent = state.quote || "“Life is a full of journey”";
        phAvatar.src = state.avatar;

        locLine.textContent = state.location || "—";
        workLine.textContent = state.work || "—";
        eduLine.textContent = state.eduLine || "—";
        skillsLine.textContent = state.skills.length
          ? state.skills.join(", ")
          : "—";
        resumeLine.textContent = state.generated
          ? "Resume — Ready"
          : "Resume — Not generated";
        complete.textContent = pct(state) + "%";

        // enable download if at least 40% completion
        btnDownload.disabled = pct(state) < 40;
      }

      function renderEditor() {
        // These lines are why the data was clearing: they overwrite
        // the current input with the value from state *if you hadn't saved yet*
        fName.value = state.name;
        fLocation.value = state.location;
        fWork.value = state.work;
        fEdu.value = state.eduLine;
        fQuote.value = state.quote;
        fAbout.value = state.about;

        skillList.innerHTML = "";
        state.skills.forEach((s, i) => skillList.appendChild(pill(s, i)));
        hintIfEmpty(skillList, state.skills);

        eduList.innerHTML = "";
        state.education.forEach((e, i) => {
          eduList.appendChild(
            listItem(e.title, e.meta, i, (idx) => {
              state.education.splice(idx, 1);
              // Removal calls syncFormDataToState within listItem's onclick
            })
          );
        });
        hintIfEmpty(eduList, state.education);

        expList.innerHTML = "";
        state.experience.forEach((e, i) => {
          expList.appendChild(
            listItem(e.title, e.meta, i, (idx) => {
              state.experience.splice(idx, 1);
              // Removal calls syncFormDataToState within listItem's onclick
            })
          );
        });
        hintIfEmpty(expList, state.experience);

        linkList.innerHTML = "";
        state.links.forEach((l, i) => {
          const div = document.createElement("div");
          div.className = "li";
          div.innerHTML = `<a class="t" href="${l.url}" target="_blank" rel="noopener">${l.text}</a><span class="s">${l.url}</span>`;
          const x = document.createElement("button");
          x.type = "button";
          x.className = "x";
          x.textContent = "×";
          x.onclick = () => {
            state.links.splice(i, 1);
            syncFormDataToState(); // ADDED: Sync before re-render
            save(state);
            renderAll();
          };
          div.appendChild(x);
          linkList.appendChild(div);
        });
        hintIfEmpty(linkList, state.links);
      }

      function renderResume() {
        rAvatar.src = state.avatar;
        rName.textContent = state.name;
        rTitle.textContent = state.work || "—";
        rAbout.textContent = state.about || "—";

        rSkills.innerHTML =
          state.skills.map((s) => `<li>${s}</li>`).join("") || "<li>—</li>";
        rEdu.innerHTML =
          state.education
            .map(
              (e) =>
                `<li><strong>${e.title}</strong> <span>${e.meta}</span></li>`
            )
            .join("") || "<li>—</li>";
        rExp.innerHTML =
          state.experience
            .map(
              (e) =>
                `<li><strong>${e.title}</strong> <span>${e.meta}</span></li>`
            )
            .join("") || "<li>—</li>";

        // Address block under "Experience" heading (as requested)
        rAddress.textContent = state.location || "—";

        // Links block shows only if we have any
        if (state.links.length) {
          rLinksBlock.style.display = "";
          rLinks.innerHTML = state.links
            .map(
              (l) => `<li><a href="${l.url}" target="_blank">${l.text}</a></li>`
            )
            .join("");
        } else {
          rLinksBlock.style.display = "none";
          rLinks.innerHTML = "";
        }
      }

      function renderAll() {
        renderProfile();
        renderEditor();
        renderResume();
      }

      // ====== Events ======

      // ** UPDATED: saveBtn click now just calls the shared sync function and saves **
      document.getElementById("saveBtn").onclick = () => {
        syncFormDataToState();
        state.generated = false;
        save(state);
        renderAll();
      };

      document.getElementById("clearBtn").onclick = () => {
        if (!confirm("Reset everything? This clears your saved profile."))
          return;
        clear();
        state = {
          name: "Your Name",
          location: "",
          work: "",
          eduLine: "",
          quote: "",
          about: "",
          avatar: "./avatar_placeholder.jpg",
          skills: [],
          education: [],
          experience: [],
          links: [],
          generated: false,
        };
        // Re-apply DB defaults to the reset state
        (function reApplyDBDefaults(){
            const d = window.__JOBGATE_DB_DEFAULTS__;
            if (d.full_name) state.name = d.full_name;
            if (d.avatar)    state.avatar = d.avatar;
        })();
        
        renderAll();
      };

      document.getElementById("btnEdit").onclick = () => {
        document
          .querySelector(".form")
          .scrollIntoView({ behavior: "smooth", block: "start" });
        fName.focus();
      };

      document.getElementById("btnGenerate").onclick = () => {
        // ** ADDED: Sync form data before checking completeness and generating **
        syncFormDataToState();
        if (pct(state) < 50) {
          alert(
            "Please fill more fields (Name, Address, About, some Skills, and at least one Education/Experience)."
          );
          return;
        }
        state.generated = true;
        save(state);
        renderAll();
        document
          .getElementById("resumeCard")
          .scrollIntoView({ behavior: "smooth" });
      };

      // ** UPDATED: Add rows now calls syncFormDataToState() before saving **
      document.getElementById("addEdu").onclick = () => {
        const t = document.getElementById("eduTitle").value.trim();
        const m = document.getElementById("eduMeta").value.trim();
        if (!t) return;
        state.education.push({ title: t, meta: m });
        document.getElementById("eduTitle").value = "";
        document.getElementById("eduMeta").value = "";
        state.generated = false;
        syncFormDataToState(); // ADDED: Sync data before saving and rendering
        save(state);
        renderAll();
      };
      document.getElementById("addExp").onclick = () => {
        const t = document.getElementById("expTitle").value.trim();
        const m = document.getElementById("expMeta").value.trim();
        if (!t) return;
        state.experience.push({ title: t, meta: m });
        document.getElementById("expTitle").value = "";
        document.getElementById("expMeta").value = "";
        state.generated = false;
        syncFormDataToState(); // ADDED: Sync data before saving and rendering
        save(state);
        renderAll();
      };
      document.getElementById("addLink").onclick = () => {
        const text = document.getElementById("linkText").value.trim();
        const url = document.getElementById("linkUrl").value.trim();
        if (!text || !url) return;
        const safeUrl = /^https?:\/\//i.test(url) ? url : "https://" + url;
        state.links.push({ text, url: safeUrl });
        document.getElementById("linkText").value = "";
        document.getElementById("linkUrl").value = "";
        state.generated = false;
        syncFormDataToState(); // ADDED: Sync data before saving and rendering
        save(state);
        renderAll();
      };

      // ** UPDATED: Skills add on Enter now calls syncFormDataToState() before saving **
      fSkillInput.addEventListener("keydown", (e) => {
        if (e.key === "Enter") {
          e.preventDefault();
          const v = fSkillInput.value.trim();
          if (!v) return;
          state.skills.push(v);
          fSkillInput.value = "";
          state.generated = false;
          syncFormDataToState(); // ADDED: Sync data before saving and rendering
          save(state);
          renderAll();
        }
      });

      // NO local file -> Base64 conversion here, relying solely on PHP server upload.

      // ====== PDF Download (jsPDF) ======
      document.getElementById("btnDownload").onclick = () => {
        // ** ADDED: Sync form data before checking completeness and downloading **
        syncFormDataToState();
        if (pct(state) < 40) {
          alert("Please complete more fields before downloading.");
          return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ unit: "pt", format: "a4" });
        const margin = 56; // 0.78in
        const lineGap = 18;
        let y = margin;

        // Header
        doc.setFont("helvetica", "bold");
        doc.setFontSize(20);
        doc.text(state.name || "Your Name", margin, y);
        y += 24;

        doc.setFont("helvetica", "normal");
        doc.setFontSize(12);
        doc.text(state.work || "—", margin, y);
        y += 10;

        // Divider
        y += 8;
        doc.setDrawColor(220);
        doc.line(margin, y, 595 - margin, y);
        y += 20;

        // About
        doc.setFont("helvetica", "bold");
        doc.setFontSize(13);
        doc.text("About Me", margin, y);
        y += 16;
        doc.setFont("helvetica", "normal");
        doc.setFontSize(11);
        const aboutTxt = doc.splitTextToSize(
          state.about || "—",
          595 - margin * 2
        );
        doc.text(aboutTxt, margin, y);
        y += aboutTxt.length * 14 + 10;

        // Skills
        doc.setFont("helvetica", "bold");
        doc.setFontSize(13);
        doc.text("Skills", margin, y);
        y += 16;
        doc.setFont("helvetica", "normal");
        doc.setFontSize(11);
        const skillsTxt = state.skills.length ? state.skills.join(", ") : "—";
        const skillsWrapped = doc.splitTextToSize(skillsTxt, 595 - margin * 2);
        doc.text(skillsWrapped, margin, y);
        y += skillsWrapped.length * 14 + 10;

        // Education
        doc.setFont("helvetica", "bold");
        doc.setFontSize(13);
        doc.text("Education", margin, y);
        y += 16;
        doc.setFont("helvetica", "normal");
        doc.setFontSize(11);
        if (state.education.length) {
          state.education.forEach((ed) => {
            doc.text(`• ${ed.title} — ${ed.meta || ""}`, margin, y);
            y += lineGap;
          });
        } else {
          doc.text("—", margin, y);
          y += lineGap;
        }

        // Experience
        y += 4;
        doc.setFont("helvetica", "bold");
        doc.setFontSize(13);
        doc.text("Experience", margin, y);
        y += 16;

        // Address under "Experience"
        doc.setFont("helvetica", "bold");
        doc.setFontSize(12);
        doc.text("Address", margin, y);
        y += 14;
        doc.setFont("helvetica", "normal");
        doc.setFontSize(11);
        const addr = state.location || "—";
        const addrLines = doc.splitTextToSize(addr, 595 - margin * 2);
        doc.text(addrLines, margin, y);
        y += addrLines.length * 14 + 6;

        doc.setFont("helvetica", "normal");
        doc.setFontSize(11);
        if (state.experience.length) {
          state.experience.forEach((ex) => {
            const line = `• ${ex.title} — ${ex.meta || ""}`;
            const wrap = doc.splitTextToSize(line, 595 - margin * 2);
            doc.text(wrap, margin, y);
            y += wrap.length * 14 + 4;
          });
        } else {
          doc.text("—", margin, y);
          y += lineGap;
        }

        // Links (if any)
        if (state.links.length) {
          y += 8;
          doc.setFont("helvetica", "bold");
          doc.setFontSize(13);
          doc.text("Links", margin, y);
          y += 16;
          doc.setFont("helvetica", "normal");
          doc.setFontSize(11);
          state.links.forEach((l) => {
            const txt = `${l.text}: ${l.url}`;
            const wrap = doc.splitTextToSize(txt, 595 - margin * 2);
            doc.text(wrap, margin, y);
            y += wrap.length * 14 + 2;
          });
        }

        // Optional avatar (if dataURL and not huge)
        try {
          if (state.avatar && state.avatar.startsWith("http")) {
            // NOTE: jsPDF addImage does not support external URLs by default.
            // If you need the avatar in the PDF, it must be pre-loaded as Base64 or you must use a library extension.
            // For now, only Base64 will work reliably in jsPDF, but we removed the Base64 conversion
            // to fix the main issue. Leaving this block commented for compatibility.
            
            /*
            // If you decide to pre-load/convert the image URL to Base64 on the fly (complex):
            const imgW = 64, imgH = 64;
            // doc.addImage(state.avatar, "PNG", 595 - margin - imgW, margin - 8, imgW, imgH); 
            */
          }
        } catch (e) {
          /* ignore image errors */
        }

        const filename = `${(state.name || "resume").replace(
          /[^a-z0-9]+/gi,
          "_"
        )}_Resume.pdf`;
        doc.save(filename);
      };

      // Mount
      renderAll();
    </script>
  </body>
</html>