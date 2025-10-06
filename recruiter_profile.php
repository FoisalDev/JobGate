<?php
// recruiter_profile.php — resilient to schema differences
require_once 'db_connect.php';
session_start();

/* DEV: show errors (turn off in production) */
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

/* Column exists via INFORMATION_SCHEMA (prepared-safe) */
function column_exists($conn, $table, $column) {
  $sql = "SELECT COUNT(*) AS c
          FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ss", $table, $column);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return isset($res['c']) && (int)$res['c'] > 0;
}

/* pick first existing column from a list */
function first_existing_column($conn, $table, $candidates) {
  foreach ($candidates as $col) {
    if (column_exists($conn, $table, $col)) return $col;
  }
  return null;
}

/* Map Users.user_id -> Recruiters.recruiter_id */
$recruiter_id = null;
try {
  $stmt = $conn->prepare("SELECT recruiter_id FROM Recruiters WHERE user_id = ?");
  $stmt->bind_param("s", $user_id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  if ($row) $recruiter_id = $row['recruiter_id'];
  $stmt->close();
} catch (Throwable $e) {
  $message = "DB error mapping recruiter: ".$e->getMessage();
  $message_type = 'error';
}

/* POST: create job */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action']==='post_job') {
  try {
    if (!$recruiter_id) throw new Exception("No recruiter profile found for this user.");

    // Optional logo upload
    $logo_path = null;
    if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
      $dir_fs  = __DIR__ . '/uploads/job_logos/';
      $dir_web = 'uploads/job_logos/';
      if (!is_dir($dir_fs)) mkdir($dir_fs, 0777, true);

      $info = @getimagesize($_FILES['logo']['tmp_name']);
      if ($info === false) throw new Exception("Invalid image file.");

      $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) throw new Exception("Only JPG, JPEG, PNG, GIF, WEBP allowed.");
      if ($_FILES['logo']['size'] > 2*1024*1024) throw new Exception("File too large. Max 2MB.");

      $fname = guid().'.'.$ext;
      if (!move_uploaded_file($_FILES['logo']['tmp_name'], $dir_fs.$fname)) {
        throw new Exception("Failed to move uploaded file. Check folder permissions.");
      }
      $logo_path = $dir_web.$fname; // save in DB
    }

    // Inputs (schema uses: title, description, logo_path, *maybe* deadline)
    $job_id      = guid();
    $title       = sanitize_input($_POST['title'] ?? '');
    $job_role    = sanitize_input($_POST['job_role'] ?? '');
    $sector_id   = sanitize_input($_POST['sector_id'] ?? '');
    $type        = sanitize_input($_POST['type'] ?? 'Full-time');
    $salary      = (int)($_POST['salary'] ?? 0);
    $description = sanitize_input($_POST['description'] ?? '');
    $requirements= sanitize_input($_POST['requirements'] ?? '');
    $featured    = isset($_POST['featured']) ? 1 : 0;
    $assessment_id = sanitize_input($_POST['assessment_id'] ?? '');

    if ($title==='' || $job_role==='' || $sector_id==='' || $description==='') {
      throw new Exception("Please fill required fields (Title, Role, Sector, Description).");
    }

    // Optional columns
    $deadline_col  = column_exists($conn, 'Jobs', 'deadline') ? 'deadline' : null;
    $created_col   = first_existing_column($conn, 'Jobs', ['creation_date','posted_date','created_at']);

    $conn->begin_transaction();

    // Build INSERT dynamically to match your schema
    $fields = ['job_id','recruiter_id','sector_id','title','job_role','logo_path','job_type','salary','description','requirements','is_featured'];
    $placeholders = array_fill(0, count($fields), '?');
    $params = [$job_id, $recruiter_id, $sector_id, $title, $job_role, $logo_path, $type, $salary, $description, $requirements, $featured];
    $types  = 'sssssssissi'; // s,s,s,s,s,s,s,i,s,s,i

    // deadline (optional)
    if ($deadline_col) {
      $deadline = sanitize_input($_POST['deadline'] ?? '');
      if ($deadline === '') throw new Exception("Please provide Application Deadline.");
      $fields[] = $deadline_col;
      $placeholders[] = '?';
      $params[] = $deadline;
      $types   .= 's';
    }

    // created/posted date column (optional)
    if ($created_col) {
      $fields[] = $created_col;
      $placeholders[] = 'NOW()'; // direct NOW(), no bind
    }

    $sql = "INSERT INTO Jobs (".implode(',', $fields).") VALUES (".implode(',', $placeholders).")";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();

    // Optional assessment mapping
    if ($assessment_id !== '') {
      $req_id = guid();
      $st2 = $conn->prepare("INSERT INTO JobAssessmentRequirements (req_id, job_id, assessment_id) VALUES (?,?,?)");
      $st2->bind_param("sss", $req_id, $job_id, $assessment_id);
      $st2->execute();
      $st2->close();
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

/* History (deadline + created date column optional) */
$job_history = [];
try {
  if ($recruiter_id) {
    $deadline_col = column_exists($conn, 'Jobs', 'deadline') ? 'deadline' : null;
    $created_col  = first_existing_column($conn, 'Jobs', ['creation_date','posted_date','created_at']);

    // select list
    $selectCols = "job_id, title, job_role, is_featured";
    if ($created_col) $selectCols .= ", $created_col AS created_dt";
    if ($deadline_col) $selectCols .= ", $deadline_col AS deadline";

    // order by: prefer created col; fallback job_id
    $orderBy = $created_col ? $created_col." DESC" : "job_id DESC";

    $sqlH = "SELECT $selectCols FROM Jobs WHERE recruiter_id = ? ORDER BY $orderBy";
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
    .alert{padding:12px;border-radius:8px;margin:12px 0}
    .alert-success{background:#dcfce7;color:#166534}
    .alert-error{background:#fee2e2;color:#991b1b}
  </style>
</head>
<body>
  <header class="topbar">
    <div class="topbar-inner">
      <img src="./JobGate_logo.png" alt="JobGate" class="logo" />
      <nav class="top-actions">
        <a href="home.php" class="tlink">Home</a>
        <a href="recruiter_profile.php" class="tlink">Profile</a>
        <span class="tlink"><?php echo htmlspecialchars($full_name); ?></span>
        <img src="./avatar_placeholder.jpg" class="avatar" alt="User avatar" />
      </nav>
    </div>
  </header>

  <div class="layout">
    <aside class="sidebar">
      <a class="sbtn" href="home.php"><iconify-icon icon="mdi:view-dashboard"></iconify-icon>Dashboard</a>
      <a class="sbtn active" href="recruiter_profile.php"><iconify-icon icon="mdi:briefcase-edit-outline"></iconify-icon>Post Job</a>
      <a class="sbtn" href="jobs.php"><iconify-icon icon="mdi:account-group-outline"></iconify-icon>View Applicants</a>
      <div class="spacer"></div>
      <a class="sbtn logout" href="logout.php"><iconify-icon icon="mdi:logout"></iconify-icon>Log out</a>
    </aside>

    <main class="content">
      <section class="profile-head">
        <div class="ph-left">
          <h1>Welcome, <?php echo htmlspecialchars($full_name); ?></h1>
          <p>Manage your job postings and brand assets.</p>
        </div>
      </section>

      <?php if (!empty($message)): ?>
        <div class="alert <?php echo ($message_type==='success')?'alert-success':'alert-error'; ?>">
          <?php echo htmlspecialchars($message); ?>
        </div>
      <?php endif; ?>

      <?php if (!$recruiter_id): ?>
        <div class="alert alert-error">Recruiter profile mapping missing. Ensure a row exists in <code>Recruiters</code> for your <code>user_id</code>.</div>
      <?php endif; ?>

      <section class="job-post-card">
        <h3 class="card-title">Post a New Job Opening</h3>
        <form method="POST" action="recruiter_profile.php" enctype="multipart/form-data">
          <input type="hidden" name="action" value="post_job" />

          <div class="form-grid">
            <div class="form-group">
              <label for="title">Job Title *</label>
              <input type="text" id="title" name="title" required value="<?php echo isset($_POST['title'])?htmlspecialchars($_POST['title']):''; ?>">
            </div>

            <div class="form-group">
              <label for="job_role">Specific Role/Title *</label>
              <input type="text" id="job_role" name="job_role" required value="<?php echo isset($_POST['job_role'])?htmlspecialchars($_POST['job_role']):''; ?>">
            </div>

            <div class="form-group">
              <label for="logo">Company Logo / Job Image (Max 2MB)</label>
              <input type="file" id="logo" name="logo" accept="image/*">
              <small class="text-muted">Used as featured image.</small>
            </div>

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

            <div class="form-group">
              <label for="type">Employment Type *</label>
              <select id="type" name="type" required>
                <?php
                  $t = isset($_POST['type'])?$_POST['type']:'Full-time';
                  foreach (['Full-time','Part-time','Contract'] as $opt) {
                    $sel = ($t===$opt)?'selected':'';
                    echo '<option value="'.htmlspecialchars($opt).'" '.$sel.'>'.htmlspecialchars($opt).'</option>';
                  }
                ?>
              </select>
            </div>

            <div class="form-group">
              <label for="salary">Salary (USD/Month)</label>
              <input type="number" id="salary" name="salary" min="0" step="100" value="<?php echo isset($_POST['salary'])?htmlspecialchars($_POST['salary']):'0'; ?>">
            </div>

            <?php if (column_exists($conn, 'Jobs', 'deadline')): ?>
            <div class="form-group">
              <label for="deadline">Application Deadline *</label>
              <input type="date" id="deadline" name="deadline" required value="<?php echo isset($_POST['deadline'])?htmlspecialchars($_POST['deadline']):''; ?>">
            </div>
            <?php endif; ?>

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

            <div class="form-group form-full">
              <label for="description">Job Description *</label>
              <textarea id="description" name="description" required rows="5"><?php echo isset($_POST['description'])?htmlspecialchars($_POST['description']):''; ?></textarea>
            </div>

            <div class="form-group form-full">
              <label for="requirements">Key Requirements / Skills</label>
              <textarea id="requirements" name="requirements" rows="3"><?php echo isset($_POST['requirements'])?htmlspecialchars($_POST['requirements']):''; ?></textarea>
            </div>
          </div>

          <div class="form-actions">
            <label style="margin-right: 16px;">
              <input type="checkbox" name="featured" <?php echo (!empty($_POST['featured']))?'checked':''; ?>>
              Feature on Homepage
            </label>
            <button type="submit" class="btn-primary"><iconify-icon icon="mdi:send"></iconify-icon> Publish Job</button>
          </div>
        </form>
      </section>

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
                    Role: <?php echo htmlspecialchars($job['job_role']); ?>
                    <?php if (isset($job['created_dt']) && !empty($job['created_dt'])): ?>
                      | Posted: <?php echo date('M d, Y', strtotime($job['created_dt'])); ?>
                    <?php endif; ?>
                    <?php if (isset($job['deadline']) && !empty($job['deadline'])): ?>
                      | Deadline: <?php echo date('M d, Y', strtotime($job['deadline'])); ?>
                    <?php endif; ?>
                    <?php if (!empty($job['is_featured'])): ?> | <strong>Featured</strong><?php endif; ?>
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
