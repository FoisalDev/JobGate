<?php
// home_view_details.php — DB-driven job details with polished poster + scrollable long text
session_start();
require_once 'db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!is_logged_in()) { redirect('login.php'); }

$user_id   = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$full_name = $_SESSION['full_name'] ?? 'User';
$profilePage = ($user_type === 'recruiter') ? 'recruiter_profile.php' : 'profile.php';

$jobId = isset($_GET['jobId']) ? trim($_GET['jobId']) : '';
if ($jobId === '') { header("HTTP/1.1 400 Bad Request"); echo "Invalid request: jobId is required."; exit; }

$job = null; $assessment_required = null;
$profile_photo_url = './avatar_placeholder.jpg'; // Default placeholder

try {
  /* --- FIX START: Fetch User's profile photo URL --- */
  $sqlUser = "SELECT profile_photo_url FROM Users WHERE user_id = ? LIMIT 1";
  $stmtUser = $conn->prepare($sqlUser);
  $stmtUser->bind_param("s", $user_id);
  $stmtUser->execute();
  $userRow = $stmtUser->get_result()->fetch_assoc();
  $stmtUser->close();
  
  if ($userRow && !empty($userRow['profile_photo_url'])) {
      $profile_photo_url = $userRow['profile_photo_url'];
  }
  $avatarSrc = htmlspecialchars($profile_photo_url);
  /* --- FIX END --- */

  $sql = "SELECT 
            j.job_id,
            j.title,
            j.job_role,
            j.description,
            j.requirements,
            j.type,
            j.location,
            j.salary_min,
            j.salary_max,
            j.posted_at,
            j.application_deadline,
            u.full_name AS company_name,
            COALESCE(j.job_logo_url, r.company_logo_url) AS logo_url
          FROM Jobs j
          JOIN Recruiters r ON j.recruiter_id = r.recruiter_id
          JOIN Users u      ON r.user_id = u.user_id
          WHERE j.job_id = ?
          LIMIT 1";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("s", $jobId);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res->num_rows === 1) { $job = $res->fetch_assoc(); }
  $stmt->close();

  if ($job) {
    $sq2 = "SELECT assessment_id FROM JobAssessmentRequirements WHERE job_id = ? LIMIT 1";
    $st2 = $conn->prepare($sq2);
    $st2->bind_param("s", $jobId);
    $st2->execute();
    $r2 = $st2->get_result();
    if ($row2 = $r2->fetch_assoc()) { $assessment_required = $row2['assessment_id']; }
    $st2->close();
  }
} catch (Throwable $e) {
  header("HTTP/1.1 500 Internal Server Error");
  echo "Error loading job: ".$e->getMessage();
  exit;
}

if (!$job) { header("HTTP/1.1 404 Not Found"); echo "Job not found."; exit; }

function format_salary($min, $max) {
  $min = (int)$min; $max = (int)$max;
  if ($min && $max) return number_format($min)." - ".number_format($max);
  if ($min) return number_format($min);
  if ($max) return number_format($max);
  return null;
}
function render_requirements($req) {
  $req = trim($req ?? '');
  if ($req === '') return '';
  $parts = preg_split('/[\r\n,]+/', $req);
  $out = '';
  foreach ($parts as $p) { $t = trim($p); if ($t !== '') $out .= "<li>".htmlspecialchars($t)."</li>"; }
  return $out ?: '';
}

$logo     = htmlspecialchars($job['logo_url'] ?: './avatar_placeholder.jpg');
$title    = htmlspecialchars($job['title']);
$company  = htmlspecialchars($job['company_name']);
$job_role = htmlspecialchars($job['job_role'] ?? '');
$type     = htmlspecialchars($job['type']);
$location = htmlspecialchars($job['location'] ?? '');
$posted   = !empty($job['posted_at']) ? date('M d, Y', strtotime($job['posted_at'])) : '';
$deadline = !empty($job['application_deadline']) ? date('M d, Y', strtotime($job['application_deadline'])) : '';
$salary   = format_salary($job['salary_min'], $job['salary_max']);
$desc     = nl2br(htmlspecialchars($job['description'] ?? ''));
$req_ul   = render_requirements($job['requirements'] ?? '');
?>
<!DOCTYPE html>
<html lang="bn">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>JobGate — Job Details</title>
    <link rel="stylesheet" href="home_view_details.css" />
    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
    <style>
      /* === Consistent header (same as other pages) === */
      .topbar{position:sticky;top:0;z-index:1000;background:#fff;border-bottom:1px solid #e5e7eb}
      .topbar-inner{max-width:1120px;margin:0 auto;padding:10px 16px;display:flex;align-items:center;justify-content:space-between}
      .logo{height:40px;display:block}
      .top-actions{display:flex;align-items:center;gap:16px}
      .tlink{display:inline-flex;align-items:center;gap:6px;padding:8px 10px;border-radius:8px;color:#0f172a;text-decoration:none;font-weight:600}
      .tlink:hover{background:#f1f5f9}
      .avatar{width:36px;height:36px;border-radius:9999px}

      /* === Layout === */
      .wrap{max-width:1100px;margin:20px auto;padding:0 16px}
      .back{display:inline-flex;align-items:center;gap:6px;color:#0f172a;text-decoration:none;border:1px solid #cbd5e1;padding:6px 10px;border-radius:10px}

      .details-card{
        display:grid;grid-template-columns:1.1fr 1.4fr;gap:20px;
        background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-top:14px
      }

      /* === Professional Poster Sizing (16:9, responsive) === */
      .poster{
        background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;
        overflow:hidden;position:relative;box-shadow:0 6px 18px rgba(0,0,0,.06)
      }
      .poster.big{
        width:100%;
        aspect-ratio:16/9;
        min-height:260px;
        max-height:420px;
      }
      .poster.big img{
        width:100%;height:100%;object-fit:cover;display:block;
        transform:translateZ(0);
      }

      .desc h2{margin:0 0 8px;font-size:24px}
      .meta-row{display:flex;flex-wrap:wrap;gap:8px;margin:8px 0 14px}
      .chip{
        display:inline-flex;align-items:center;gap:6px;
        background:#f8fafc;border:1px solid #e5e7eb;color:#334155;
        padding:6px 10px;border-radius:9999px;font-size:13px
      }

      /* === Scrollable long text area === */
      .desc-scroll {
        max-height: 380px;
        overflow-y: auto;
        padding-right: 6px;
      }
      .desc-scroll::-webkit-scrollbar { width: 8px; }
      .desc-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 6px; }

      .desc h3{margin:18px 0 8px}
      .desc p{line-height:1.6}
      .desc ul{padding-left:18px;margin:0}

      .cta-row{display:flex;gap:10px;margin-top:18px}
      .btn-apply{background:#2563eb;color:#fff;border:0;padding:10px 14px;border-radius:10px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
      .btn-apply:hover{filter:brightness(.95)}

      @media (max-width: 980px){
        .details-card{grid-template-columns:1fr}
      }
    </style>
  </head>
  <body>
    <header class="topbar">
      <div class="topbar-inner">
        <img src="./JobGate_logo.png" alt="JobGate" class="logo" />
        <nav class="top-actions" aria-label="Top actions">
          <a href="home.php" class="tlink">
            <iconify-icon icon="mdi:home-variant" width="20" height="20"></iconify-icon>
            Home
          </a>
          <a href="<?php echo htmlspecialchars($profilePage); ?>" class="tlink">
            <iconify-icon icon="mdi:account" width="20" height="20"></iconify-icon>
            Profile
          </a>
          <img src="<?php echo $avatarSrc; ?>" class="avatar" alt="User avatar" 
               onerror="this.src='./avatar_placeholder.jpg';">
        </nav>
      </div>
    </header>

    <main class="wrap">
      <a class="back" href="home.php">
        <iconify-icon icon="mdi:arrow-left"></iconify-icon> Back
      </a>

      <section class="details-card">
        <div class="poster big">
          <img src="<?php echo $logo; ?>" alt="<?php echo $company; ?> Poster"
               onerror="this.src='./avatar_placeholder.jpg'">
        </div>

        <div class="desc">
          <h2><?php echo $title; ?> — <?php echo $company; ?></h2>

          <div class="meta-row">
            <?php if ($location): ?>
              <span class="chip"><iconify-icon icon="mdi:map-marker-outline"></iconify-icon><?php echo htmlspecialchars($location); ?></span>
            <?php endif; ?>
            <span class="chip"><iconify-icon icon="mdi:briefcase-outline"></iconify-icon><?php echo htmlspecialchars($type); ?></span>
            <?php if ($posted): ?>
              <span class="chip"><iconify-icon icon="mdi:calendar-check-outline"></iconify-icon>Posted: <?php echo htmlspecialchars($posted); ?></span>
            <?php endif; ?>
            <?php if ($deadline): ?>
              <span class="chip"><iconify-icon icon="mdi:calendar-end-outline"></iconify-icon>Deadline: <?php echo htmlspecialchars($deadline); ?></span>
            <?php endif; ?>
            <?php if ($salary): ?>
              <span class="chip"><iconify-icon icon="mdi:currency-usd"></iconify-icon>Salary: <?php echo htmlspecialchars($salary); ?></span>
            <?php endif; ?>
            <?php if ($assessment_required): ?>
              <span class="chip"><iconify-icon icon="mdi:clipboard-check-outline"></iconify-icon>Assessment required</span>
            <?php endif; ?>
          </div>

          <div class="desc-scroll">
            <h3>Description</h3>
            <p><?php echo $desc; ?></p>

            <?php if (!empty($req_ul)): ?>
              <h3>Requirements</h3>
              <ul><?php echo $req_ul; ?></ul>
            <?php endif; ?>
          </div>

          <div class="cta-row">
            <a class="btn-apply" href="<?php echo 'apply_job.php?jobId='.urlencode($jobId); ?>">
              <iconify-icon icon="mdi:send"></iconify-icon> Apply Now
            </a>
          </div>
        </div>
      </section>
    </main>
  </body>
</html>