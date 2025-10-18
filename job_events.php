<?php
session_start();
require_once 'db_connect.php';

// Redirect if not logged in
if (!is_logged_in()) {
    redirect('login.php');
}

$user_id   = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? 'applicant';
$full_name = $_SESSION['full_name'] ?? 'Your Name';
$profilePage = ($user_type === 'recruiter') ? 'recruiter_profile.php' : 'profile.php';

// Load avatar
$profile_photo_url = null;
try {
  $stmt = $conn->prepare("SELECT profile_photo_url FROM Users WHERE user_id = ? LIMIT 1");
  $stmt->bind_param("s", $user_id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if ($row && !empty($row['profile_photo_url'])) {
      $profile_photo_url = $row['profile_photo_url'];
  }
} catch (Throwable $e) { }
$avatarSrc = $profile_photo_url ?: './avatar_placeholder.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>JobGate — Job Events</title>
  <link rel="stylesheet" href="job_events.css" />
  <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
</head>
<body>
  <!-- ✅ Navbar identical to home.php/profile.php -->
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
    <!-- ✅ Sidebar identical to other pages -->
    <aside class="sidebar">
      <button class="sbtn" onclick="window.location.href='home.php'">
        <iconify-icon icon="mdi:home" class="sib"></iconify-icon>Feed
      </button>

      <button class="sbtn" onclick="window.location.href='career_tips.php'">
        <iconify-icon icon="mdi:lightbulb-on-outline" class="sib"></iconify-icon>
        Career Tips
      </button>

      <button class="sbtn active" onclick="window.location.href='job_events.php'">
        <iconify-icon icon="mdi:calendar-star" class="sib"></iconify-icon>Job Events
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

    <!-- ✅ Main content -->
    <main class="content">
      <header class="events-header">
        <h1 class="page-title">Events</h1>
        <div class="controls-wrapper">
          <button class="btn btn-primary">
            <iconify-icon icon="mdi:check-decagram-outline"></iconify-icon>
            Check Event
          </button>
          <div class="filter-group">
            <button class="filter-btn">All</button>
            <button class="filter-btn active">Job Fair</button>
            <button class="filter-btn">Internship Fair</button>
          </div>
        </div>
      </header>

      <div class="events-grid">
        <article class="event-card">
          <div class="event-body text-only">
            <h3 class="event-title">
              Yakima Development Association Job Fair 2025
            </h3>
            <p class="event-meta">
              <strong>Event Title:</strong> Development Association Job Fair 2025<br />
              <strong>Date:</strong> July 1–3, 2025<br />
              <strong>Organizer:</strong> Yakima Country<br />
              <strong>Description:</strong> Development Association Job Fair 2025 brings together top
              companies, recruiters, and career coaches under one roof. This
              three-day event is your opportunity to meet industry leaders,
              interview on the spot, and receive personalized career
              development advice.
            </p>
            <a href="#" class="view-details">View Details</a>
          </div>
        </article>

        <article class="event-card">
          <img
            src="https://i.imgur.com/uU59s6S.png"
            alt="Coders Combat 4.0"
            class="event-image"
          />
          <div class="event-body">
            <h3 class="event-title">UIU CODERS COMBAT 4.0</h3>
            <p class="event-desc">
              Remember how we mentioned about Coders Combat happening every
              time we stepped into your DMs? Guess what! It's finally here.
              And this time — it's bigger, better, and more exciting than ever
              before! Teaming up with Shikhbe Shobai as our Technology &
              Knowledge partner, UIU Computer Club brings Coders Combat 4.0,
              an event to challenge your problem-solving skills, apply your
              love for coding, and prepare for ICPC and the upcoming NCPC.
            </p>
            <a href="#" class="view-details">View Details</a>
          </div>
        </article>

        <article class="event-card">
          <img
            src="https://i.imgur.com/gK52BLe.png"
            alt="IEEE PES Day"
            class="event-image"
          />
          <div class="event-body">
            <h3 class="event-title">IEEE PES DAY 2025</h3>
            <p class="event-desc">
              Join us for IEEE PES Day 2025 at UIU! The IEEE UIU Student
              Branch is proud to host a celebration of innovation,
              sustainability, and the future of energy.<br />
              <strong>Theme:</strong> "Clean Energy, Smarter Grids, Better Lives"
            </p>
            <a href="#" class="view-details">View Details</a>
          </div>
        </article>

        <article class="event-card">
          <img
            src="https://i.imgur.com/tYt3LpS.png"
            alt="Online Job Fair"
            class="event-image"
          />
          <div class="event-body">
            <h3 class="event-title">Campo Wave 2025</h3>
            <p class="event-desc">
              We are offering an exciting Job Fair to be held on December 10–20,
              bringing together top companies and talented job seekers. This
              event features on-spot interviews, career counseling, and
              networking opportunities for all participants.
            </p>
            <a href="#" class="view-details">View Details</a>
          </div>
        </article>
      </div>
    </main>
  </div>
</body>
</html>
