<?php
// Start session and connect to DB
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('login.php');
}

// Get user info from session
$user_id   = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? 'applicant';
$full_name = $_SESSION['full_name'] ?? 'Your Name';

// Admin route
if ($user_type === 'admin') {
    redirect('admin_dashboard.php');
}

/* Ensure Users.profile_photo_url exists (best-effort) */
try {
  $q = $conn->prepare("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='Users' AND COLUMN_NAME='profile_photo_url'");
  $q->execute(); $row = $q->get_result()->fetch_assoc(); $q->close();
  if (empty($row['c'])) { $conn->query("ALTER TABLE Users ADD COLUMN profile_photo_url VARCHAR(255) NULL"); }
} catch (Throwable $e) { /* ignore */ }

/* Load current profile photo URL */
$profile_photo_url = null;
try {
  $stmt = $conn->prepare("SELECT profile_photo_url FROM Users WHERE user_id = ? LIMIT 1");
  $stmt->bind_param("s", $user_id);
  $stmt->execute();
  $u = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if ($u) {
    $profile_photo_url = $u['profile_photo_url'] ?: null;
  }
} catch (Throwable $e) {}

$avatarSrc = $profile_photo_url ?: './avatar_placeholder.jpg'; // Default to placeholder if not set

// ✅ Profile page by role (unchanged)
$profilePage = ($user_type === 'recruiter') ? 'recruiter_profile.php' : 'profile.php';

// Helper to trim description
function excerpt($text, $len = 180) {
    $text = strip_tags($text ?? '');
    if (mb_strlen($text) <= $len) return $text;
    return mb_substr($text, 0, $len - 1) . '…';
}

// ✅ Fetch latest jobs based on your schema (no is_featured/job_title/short_description)
function get_featured_jobs($conn, $limit = 10) {
  $sql = "SELECT 
  j.job_id,
  j.title,
  j.description,
  j.type,
  j.location,
  j.posted_at,
  u.full_name AS company_name,
  COALESCE(j.job_logo_url, r.company_logo_url) AS logo_url
FROM Jobs j
JOIN Recruiters r ON j.recruiter_id = r.recruiter_id
JOIN Users u      ON r.user_id = u.user_id
ORDER BY j.posted_at DESC
LIMIT ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

$featured_jobs = get_featured_jobs($conn, 10);

// Helper function to render job cards (keep layout)
function render_job_card($job) {
    $job_id   = htmlspecialchars($job['job_id']);
    $title    = htmlspecialchars($job['title']);
    $company  = htmlspecialchars($job['company_name']);
    $location = htmlspecialchars($job['location']);
    $type     = htmlspecialchars($job['type']);
    $desc     = htmlspecialchars(excerpt($job['description'], 200));
    $logo     = htmlspecialchars($job['logo_url'] ?: './avatar_placeholder.jpg');
    $posted   = !empty($job['posted_at']) ? date('M d, Y', strtotime($job['posted_at'])) : '';

    $details_link = "home_view_details.php?jobId=" . $job_id;

    // FIX: Removed 'Poster/Logo' text from output
    return "
      <article class='job-card' data-job-id='{$job_id}'>
        <div class='job-left'>
          <div class='poster placeholder'>
            <img src='{$logo}' alt='{$company} Logo' class='job-poster' onerror=\"this.src='./avatar_placeholder.jpg';\">
          </div>
        </div>
        <div class='job-right'>
          <h3 class='job-title'>{$title} — {$company}</h3>
          <div class='meta'>
            <span>Location: {$location}</span> ·
            <span>Type: {$type}</span>".
            (!empty($posted) ? " · <span>Posted: {$posted}</span>" : "") .
          "</div>
          <div class='short-desc'><p>{$desc}</p></div>

          <a class='view-details' href='{$details_link}' target='_blank' rel='noopener'>
            <iconify-icon icon='mdi:eye-outline'></iconify-icon> view details
          </a>
        </div>
      </article>
    ";
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>JobGate — Home</title>
    <link rel="stylesheet" href="home.css" />
    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>

    
  </head>
  <body>
    <header class="topbar">
      <div class="topbar-inner">
        <img src="./JobGate_logo.png" alt="JobGate" class="logo" />

        <nav class="top-actions" aria-label="Top actions">
          <a href="#" class="tlink">Home</a>
          <a href="<?php echo $profilePage; ?>" class="tlink">Profile</a>
          <img
            src="<?php echo htmlspecialchars($avatarSrc); ?>"
            class="avatar"
            alt="<?php echo htmlspecialchars($full_name); ?> avatar"
            title="<?php echo htmlspecialchars($full_name); ?>"
            onerror="this.src='./avatar_placeholder.jpg';"
          />
        </nav>
      </div>
    </header>

    <div class="layout">
      <aside class="sidebar">
        <button class="sbtn active">
          <iconify-icon icon="mdi:home" class="sib"></iconify-icon>Feed
        </button>

        <button class="sbtn" onclick="window.location.href='career_tips.php'">
          <iconify-icon icon="mdi:lightbulb-on-outline" class="sib"></iconify-icon>
          Career Tips
        </button>

        <button class="sbtn" onclick="window.location.href='job_events.php'">
          <iconify-icon icon="mdi:calendar-star" class="sib"></iconify-icon>
          Job Events
        </button>

        <button class="sbtn" onclick="window.location.href='courses.php'">
          <iconify-icon icon="mdi:book-open-variant" class="sib"></iconify-icon>
          Courses
        </button>

        <button class="sbtn" onclick="window.location.href='skill_assessment.php'">
          <iconify-icon icon="mdi:account-check-outline" class="sib"></iconify-icon>
          Skill Assessment
        </button>

        <button class="sbtn" onclick="window.location.href='jobs.php'">
          <iconify-icon icon="mdi:briefcase-outline" class="sib"></iconify-icon>
          Jobs
        </button>

        <div class="spacer"></div>

        <a class="sbtn logout" href="logout.php" style="text-decoration: none;">
          <iconify-icon icon="mdi:logout" class="sib"></iconify-icon>Log out
        </a>
      </aside>

      <main class="content">
        <h2 class="section-title">Featured Jobs (Latest <?php echo count($featured_jobs); ?>)</h2>

        <div class="featured-scroll">
          <?php if (!empty($featured_jobs)): ?>
              <?php foreach ($featured_jobs as $job): ?>
                  <?php echo render_job_card($job); ?>
              <?php endforeach; ?>
          <?php else: ?>
              <p style="padding:20px;text-align:center;background:#fff;border-radius:10px;margin-top:20px;box-shadow:0 4px 10px rgba(0,0,0,.05);">
                No jobs available right now. Check back later!
              </p>
          <?php endif; ?>
        </div>

      </main>
    </div>
  </body>
</html>