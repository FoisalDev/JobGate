<?php
// Start session and connect to DB
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('login.php');
}

// Get user info from session
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$full_name = $_SESSION['full_name'];

// Define redirection based on user type
if ($user_type === 'admin') {
    // Admin needs a separate file
    redirect('admin_dashboard.php'); 
} 
// If Recruiter or Applicant, they see this feed for now.
$is_applicant = ($user_type === 'applicant');


// Function to fetch featured jobs from the database
function get_featured_jobs($conn) {
    // Select featured jobs, joining Recruiters (r) and their corresponding Users entry (c) to get company info.
    $sql = "SELECT 
                j.job_id, 
                j.job_title, 
                j.short_description, 
                j.job_type, 
                j.location,
                c.full_name AS company_name, 
                r.logo_url 
            FROM Jobs j
            JOIN Recruiters r ON j.recruiter_id = r.recruiter_id
            JOIN Users c ON r.user_id = c.user_id
            WHERE j.is_featured = 1
            ORDER BY j.posted_date DESC 
            LIMIT 5";
            
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    return [];
}

$featured_jobs = get_featured_jobs($conn);

// Helper function to render job cards
function render_job_card($job) {
    $job_id = htmlspecialchars($job['job_id']);
    $title = htmlspecialchars($job['job_title']);
    $company = htmlspecialchars($job['company_name']);
    $location = htmlspecialchars($job['location']);
    $type = htmlspecialchars($job['job_type']);
    $desc = htmlspecialchars($job['short_description']);
    $logo = htmlspecialchars($job['logo_url'] ?? './avatar_placeholder.jpg'); 

    // Details link points to job_details.php
    $details_link = "job_details.php?jobId=" . $job_id; 

    $output = "
        <article class='job-card' data-job-id='{$job_id}'>
            <div class='job-left'>
                <div class='poster placeholder'>
                    <img src='{$logo}' alt='{$company} Logo' style='width: 80%; height: auto; border-radius: 8px;' onerror=\"this.style.display='none';\">
                    <span style='color: #64748b; font-weight: 700;'>Poster/Logo</span>
                </div>
            </div>
            <div class='job-right'>
                <h3 class='job-title'>{$title} — {$company}</h3>
                <div class='meta'>
                    <span>Location: {$location}</span> ·
                    <span>Type: {$type}</span>
                </div>
                <div class='short-desc'><p>{$desc}</p></div>

                <a
                    class='view-details'
                    href='{$details_link}'
                    target='_blank'
                >
                    <iconify-icon icon='mdi:eye-outline'></iconify-icon> view details
                </a>
            </div>
        </article>
    ";
    return $output;
}

?>
<!DOCTYPE html>
<html lang="en"> <!-- Language changed to English -->
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>JobGate — Home</title>
    <link rel="stylesheet" href="home.css" />
    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
  </head>
  <body>
    <!-- Top bar -->
    <header class="topbar">
      <div class="topbar-inner">
        <img src="./JobGate_logo.png" alt="JobGate" class="logo" />

        <!-- Search bar REMOVED as requested -->

        <nav class="top-actions" aria-label="Top actions">
          <a href="#" class="tlink">Home</a>
          <a href="profile.php" class="tlink">Profile</a>
          <img
            src="./avatar_placeholder.jpg"
            class="avatar"
            alt="<?php echo htmlspecialchars($full_name); ?> avatar"
            title="<?php echo htmlspecialchars($full_name); ?>"
          />
        </nav>
      </div>
    </header>

    <div class="layout">
      <!-- Sidebar -->
      <aside class="sidebar">
        <button class="sbtn active">
          <iconify-icon icon="mdi:home" class="sib"></iconify-icon>Feed
        </button>

        <button class="sbtn" onclick="window.location.href='career_tips.php'">
          <iconify-icon
            icon="mdi:lightbulb-on-outline"
            class="sib"
          ></iconify-icon>
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

        <button
          class="sbtn"
          onclick="window.location.href='skill_assessment.php'"
        >
          <iconify-icon
            icon="mdi:account-check-outline"
            class="sib"
          ></iconify-icon>
          Skill Assessment
        </button>

        <button class="sbtn" onclick="window.location.href='jobs.php'">
          <iconify-icon icon="mdi:briefcase-outline" class="sib"></iconify-icon>
          Jobs
        </button>

        <div class="spacer"></div>

        <!-- FIX: Changed the button to an anchor tag to ensure proper navigation -->
        <a class="sbtn logout" href="logout.php" style="text-decoration: none;">
          <iconify-icon icon="mdi:logout" class="sib"></iconify-icon>Log out
        </a>
      </aside>

      <!-- Main feed -->
      <main class="content">
        <h2 class="section-title">Featured Jobs (Top <?php echo count($featured_jobs); ?>)</h2>

        <!-- Dynamic Job Cards -->
        <?php if (!empty($featured_jobs)): ?>
            <?php foreach ($featured_jobs as $job): ?>
                <?php echo render_job_card($job); ?>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="padding: 20px; text-align: center; background: #fff; border-radius: 10px; margin-top: 20px; box-shadow: 0 4px 10px rgba(0,0,0,.05);">No featured jobs available right now. Check back later!</p>
        <?php endif; ?>

      </main>
    </div>
  </body>
</html>
