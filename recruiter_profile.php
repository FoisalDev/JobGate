<?php
// recruiter_profile.php — STRICT to jobgate.sql + fixed navbar overlap
require_once 'db_connect.php';
session_start();

/* DEV (turn off in prod) */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* Helpers */
if (!function_exists('sanitize_input')) {
  function sanitize_input($v){ return trim(filter_var($v, FILTER_SANITIZE_FULL_SPECIAL_CHARS)); }
}
if (!function_exists('guid')) {
  function guid(){
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
  }
}
if (!function_exists('GUID')) { function GUID(){ return guid(); } }

/* Guards */
if (!is_logged_in()) { redirect('login.php'); exit; }
if ($_SESSION['user_type'] !== 'recruiter') { redirect('home.php'); exit; }

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Recruiter';

$message = '';
$message_type = '';

/* Map Users.user_id -> Recruiters.recruiter_id */
$recruiter_id = null;
$existing_logo = null;
try {
  $stmt = $conn->prepare("SELECT recruiter_id, company_logo_url FROM Recruiters WHERE user_id = ?");
  $stmt->bind_param("s", $user_id);
  $stmt->execute();
  $recRow = $stmt->get_result()->fetch_assoc();
  if ($recRow) {
    $recruiter_id = $recRow['recruiter_id'];
    $existing_logo = $recRow['company_logo_url'];
  }
  $stmt->close();
} catch (Throwable $e) {
  $message = "DB error mapping recruiter: ".$e->getMessage();
  $message_type = 'error';
}

/* POST: Create job (STRICT to jobgate.sql) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ( $_POST['action'] ?? '' ) === 'post_job') {
  try {
    if (!$recruiter_id) throw new Exception("No recruiter profile found for this user.");

    // Optional: update recruiter company logo (Jobs table has no logo column)
    if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
      $dir_fs  = __DIR__ . '/uploads/job_logos/';
      $dir_web = 'uploads/job_logos/';
      if (!is_dir($dir_fs)) mkdir($dir_fs, 0777, true);

      $info = @getimagesize($_FILES['logo']['tmp_name']);
      if ($info === false) throw new Exception("Invalid image file.");

      $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) throw new Exception("Only JPG, JPEG, PNG, GIF, WEBP allowed.");
      if ($_FILES['logo']['size'] > 2*1024*1024) throw new Exception("File too large. Max 2MB.");

      $fname = GUID().'.'.$ext;
      if (!move_uploaded_file($_FILES['logo']['tmp_name'], $dir_fs.$fname)) {
        throw new Exception("Failed to move uploaded file. Check folder permissions.");
      }
      $logo_url = $dir_web.$fname;

      // Save to Recruiters.company_logo_url
      $stUp = $conn->prepare("UPDATE Recruiters SET company_logo_url = ? WHERE recruiter_id = ?");
      $stUp->bind_param("ss", $logo_url, $recruiter_id);
      $stUp->execute(); $stUp->close();
      $existing_logo = $logo_url;
    }

    // Inputs for Jobs (as per jobgate.sql)
    $job_id     = GUID();
    $title      = sanitize_input($_POST['title'] ?? '');
    $job_role   = sanitize_input($_POST['job_role'] ?? '');
    $sector_id  = sanitize_input($_POST['sector_id'] ?? '');
    $location   = sanitize_input($_POST['location'] ?? '');
    $type       = sanitize_input($_POST['type'] ?? 'Full-time'); // ENUM('Full-time','Part-time','Contract','Internship')
    $salary_min = (int)($_POST['salary_min'] ?? 0);
    $salary_max = (int)($_POST['salary_max'] ?? 0);
    $description= sanitize_input($_POST['description'] ?? '');
    $requirements = sanitize_input($_POST['requirements'] ?? '');
    $application_deadline = sanitize_input($_POST['application_deadline'] ?? ($_POST['deadline'] ?? ''));

    if ($title==='' || $sector_id==='' || $description==='' || $application_deadline==='') {
      throw new Exception("Please fill required fields (Title, Sector, Description, Application Deadline).");
    }
    if (!in_array($type, ['Full-time','Part-time','Contract','Internship'], true)) {
      throw new Exception("Invalid employment type.");
    }
    if ($salary_max && $salary_min && $salary_max < $salary_min) {
      throw new Exception("Salary max cannot be less than salary min.");
    }

    $conn->begin_transaction();

    // INSERT into Jobs (exact columns)
    $sql = "INSERT INTO Jobs
            (job_id, recruiter_id, sector_id, title, job_role, location, type, salary_min, salary_max, description, requirements, application_deadline)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
      "ssssssssisss",
      $job_id, $recruiter_id, $sector_id, $title, $job_role, $location, $type, $salary_min, $salary_max, $description, $requirements, $application_deadline
    );
    $stmt->execute();
    $stmt->close();

    // Optional assessment mapping (JobAssessmentRequirements has ONLY (job_id, assessment_id))
    $assessment_id = sanitize_input($_POST['assessment_id'] ?? '');
    if ($assessment_id !== '') {
      $st2 = $conn->prepare("INSERT INTO JobAssessmentRequirements (job_id, assessment_id) VALUES (?, ?)");
      $st2->bind_param("ss", $job_id, $assessment_id);
      $st2->execute(); $st2->close();
    }

    $conn->commit();
    $message = "Job posted successfully!";
    $message_type = 'success';
    $_POST = [];

  } catch (Throwable $e) {
    if ($conn && $conn->errno) $conn->rollback();
    $message = "Error: ".$e->getMessage();
    $message_type = 'error';
  }
}

/* Dropdowns */
$sectors = [];
try {
  $q = $conn->query("SELECT sector_id, sector_name AS name FROM JobSectors ORDER BY name");
  while ($r = $q->fetch_assoc()) $sectors[] = $r;
  $q->close();
} catch (Throwable $e) {}

$assessments = [];
try {
  $q = $conn->query("SELECT assessment_id, title FROM Assessments ORDER BY title");
  while ($r = $q->fetch_assoc()) $assessments[] = $r;
  $q->close();
} catch (Throwable $e) {}

/* History — posted_at & application_deadline */
$job_history = [];
try {
  if ($recruiter_id) {
    $sqlH = "SELECT job_id, title, job_role, location, type, salary_min, salary_max, application_deadline, posted_at
             FROM Jobs
             WHERE recruiter_id = ?
             ORDER BY posted_at DESC";
    $st = $conn->prepare($sqlH);
    $st->bind_param("s", $recruiter_id);
    $st->execute();
    $r = $st->get_result();
    while ($row = $r->fetch_assoc()) $job_history[] = $row;
    $st->close();
  }
} catch (Throwable $e) {
  $message = "History load error: ".$e->getMessage();
  $message_type = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>JobGate — Recruiter Profile</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="recruiter_profile.css" />
  <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
  <style>
    /* === FIXED, CONSISTENT NAVBAR === */
    .topbar{
      position: sticky; top:0; z-index: 2000;
      background:#ffffff; border-bottom:1px solid #e5e7eb;
    }
    .topbar-inner{
      max-width: 1120px; margin:0 auto; padding:10px 16px;
      display:flex; align-items:center; justify-content:space-between; gap:16px;
      pointer-events: auto;
    }
    .brand{ display:flex; align-items:center; text-decoration:none }
    .logo{ height:40px; display:block }
    .top-actions{ display:flex; align-items:center; gap:16px }
    .tlink{
      display:inline-block; padding:8px 10px; border-radius:8px;
      color:#0f172a; text-decoration:none; font-weight:600;
    }
    .tlink:hover{ background:#f1f5f9 }
    .user-name{ color:#334155; font-weight:600 }
    .avatar{ width:36px; height:36px; border-radius:9999px; display:block }

    /* Layout: ensure sidebar never overlaps topbar */
    .layout{ max-width:1120px; margin:20px auto; padding:0 16px; display:flex; gap:18px; position: relative; z-index: 1; }
    .sidebar{
      width:230px; background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:12px;
      position: sticky; top:72px; z-index: 1;  /* LOWER than topbar (2000) */
    }
    .sbtn{ display:flex; align-items:center; gap:8px; padding:10px; border-radius:10px;
           color:#0f172a; text-decoration:none; border:0; background:#f8fafc; margin-bottom:8px }
    .sbtn.active{ background:#e2e8f0 }
    .sbtn.logout{ background:#fee2e2; color:#991b1b }
    .content{ flex:1 }

    /* Cards */
    .profile-head{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;margin-bottom:16px}
    .job-post-card,.history-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;margin-bottom:16px}
    .form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
    .form-group{display:flex;flex-direction:column;gap:6px}
    .form-full{grid-column:1/-1}
    .form-group input,.form-group select,.form-group textarea{padding:10px;border:1px solid #cbd5e1;border-radius:8px}
    .btn-primary{display:inline-flex;align-items:center;gap:6px;background:#2563eb;color:#fff;border:0;padding:10px 14px;border-radius:10px;cursor:pointer}
    .btn-ghost{display:inline-flex;align-items:center;gap:6px;border:1px solid #cbd5e1;padding:8px 10px;border-radius:10px;text-decoration:none;color:#0f172a}
    .job-history-list{display:flex;flex-direction:column;gap:12px}
    .history-item{display:flex;align-items:center;justify-content:space-between;border:1px solid #e5e7eb;border-radius:10px;padding:12px}
    .job-title{font-weight:700}
    .text-muted{color:#64748b}

    /* Prevent any accidental overlay clicks */
    header.topbar, header.topbar * { pointer-events: auto; }
  </style>
</head>
<body>
  <!-- Top bar (same structure as other pages) -->
  <header class="topbar">
    <div class="topbar-inner">
      <a href="home.php" class="brand">
        <img src="./JobGate_logo.png" alt="JobGate" class="logo" />
      </a>
      <nav class="top-actions" aria-label="Top navigation">
        <a href="home.php" class="tlink">Home</a>
        <a href="recruiter_profile.php" class="tlink">Profile</a>
        <span class="user-name"><?php echo htmlspecialchars($full_name); ?></span>
        <img src="./avatar_placeholder.jpg" class="avatar" alt="User avatar" />
      </nav>
    </div>
  </header>

  <div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <a class="sbtn" href="home.php">
        <iconify-icon icon="mdi:view-dashboard"></iconify-icon>Dashboard
      </a>
      <a class="sbtn active" href="recruiter_profile.php">
        <iconify-icon icon="mdi:briefcase-edit-outline"></iconify-icon>Post Job
      </a>
      <a class="sbtn" href="jobs.php">
        <iconify-icon icon="mdi:account-group-outline"></iconify-icon>View Applicants
      </a>
      <div class="spacer"></div>
      <a class="sbtn logout" href="logout.php">
        <iconify-icon icon="mdi:logout"></iconify-icon>Log out
      </a>
    </aside>

    <!-- Main Content -->
    <main class="content">
      <!-- Status -->
      <?php if (!empty($message)): ?>
        <div class="alert <?php echo ($message_type==='success')?'alert-success':'alert-error'; ?>">
          <?php echo htmlspecialchars($message); ?>
        </div>
      <?php endif; ?>

      <?php if (!$recruiter_id): ?>
        <div class="alert alert-error">Recruiter profile mapping missing. Ensure a row exists in <code>Recruiters</code> for your <code>user_id</code>.</div>
      <?php endif; ?>

      <!-- Job Posting Form -->
      <section class="job-post-card">
        <h3 class="card-title">Post a New Job Opening</h3>
        <form method="POST" action="recruiter_profile.php" enctype="multipart/form-data">
          <input type="hidden" name="action" value="post_job" />

          <div class="form-grid">
            <!-- Title -->
            <div class="form-group">
              <label for="title">Job Title *</label>
              <input type="text" id="title" name="title" required value="<?php echo isset($_POST['title'])?htmlspecialchars($_POST['title']):''; ?>">
            </div>

            <!-- Role -->
            <div class="form-group">
              <label for="job_role">Specific Role/Title</label>
              <input type="text" id="job_role" name="job_role" value="<?php echo isset($_POST['job_role'])?htmlspecialchars($_POST['job_role']):''; ?>">
            </div>

            <!-- Company Logo (saves to Recruiters.company_logo_url) -->
            <div class="form-group">
              <label for="logo">Company Logo (Max 2MB)</label>
              <input type="file" id="logo" name="logo" accept="image/*">
              <small class="text-muted">Saved as your company logo (Jobs table has no logo column).</small>
            </div>

            <!-- Sector -->
            <div class="form-group">
              <label for="sector_id">Job Sector *</label>
              <select id="sector_id" name="sector_id" required>
                <option value="">Select Sector</option>
                <?php foreach ($sectors as $sector): ?>
                  <option value="<?php echo htmlspecialchars($sector['sector_id']); ?>"
                    <?php echo (isset($_POST['sector_id']) && $_POST['sector_id']===$sector['sector_id'])?'selected':''; ?>>
                    <?php echo htmlspecialchars($sector['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Location -->
            <div class="form-group">
              <label for="location">Location</label>
              <input type="text" id="location" name="location" value="<?php echo isset($_POST['location'])?htmlspecialchars($_POST['location']):''; ?>">
            </div>

            <!-- Employment Type -->
            <div class="form-group">
              <label for="type">Employment Type *</label>
              <select id="type" name="type" required>
                <?php
                  $t = isset($_POST['type'])?$_POST['type']:'Full-time';
                  foreach (['Full-time','Part-time','Contract','Internship'] as $opt) {
                    $sel = ($t===$opt)?'selected':'';
                    echo '<option value="'.htmlspecialchars($opt).'" '.$sel.'>'.htmlspecialchars($opt).'</option>';
                  }
                ?>
              </select>
            </div>

            <!-- Salary Min -->
            <div class="form-group">
              <label for="salary_min">Salary Min</label>
              <input type="number" id="salary_min" name="salary_min" min="0" step="100" value="<?php echo isset($_POST['salary_min'])?htmlspecialchars($_POST['salary_min']):'0'; ?>">
            </div>

            <!-- Salary Max -->
            <div class="form-group">
              <label for="salary_max">Salary Max</label>
              <input type="number" id="salary_max" name="salary_max" min="0" step="100" value="<?php echo isset($_POST['salary_max'])?htmlspecialchars($_POST['salary_max']):'0'; ?>">
            </div>

            <!-- Application Deadline -->
            <div class="form-group">
              <label for="application_deadline">Application Deadline *</label>
              <input type="date" id="application_deadline" name="application_deadline" required
                     value="<?php echo isset($_POST['application_deadline'])?htmlspecialchars($_POST['application_deadline']):(isset($_POST['deadline'])?htmlspecialchars($_POST['deadline']):''); ?>">
            </div>

            <!-- Assessment -->
            <div class="form-group form-full">
              <label for="assessment_id">Mandatory Skill Assessment (Optional)</label>
              <select id="assessment_id" name="assessment_id">
                <option value="">No Mandatory Assessment</option>
                <?php foreach ($assessments as $assessment): ?>
                  <option value="<?php echo htmlspecialchars($assessment['assessment_id']); ?>"
                    <?php echo (isset($_POST['assessment_id']) && $_POST['assessment_id']===$assessment['assessment_id'])?'selected':''; ?>>
                    <?php echo htmlspecialchars($assessment['title']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted">If selected, candidates must pass (≥ 80) before applying.</small>
            </div>

            <!-- Description -->
            <div class="form-group form-full">
              <label for="description">Job Description *</label>
              <textarea id="description" name="description" rows="5" required><?php echo isset($_POST['description'])?htmlspecialchars($_POST['description']):''; ?></textarea>
            </div>

            <!-- Requirements -->
            <div class="form-group form-full">
              <label for="requirements">Key Requirements / Skills</label>
              <textarea id="requirements" name="requirements" rows="3"><?php echo isset($_POST['requirements'])?htmlspecialchars($_POST['requirements']):''; ?></textarea>
            </div>
          </div>

          <div class="form-actions" style="margin-top:10px">
            <button type="submit" class="btn-primary"><iconify-icon icon="mdi:send"></iconify-icon> Publish Job</button>
          </div>
        </form>
      </section>

      <!-- Job History -->
      <section class="history-card">
        <h3 class="card-title">Your Posted Jobs (<?php echo count($job_history); ?>)</h3>
        <?php if (empty($job_history)): ?>
          <p class="text-muted">You have not posted any jobs yet.</p>
        <?php else: ?>
          <div class="job-history-list">
            <?php foreach ($job_history as $job): ?>
              <div class="history-item">
                <div>
                  <div class="job-title"><?php echo htmlspecialchars($job['title']); ?></div>
                  <div class="job-meta">
                    <?php if (!empty($job['job_role'])): ?>
                      Role: <?php echo htmlspecialchars($job['job_role']); ?> |
                    <?php endif; ?>
                    <?php if (!empty($job['location'])): ?>
                      Location: <?php echo htmlspecialchars($job['location']); ?> |
                    <?php endif; ?>
                    Type: <?php echo htmlspecialchars($job['type']); ?>
                    <?php if (!empty($job['salary_min']) || !empty($job['salary_max'])): ?>
                      | Salary: <?php echo htmlspecialchars($job['salary_min']); ?> - <?php echo htmlspecialchars($job['salary_max']); ?>
                    <?php endif; ?>
                    <?php if (!empty($job['posted_at'])): ?>
                      | Posted: <?php echo date('M d, Y', strtotime($job['posted_at'])); ?>
                    <?php endif; ?>
                    <?php if (!empty($job['application_deadline'])): ?>
                      | Deadline: <?php echo date('M d, Y', strtotime($job['application_deadline'])); ?>
                    <?php endif; ?>
                  </div>
                </div>
                <a href="job_details.php?jobId=<?php echo htmlspecialchars($job['job_id']); ?>" class="btn-ghost">View</a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
</body>
</html>
