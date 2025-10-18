<?php
session_start();
require_once 'db_connect.php';

if (!is_logged_in()) { redirect('login.php'); }

$user_id   = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? 'applicant';
$full_name = $_SESSION['full_name'] ?? 'Your Name';
$profilePage = ($user_type === 'recruiter') ? 'recruiter_profile.php' : 'profile.php';

/* Avatar */
$avatarSrc = './avatar_placeholder.jpg';
try {
  $stmt = $conn->prepare("SELECT profile_photo_url FROM Users WHERE user_id = ? LIMIT 1");
  $stmt->bind_param("s", $user_id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!empty($row['profile_photo_url'])) $avatarSrc = $row['profile_photo_url'];
} catch (Throwable $e) {}

/* Helper: excerpt */
function excerpt($text, $len = 180){
  $text = strip_tags($text ?? '');
  if (mb_strlen($text) <= $len) return $text;
  return mb_substr($text, 0, $len-1).'…';
}

/* Fetch events (no created_at dependency) */
$events = [];
$events_error = '';
try {
  $sql = "
    SELECT event_id, title, description, organizer, start_date, end_date, image_url
    FROM JobEvents
    ORDER BY 
      CASE WHEN start_date IS NULL THEN 1 ELSE 0 END,
      start_date DESC,
      event_id DESC
  ";
  $q = $conn->query($sql);
  while ($r = $q->fetch_assoc()) $events[] = $r;
  $q->close();
} catch (Throwable $e) {
  $events_error = 'DB error: '.$e->getMessage();
}
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
  <!-- Topbar (same as অন্যান্য পেজ) -->
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
    <!-- Sidebar (unchanged) -->
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

    <!-- Main -->
    <main class="content">
      <header class="events-header">
        <h1 class="page-title">Latest Events</h1>
      </header>

      <?php if ($events_error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($events_error); ?></div>
      <?php endif; ?>

      <?php if (empty($events)): ?>
        <p class="muted">No events posted yet.</p>
      <?php else: ?>
        <section class="events-grid">
          <?php foreach ($events as $ev): 
            $eid   = htmlspecialchars($ev['event_id']);
            $title = htmlspecialchars($ev['title']);
            $org   = htmlspecialchars($ev['organizer'] ?? '');
            $img   = htmlspecialchars($ev['image_url'] ?: '');
            $sd    = !empty($ev['start_date']) ? date('M d, Y', strtotime($ev['start_date'])) : '';
            $ed    = !empty($ev['end_date'])   ? date('M d, Y', strtotime($ev['end_date']))   : '';
            $desc  = htmlspecialchars(excerpt($ev['description'] ?? '', 220));
          ?>
          <article class="event-card">
            <div class="poster">
              <?php if ($img): ?>
                <img src="<?php echo $img; ?>" alt="<?php echo $title; ?>" onerror="this.style.display='none';" />
              <?php else: ?>
                <div class="poster-empty">No image</div>
              <?php endif; ?>
            </div>
            <div class="event-info">
              <h3 class="event-title"><?php echo $title; ?></h3>
              <div class="event-meta">
                <?php if ($sd): ?><span>Date: <?php echo $sd; ?><?php echo $ed ? ' — '.$ed : ''; ?></span><?php endif; ?>
                <?php if ($org): ?> · <span>Organizer: <?php echo $org; ?></span><?php endif; ?>
              </div>
              <p class="event-desc"><?php echo $desc; ?></p>
              <a class="btn view-btn" href="job_event_details.php?eventId=<?php echo $eid; ?>">
                <iconify-icon icon="mdi:eye-outline"></iconify-icon> View
              </a>
            </div>
          </article>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
