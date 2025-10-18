<?php
session_start();
require_once 'db_connect.php';
if (!is_logged_in()) { redirect('login.php'); }

$eventId = $_GET['eventId'] ?? '';
if ($eventId === '') { redirect('job_events.php'); }

$event = null;
try {
  $st = $conn->prepare("SELECT event_id, title, description, organizer, start_date, end_date, image_url FROM JobEvents WHERE event_id = ? LIMIT 1");
  $st->bind_param("s", $eventId);
  $st->execute();
  $event = $st->get_result()->fetch_assoc();
  $st->close();
  if (!$event) redirect('job_events.php');
} catch (Throwable $e) { redirect('job_events.php'); }

/* Simple formatting */
$sd = !empty($event['start_date']) ? date('M d, Y', strtotime($event['start_date'])) : '';
$ed = !empty($event['end_date'])   ? date('M d, Y', strtotime($event['end_date']))   : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo htmlspecialchars($event['title']); ?> — Job Event</title>
  <link rel="stylesheet" href="job_events.css" />
</head>
<body>
  <header class="topbar">
    <div class="topbar-inner">
      <img src="./JobGate_logo.png" alt="JobGate" class="logo" />
      <nav class="top-actions"><a href="job_events.php" class="tlink">Back to Events</a></nav>
    </div>
  </header>

  <div class="layout">
    <aside class="sidebar">
      <button class="sbtn" onclick="window.location.href='job_events.php'">
        <iconify-icon icon="mdi:calendar-star" class="sib"></iconify-icon>Job Events
      </button>
      <div class="spacer"></div>
      <a class="sbtn logout" href="logout.php" style="text-decoration:none;">
        <iconify-icon icon="mdi:logout" class="sib"></iconify-icon>Log out
      </a>
    </aside>

    <main class="content">
      <article class="event-card" style="grid-template-columns:340px 1fr;">
        <div class="poster">
          <?php if (!empty($event['image_url'])): ?>
            <img src="<?php echo htmlspecialchars($event['image_url']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>">
          <?php else: ?>
            <div class="poster-empty">No image</div>
          <?php endif; ?>
        </div>
        <div class="event-info">
          <h1 class="event-title" style="font-size:26px;"><?php echo htmlspecialchars($event['title']); ?></h1>
          <div class="event-meta">
            <?php if ($sd): ?><span>Date: <?php echo $sd; ?><?php echo $ed ? ' — '.$ed : ''; ?></span><?php endif; ?>
            <?php if (!empty($event['organizer'])): ?> · <span>Organizer: <?php echo htmlspecialchars($event['organizer']); ?></span><?php endif; ?>
          </div>
          <div style="white-space:pre-line"><?php echo nl2br(htmlspecialchars($event['description'] ?? '')); ?></div>
        </div>
      </article>
    </main>
  </div>
</body>
</html>
