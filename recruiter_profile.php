<?php
// recruiter_profile.php — Rerouter for Recruiter's personal profile and job posting/history

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

/* Guard */
if (!is_logged_in()) { redirect('login.php'); exit; }
if ($_SESSION['user_type'] !== 'recruiter') { redirect('home.php'); exit; }

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Recruiter';

$message = '';
$message_type = '';
$job_to_edit = null; // Holds job data if we are in edit mode
$current_job_id = sanitize_input($_GET['job_id'] ?? ''); // Check for job ID in URL

/* Utility: check if a column exists */
function column_exists($conn, $table, $column) {
  $sql = "SELECT COUNT(*) c
          FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?";
  $st = $conn->prepare($sql);
  $st->bind_param("ss", $table, $column);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  $st->close();
  return !empty($row['c']);
}

/* Ensure necessary columns exist */
try {
    if (!column_exists($conn, 'Users', 'profile_photo_url')) { $conn->query("ALTER TABLE Users ADD COLUMN profile_photo_url VARCHAR(255) NULL"); }
    if (!column_exists($conn, 'Recruiters', 'company_name')) { $conn->query("ALTER TABLE Recruiters ADD COLUMN company_name VARCHAR(255) NULL"); }
    if (!column_exists($conn, 'Recruiters', 'company_address')) { $conn->query("ALTER TABLE Recruiters ADD COLUMN company_address VARCHAR(255) NULL"); }
} catch (Throwable $e) { /* ignore column changes */ }


/* Map user -> recruiter and load all profile data */
$recruiter_id = null;
$company_name = null;
$company_address = null;
$user_email = null;
$profile_photo_url = './avatar_placeholder.jpg'; // Recruiter's personal photo

try {
  $stmt = $conn->prepare("SELECT R.recruiter_id, R.company_name, R.company_address, U.full_name, U.email, U.profile_photo_url
                          FROM Recruiters R
                          JOIN Users U ON R.user_id = U.user_id
                          WHERE R.user_id = ?");
  $stmt->bind_param("s", $user_id);
  $stmt->execute();
  $recRow = $stmt->get_result()->fetch_assoc();
  if ($recRow) {
    $recruiter_id = $recRow['recruiter_id'];
    $company_name = $recRow['company_name'];
    $company_address = $recRow['company_address'];
    $full_name = $recRow['full_name'];
    $user_email = $recRow['email'];
    if (!empty($recRow['profile_photo_url'])) {
        $profile_photo_url = $recRow['profile_photo_url'];
    }
  }
  $stmt->close();
} catch (Throwable $e) {
  $message = "DB error loading recruiter profile: ".$e->getMessage();
  $message_type = 'error';
}


/* Load Job for Editing if job_id is present in URL */
if ($recruiter_id && $current_job_id) {
    try {
        $sql = "SELECT * FROM Jobs WHERE job_id = ? AND recruiter_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $current_job_id, $recruiter_id);
        $stmt->execute();
        $job_to_edit = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$job_to_edit) {
            $current_job_id = null;
            $message = "Job not found or access denied.";
            $message_type = 'error';
        } else {
            // Also load assessment ID if it exists
            $sqlA = "SELECT assessment_id FROM JobAssessmentRequirements WHERE job_id = ?";
            $stA = $conn->prepare($sqlA);
            $stA->bind_param("s", $current_job_id);
            $stA->execute();
            $assessRow = $stA->get_result()->fetch_assoc();
            $stA->close();
            if ($assessRow) {
                $job_to_edit['assessment_id'] = $assessRow['assessment_id'];
            }
        }
    } catch (Throwable $e) {
        $message = "Error loading job details: ".$e->getMessage();
        $message_type = 'error';
        $current_job_id = null;
        $job_to_edit = null;
    }
}


/* Handle Profile Save (User and Recruiter Data) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ( $_POST['action'] ?? '' ) === 'save_user_profile') {
    try {
        if (!$recruiter_id) throw new Exception("No recruiter profile found for this user.");

        $newName = sanitize_input($_POST['user_full_name'] ?? '');
        $newEmail = filter_var($_POST['user_email'] ?? '', FILTER_SANITIZE_EMAIL);
        $newCompanyName = sanitize_input($_POST['company_name'] ?? '');
        $newCompanyAddress = sanitize_input($_POST['company_address'] ?? '');
        
        if ($newName === '' || $newEmail === '') throw new Exception("Name and Email are required.");

        $photo_url_update = '';
        $user_params = [];
        $user_types = '';
        
        /* 1) Handle Profile Photo Upload (to /uploads/profile_photos/) */
        if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $dirFs  = __DIR__ . '/uploads/profile_photos/'; 
            $dirWeb = 'uploads/profile_photos/';
            if (!is_dir($dirFs)) mkdir($dirFs, 0777, true);

            $info = @getimagesize($_FILES['profile_photo']['tmp_name']);
            if ($info === false) throw new Exception("Invalid image file.");

            $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) throw new Exception("Only JPG, JPEG, PNG, GIF, WEBP allowed.");
            if ($_FILES['profile_photo']['size'] > 2*1024*1024) throw new Exception("File too large. Max 2MB.");

            $fname = GUID().'.'.$ext;
            if (!move_uploaded_file($_FILES['profile_photo']['tmp_name'], $dirFs.$fname)) {
                throw new Exception("Failed to move uploaded file. Check folder permissions.");
            }
            $uploaded_url = $dirWeb.$fname;
            $photo_url_update = ', profile_photo_url = ?';
            $user_params[] = $uploaded_url;
            $user_types .= 's';
        }
        
        // Start Transaction
        $conn->begin_transaction();

        /* 2) Update Users table (Name, Email, Photo) */
        $sqlU = "UPDATE Users 
                 SET full_name = ?, email = ? {$photo_url_update}
                 WHERE user_id = ?";
        
        $user_params = array_merge([$newName, $newEmail], $user_params, [$user_id]);
        $user_types = 'ss' . substr($user_types, 0) . 's';
        
        $stU = $conn->prepare($sqlU);
        $stU->bind_param($user_types, ...$user_params);
        $stU->execute();
        $stU->close();

        /* 3) Update Recruiters table (Company Name, Address) */
        $sqlR = "UPDATE Recruiters 
                 SET company_name = ?, company_address = ?
                 WHERE recruiter_id = ?";
        
        $stR = $conn->prepare($sqlR);
        $stR->bind_param("sss", $newCompanyName, $newCompanyAddress, $recruiter_id);
        $stR->execute();
        $stR->close();
        
        $conn->commit();
        
        // Refresh session data and redirect to clear POST
        $_SESSION['full_name'] = $newName; // Update header name
        header("Location: recruiter_profile.php?msg=saved");
        exit;

    } catch (Throwable $e) {
        if ($conn && $conn->errno) {
            try {
                $conn->rollback();
            } catch (\Throwable $rb_e) { /* ignore rollback error */ }
        }
        $message = "Profile Save Error: ".$e->getMessage();
        $message_type = 'error';
    }
}

/* POST: Delete Job */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ( $_POST['action'] ?? '' ) === 'delete_job') {
    try {
        if (!$recruiter_id) throw new Exception("No recruiter profile found.");
        $job_id_to_delete = sanitize_input($_POST['job_id'] ?? '');

        if (empty($job_id_to_delete)) throw new Exception("Job ID missing for deletion.");

        // NOTE: JobApplication and JobAssessmentRequirements tables might have foreign keys
        // pointing to Jobs. You might need to delete from those tables first, 
        // or ensure your foreign keys have ON DELETE CASCADE set.
        
        $conn->begin_transaction();

        // Delete the job itself, ensuring the current user owns it
        $sqlDel = "DELETE FROM Jobs WHERE job_id = ? AND recruiter_id = ?";
        $stDel = $conn->prepare($sqlDel);
        $stDel->bind_param("ss", $job_id_to_delete, $recruiter_id);
        $stDel->execute();
        
        if ($stDel->affected_rows === 0) {
            $conn->rollback();
            throw new Exception("Deletion failed. Job not found or not owned by you.");
        }
        $stDel->close();
        
        $conn->commit();
        header("Location: recruiter_profile.php?msg=deleted");
        exit;

    } catch (Throwable $e) {
        if ($conn && $conn->errno) {
            try {
                $conn->rollback();
            } catch (\Throwable $rb_e) { /* ignore rollback error */ }
        }
        $message = "Deletion Error: ".$e->getMessage();
        $message_type = 'error';
    }
}


/* POST: Create or Update job */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ( $_POST['action'] ?? '' ) === 'post_job') {
  try {
    if (!$recruiter_id) throw new Exception("No recruiter profile found for this user.");
    
    $is_update = !empty($_POST['job_id']); // Check if we are updating an existing job
    $current_job_id_post = sanitize_input($_POST['job_id'] ?? GUID());

    /* 1) Handle image upload (Company/Job Logo) */
    $job_logo_url = null; // Default to null for insert/update
    
    // Check for existing company logo to use as default job logo
    $stL = $conn->prepare("SELECT company_logo_url FROM Recruiters WHERE recruiter_id = ?");
    $stL->bind_param("s", $recruiter_id);
    $stL->execute();
    $logoRow = $stL->get_result()->fetch_assoc();
    $stL->close();
    if ($logoRow && !empty($logoRow['company_logo_url'])) {
        $job_logo_url = $logoRow['company_logo_url'];
    }

    // Check for a NEW logo uploaded with the job post/edit form
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
        $uploaded_url = $dir_web.$fname;
        
        // Update company logo in Recruiters table (only if necessary)
        $stUp = $conn->prepare("UPDATE Recruiters SET company_logo_url = ? WHERE recruiter_id = ?");
        $stUp->bind_param("ss", $uploaded_url, $recruiter_id);
        $stUp->execute(); $stUp->close();
        
        $job_logo_url = $uploaded_url;
    }


    /* 2) Gather fields (schema exact) */
    $title      = sanitize_input($_POST['title'] ?? '');
    $job_role   = sanitize_input($_POST['job_role'] ?? '');
    $sector_id  = sanitize_input($_POST['sector_id'] ?? '');
    $location   = sanitize_input($_POST['location'] ?? '');
    $type       = sanitize_input($_POST['type'] ?? 'Full-time'); // ENUM
    $salary_min = (int)($_POST['salary_min'] ?? 0);
    $salary_max = (int)($_POST['salary_max'] ?? 0);
    $description= sanitize_input($_POST['description'] ?? '');
    $requirements = sanitize_input($_POST['requirements'] ?? '');
    $application_deadline = sanitize_input($_POST['application_deadline'] ?? ($_POST['deadline'] ?? ''));
    $assessment_id = sanitize_input($_POST['assessment_id'] ?? '');

    if ($title==='' || $sector_id==='' || $description==='' || $application_deadline==='') {
      throw new Exception("Please fill required fields (Title, Sector, Description, Application Deadline).");
    }
    if (!in_array($type, ['Full-time','Part-time','Contract','Internship'], true)) {
      throw new Exception("Invalid employment type.");
    }
    if ($salary_max && $salary_min && $salary_max < $salary_min) {
      throw new Exception("Salary max cannot be less than salary min.");
    }

    /* 3) Build INSERT/UPDATE dynamically */
    $conn->begin_transaction();
    $has_job_logo = column_exists($conn, 'Jobs', 'job_logo_url');

    if ($is_update) {
        // UPDATE Logic
        $update_fields = [
            'sector_id = ?', 'title = ?', 'job_role = ?', 'location = ?', 'type = ?',
            'salary_min = ?', 'salary_max = ?', 'description = ?', 'requirements = ?', 'application_deadline = ?'
        ];
        $update_params = [$sector_id, $title, $job_role, $location, $type, $salary_min, $salary_max, $description, $requirements, $application_deadline];
        $update_types = 'sssssiisss';

        if ($has_job_logo) {
            $update_fields[] = 'job_logo_url = ?';
            $update_params[] = $job_logo_url;
            $update_types .= 's';
        }

        $sql = "UPDATE Jobs SET ".implode(', ', $update_fields)." WHERE job_id = ? AND recruiter_id = ?";
        $update_params[] = $current_job_id_post;
        $update_params[] = $recruiter_id;
        $update_types .= 'ss';

    } else {
        // INSERT Logic
        $fields = ['job_id','recruiter_id','sector_id','title','job_role','location','type','salary_min','salary_max','description','requirements','application_deadline'];
        $place  = ['?','?','?','?','?','?','?','?','?','?','?','?'];
        $types  = 'sssssssiisss'; 
        $params = [$current_job_id_post, $recruiter_id, $sector_id, $title, $job_role, $location, $type, $salary_min, $salary_max, $description, $requirements, $application_deadline];

        if ($has_job_logo) {
            $fields[] = 'job_logo_url';
            $place[]  = '?';
            $types   .= 's';
            $params[] = $job_logo_url;
        }

        $sql = "INSERT INTO Jobs (".implode(',', $fields).") VALUES (".implode(',', $place).")";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...($is_update ? $update_params : $params));
    $stmt->execute();
    $stmt->close();

    /* 4) Assessment mapping (Update or Insert) */
    // Delete existing requirements first
    $stDel = $conn->prepare("DELETE FROM JobAssessmentRequirements WHERE job_id = ?");
    $stDel->bind_param("s", $current_job_id_post);
    $stDel->execute(); $stDel->close();
    
    if ($assessment_id !== '') {
      $st2 = $conn->prepare("INSERT INTO JobAssessmentRequirements (job_id, assessment_id) VALUES (?, ?)");
      $st2->bind_param("ss", $current_job_id_post, $assessment_id);
      $st2->execute(); $st2->close();
    }

    $conn->commit();
    $message = $is_update ? "Job updated successfully!" : "Job posted successfully!";
    $message_type = 'success';
    
    // Redirect to the same page without job_id or just clear POST
    header("Location: recruiter_profile.php?msg=" . ($is_update ? "updated" : "posted"));
    exit;

  } catch (Throwable $e) {
    if ($conn && $conn->errno) {
        try {
            $conn->rollback();
        } catch (\Throwable $rb_e) { /* ignore rollback error */ }
    }
    $message = "Error: ".$e->getMessage();
    $message_type = 'error';
  }
}

// Check for success message after redirect
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'saved') {
        $message = "Profile details saved successfully!";
        $message_type = 'success';
    } elseif ($_GET['msg'] === 'posted') {
        $message = "Job posted successfully!";
        $message_type = 'success';
    } elseif ($_GET['msg'] === 'updated') {
        $message = "Job updated successfully!";
        $message_type = 'success';
    } elseif ($_GET['msg'] === 'deleted') {
        $message = "Job successfully deleted!";
        $message_type = 'success';
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

/* History */
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
    /* Base Styles from the user's provided CSS */
    .topbar{ position: sticky; top:0; z-index: 2000; background:#ffffff; border-bottom:1px solid #e5e7eb; }
    .topbar-inner{ max-width: 1120px; margin:0 auto; padding:10px 16px; display:flex; align-items:center; justify-content:space-between; gap:16px; }
    .brand{ display:flex; align-items:center; text-decoration:none }
    .logo{ height:40px; display:block }
    .top-actions{ display:flex; align-items:center; gap:16px }
    .tlink{ display:inline-block; padding:8px 10px; border-radius:8px; color:#0f172a; text-decoration:none; font-weight:600; }
    .tlink:hover{ background:#f1f5f9 }
    .user-name{ color:#334155; font-weight:600 }
    .avatar{ width:36px; height:36px; border-radius:9999px; display:block }

    /* Layout & Sidebar structure definitions */
    /* FIX: Changed layout from flex to grid to match profile.php structure */
    .layout{ 
        max-width:1120px; 
        margin:20px auto; 
        padding:0 16px; 
        display:grid; /* Changed from flex */
        grid-template-columns: 260px 1fr; /* Explicit grid definition */
        gap:18px; 
        position: relative; 
        z-index: 1; 
    }
    
    /* MODIFIED: Sidebar to match previous dark structure, and be STICKY */
    .sidebar{ 
      width:100%; /* Takes 100% of its grid column */
      background:#0b1d3a; /* Dark Blue from your previous structure */
      color:#e2e8f0; /* Light text */
      padding:12px 14px;
      /* Core Fix: Use STICKY position within the grid */
      position: sticky; 
      top: 86px; /* Pin below the topbar (assuming 86px from profile.php) */
      z-index: 10; 
      display: flex; 
      flex-direction: column; 
      height: calc(100vh - 86px - 20px); /* Height calculation to fit viewport */
    }

    /* MODIFIED: SBTN styles to match dark sidebar structure */
    .sbtn{ display:flex; align-items:center; gap:8px; padding:10px; border-radius:10px; 
           color:#e2e8f0; /* Light text */
           text-decoration:none; border:0; 
           background:transparent; /* Transparent base */
           margin-bottom:8px;
           width: 100%;
           font-weight: 800; /* Matching profile.php button style */
           cursor: pointer;
    }
    .sbtn.active{ 
        background:rgba(255, 255, 255, 0.1); /* Light background for active */
        color:#ffffff;
    }
    .sbtn:hover{
        background: rgba(255, 255, 255, 0.06);
    }
    .sbtn.logout{ 
      background:#fee2e2; color:#991b1b; 
      margin-top: auto; 
      margin-bottom: 0; 
    }
    .spacer {
      flex-grow: 1; 
    }
    /* END MODIFIED SBTN STYLES */

    
    .content{ flex:1 }

    .job-post-card,.history-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;margin-bottom:16px}
    .form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
    .form-group{display:flex;flex-direction:column;gap:6px}
    .form-full{grid-column:1/-1}
    .form-group input,.form-group select,.form-group textarea{padding:10px;border:1px solid #cbd5e1;border-radius:8px}
    .btn-primary{display:inline-flex;align-items:center;gap:6px;background:#2563eb;color:#fff;border:0;padding:10px 14px;border-radius:10px;cursor:pointer}
    .btn-ghost{display:inline-flex;align-items:center;gap:6px;border:1px solid #cbd5e1;padding:8px 10px;border-radius:10px;text-decoration:none;color:#0f172a}
    
    /* MODIFIED: Job History List to be scrollable */
    .job-history-list{
      display:flex;flex-direction:column;gap:12px;
      max-height: 400px; /* Set a maximum height */
      overflow-y: auto; /* Enable vertical scrolling */
      padding-right: 10px; /* Space for the scrollbar */
    }
    .history-item{display:flex;align-items:center;justify-content:space-between;border:1px solid #e5e7eb;border-radius:10px;padding:12px}
    .job-title{font-weight:700}
    .text-muted{color:#64748b}
    .alert{ padding: 10px; border-radius: 8px; margin-bottom: 15px; }
    .alert-success{ background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .alert-error{ background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

    /* Profile Specific Styles */
    .profile-head-info{
      display: flex; 
      gap: 20px; 
      align-items: flex-start;
      margin-bottom: 15px;
    }
    .profile-info-display{ flex: 1; }
    .profile-info-display h1{ margin: 0 0 5px 0; font-size: 1.8rem; }
    .profile-info-display p{ margin: 0 0 5px 0; color: #4b5563; font-size: 0.9rem; }
    .profile-info-display strong{ font-weight: 700; color: #1f2937; }
    
    .avatar-wrap{ position: relative; width: 80px; height: 80px; flex-shrink: 0; }
    .avatar-lg{ width: 80px; height: 80px; border-radius: 9999px; object-fit: cover; border: 2px solid #e5e7eb; }
    .cam-btn{ 
      position: absolute; bottom: 0; right: 0; 
      width: 25px; height: 25px; border-radius: 9999px; 
      background: #2563eb; color: white; border: 2px solid #fff; 
      display: flex; align-items: center; justify-content: center; 
      cursor: pointer; padding: 0; font-size: 14px;
    }
    .cam-btn iconify-icon{ font-size: 14px; }
    .cam-btn:hover{ background: #1d4ed8; }
    .form-grid.profile-grid{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
  </style>
</head>
<body <?php echo $job_to_edit ? 'onload="scrollToJobPost()"' : ''; ?>>
  <header class="topbar">
    <div class="topbar-inner">
      <a href="home.php" class="brand">
        <img src="./JobGate_logo.png" alt="JobGate" class="logo" />
      </a>
      <nav class="top-actions" aria-label="Top navigation">
        <a href="home.php" class="tlink">Home</a>
        <a href="recruiter_profile.php" class="tlink">Profile</a>
        <span class="user-name"><?php echo htmlspecialchars($full_name); ?></span>
        <img src="<?php echo htmlspecialchars($profile_photo_url); ?>" class="avatar" alt="User avatar" />
      </nav>
    </div>
  </header>

  <div class="layout">
    <aside class="sidebar">
      <a class="sbtn" href="home.php"><iconify-icon icon="mdi:view-dashboard"></iconify-icon>Dashboard</a>
      <a class="sbtn active" href="recruiter_profile.php"><iconify-icon icon="mdi:briefcase-edit-outline"></iconify-icon>Post Job</a>
      <div class="spacer"></div>
      <a class="sbtn logout" href="logout.php"><iconify-icon icon="mdi:logout"></iconify-icon>Log out</a>
    </aside>

    <main class="content">
      <?php if (!empty($message)): ?>
        <div class="alert <?php echo ($message_type==='success')?'alert-success':'alert-error'; ?>">
          <?php echo htmlspecialchars($message); ?>
        </div>
      <?php endif; ?>

      <?php if (!$recruiter_id): ?>
        <div class="alert alert-error">Recruiter profile mapping missing. Ensure a row exists in <code>Recruiters</code> for your <code>user_id</code>.</div>
      <?php endif; ?>

      <section class="job-post-card">
        <h3 class="card-title">My Profile & Company Details</h3>
        <form method="POST" action="recruiter_profile.php" enctype="multipart/form-data">
          <input type="hidden" name="action" value="save_user_profile" />

          <div class="profile-head-info">
            <div class="avatar-wrap">
              <img id="profilePhoto" src="<?php echo htmlspecialchars($profile_photo_url); ?>" alt="Profile Photo" class="avatar-lg" />
              <button class="cam-btn" id="btnPhotoUpload" type="button" title="Upload profile photo">
                <iconify-icon icon="mdi:camera"></iconify-icon>
              </button>
              <input type="file" name="profile_photo" id="photoInput" accept="image/*" hidden />
            </div>
            
            <div class="profile-info-display">
              <h1><?php echo htmlspecialchars($full_name ?: 'Your Name'); ?></h1>
              <p>Email: <strong><?php echo htmlspecialchars($user_email ?: 'N/A'); ?></strong></p>
              <p>Company: <strong><?php echo htmlspecialchars($company_name ?: 'Not set'); ?></strong></p>
              <p>Address: <span><?php echo htmlspecialchars($company_address ?: 'Not set'); ?></span></p>
            </div>
          </div>
          
          <div class="form-grid profile-grid">
            
            <div class="form-group">
              <label for="user_full_name">Your Full Name *</label>
              <input type="text" id="user_full_name" name="user_full_name" required 
                     value="<?php echo htmlspecialchars($full_name ?? ''); ?>">
            </div>

            <div class="form-group">
              <label for="user_email">Email *</label>
              <input type="email" id="user_email" name="user_email" required 
                     value="<?php echo htmlspecialchars($user_email ?? ''); ?>">
            </div>

            <div class="form-group">
              <label for="company_name">Company Name</label>
              <input type="text" id="company_name" name="company_name" 
                     value="<?php echo htmlspecialchars($company_name ?? ''); ?>">
            </div>

            <div class="form-group">
              <label for="company_address">Company Address / Location</label>
              <input type="text" id="company_address" name="company_address" 
                     value="<?php echo htmlspecialchars($company_address ?? ''); ?>">
            </div>
            
          </div>

          <div class="form-actions" style="margin-top:15px">
            <button type="submit" class="btn-primary"><iconify-icon icon="mdi:content-save-outline"></iconify-icon> Save Profile Details</button>
          </div>
        </form>
      </section>

      <section id="job-post-section" class="job-post-card">
        <h3 class="card-title"><?php echo $job_to_edit ? 'Edit Job: ' . htmlspecialchars($job_to_edit['title']) : 'Post a New Job Opening'; ?></h3>
        
        <form method="POST" action="recruiter_profile.php" enctype="multipart/form-data">
          <input type="hidden" name="action" value="post_job" />
          
          <?php if ($job_to_edit): ?>
            <input type="hidden" name="job_id" value="<?php echo htmlspecialchars($job_to_edit['job_id']); ?>" />
            <?php 
                // Set form data to job being edited
                $formData = $job_to_edit;
            ?>
          <?php else: ?>
            <?php 
                // Use posted data or empty defaults for new job
                $formData = $_POST;
            ?>
          <?php endif; ?>

          <div class="form-grid">
            <div class="form-group">
              <label for="title">Job Title *</label>
              <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($formData['title'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label for="job_role">Specific Role/Title</label>
              <input type="text" id="job_role" name="job_role" value="<?php echo htmlspecialchars($formData['job_role'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label for="logo">Company/Job Image (Max 2MB)</label>
              <input type="file" id="logo" name="logo" accept="image/*">
              <?php if ($job_to_edit && !empty($job_to_edit['job_logo_url'])): ?>
                <small class="text-muted">Current Logo: <a href="<?php echo htmlspecialchars($job_to_edit['job_logo_url']); ?>" target="_blank">View</a> | Upload new to replace.</small>
              <?php else: ?>
                <small class="text-muted">Upload logo for this job (optional).</small>
              <?php endif; ?>
            </div>

            <div class="form-group">
              <label for="sector_id">Job Sector *</label>
              <select id="sector_id" name="sector_id" required>
                <option value="">Select Sector</option>
                <?php foreach ($sectors as $sector): ?>
                  <option value="<?php echo htmlspecialchars($sector['sector_id']); ?>"
                    <?php echo (isset($formData['sector_id']) && $formData['sector_id']===$sector['sector_id'])?'selected':''; ?>>
                    <?php echo htmlspecialchars($sector['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="location">Location</label>
              <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($formData['location'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label for="type">Employment Type *</label>
              <select id="type" name="type" required>
                <?php
                  $t = htmlspecialchars($formData['type'] ?? 'Full-time');
                  foreach (['Full-time','Part-time','Contract','Internship'] as $opt) {
                    $sel = ($t===$opt)?'selected':'';
                    echo '<option value="'.htmlspecialchars($opt).'" '.$sel.'>'.htmlspecialchars($opt).'</option>';
                  }
                ?>
              </select>
            </div>

            <div class="form-group">
              <label for="salary_min">Salary Min</label>
              <input type="number" id="salary_min" name="salary_min" min="0" step="100" value="<?php echo htmlspecialchars($formData['salary_min'] ?? '0'); ?>">
            </div>

            <div class="form-group">
              <label for="salary_max">Salary Max</label>
              <input type="number" id="salary_max" name="salary_max" min="0" step="100" value="<?php echo htmlspecialchars($formData['salary_max'] ?? '0'); ?>">
            </div>

            <div class="form-group">
              <label for="application_deadline">Application Deadline *</label>
              <input type="date" id="application_deadline" name="application_deadline" required
                     value="<?php echo htmlspecialchars($formData['application_deadline'] ?? ''); ?>">
            </div>

            <div class="form-group form-full">
              <label for="assessment_id">Mandatory Skill Assessment (Optional)</label>
              <select id="assessment_id" name="assessment_id">
                <option value="">No Mandatory Assessment</option>
                <?php foreach ($assessments as $assessment): ?>
                  <option value="<?php echo htmlspecialchars($assessment['assessment_id']); ?>"
                    <?php 
                      $current_assessment = $formData['assessment_id'] ?? '';
                      if($job_to_edit && !empty($job_to_edit['assessment_id'])) {
                          $current_assessment = $job_to_edit['assessment_id'];
                      }
                      echo ($current_assessment===$assessment['assessment_id'])?'selected':''; 
                    ?>>
                    <?php echo htmlspecialchars($assessment['title']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted">If selected, candidates must pass (≥ 80) before applying.</small>
            </div>

            <div class="form-group form-full">
              <label for="description">Job Description *</label>
              <textarea id="description" name="description" rows="5" required><?php echo htmlspecialchars($formData['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group form-full">
              <label for="requirements">Key Requirements / Skills</label>
              <textarea id="requirements" name="requirements" rows="3"><?php echo htmlspecialchars($formData['requirements'] ?? ''); ?></textarea>
            </div>
          </div>

          <div class="form-actions" style="margin-top:10px; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn-primary">
                    <iconify-icon icon="<?php echo $job_to_edit ? 'mdi:content-save-outline' : 'mdi:send'; ?>"></iconify-icon> 
                    <?php echo $job_to_edit ? 'Update Job' : 'Publish Job'; ?>
                </button>
                <?php if ($job_to_edit): ?>
                <a href="recruiter_profile.php" class="btn-ghost" style="text-decoration: none;">
                    <iconify-icon icon="mdi:cancel"></iconify-icon> Cancel Edit
                </a>
                <?php endif; ?>
            </div>
            
            <?php if ($job_to_edit): ?>
            <button type="button" 
                    id="deleteJobBtn"
                    class="btn-primary" 
                    style="background: #dc2626;"
                    onclick="confirmDelete('<?php echo htmlspecialchars($job_to_edit['job_id']); ?>')">
                <iconify-icon icon="mdi:delete"></iconify-icon> Delete Job
            </button>
            <?php endif; ?>
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
                <a href="recruiter_profile.php?job_id=<?php echo htmlspecialchars($job['job_id']); ?>" 
                   class="btn-primary" 
                   style="padding: 10px 14px; text-decoration: none; background: #f97316;">
                  <iconify-icon icon="mdi:pencil"></iconify-icon> Edit Job
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
  
  <form id="deleteForm" method="POST" action="recruiter_profile.php" style="display: none;">
      <input type="hidden" name="action" value="delete_job">
      <input type="hidden" name="job_id" id="deleteJobId">
  </form>

  <script>
    // ** Photo Upload/Preview Functionality for User Profile Photo **
    const btnPhotoUpload = document.getElementById('btnPhotoUpload');
    const photoInput = document.getElementById('photoInput');
    const profilePhoto = document.getElementById('profilePhoto');

    // 1. Open file dialog on button click
    btnPhotoUpload?.addEventListener('click', () => photoInput?.click());
    
    // 2. Display preview when file is selected
    photoInput?.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (event) => {
                profilePhoto.src = event.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
    
    // ** Scrollable Editing Section Implimentation **
    function scrollToJobPost() {
      const jobPostSection = document.getElementById('job-post-section');
      if (jobPostSection) {
        jobPostSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }
    
    // ** Job Deletion Functionality **
    function confirmDelete(jobId) {
        if (confirm("Are you sure you want to delete this job? This action cannot be undone.")) {
            document.getElementById('deleteJobId').value = jobId;
            document.getElementById('deleteForm').submit();
        }
    }

    // Call scroll function if job_id is present in the URL on page load
    <?php if ($job_to_edit): ?>
        scrollToJobPost();
    <?php endif; ?>
  </script>
</body>
</html>