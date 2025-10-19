<?php
// course_note.php — show single course + basic tracking actions
session_start();
require_once 'db_connect.php';
if (!is_logged_in()) { redirect('login.php'); }

$user_id  = $_SESSION['user_id'] ?? '';
$courseId = $_GET['courseId'] ?? '';

if ($courseId === '') { redirect('courses.php'); }

/* load course */
$course = null;
try {
  $st = $conn->prepare("SELECT course_id, cat, cat_title, title, subtitle, blurb, banner_url, note_url, created_at
                        FROM Courses WHERE course_id = ? LIMIT 1");
  $st->bind_param("s", $courseId);
  $st->execute();
  $course = $st->get_result()->fetch_assoc();
  $st->close();
  if (!$course) { redirect('courses.php'); }
} catch (Throwable $e) { redirect('courses.php'); }

/* ensure a tracking row exists for this user+course (not_started) */
try {
  $st = $conn->prepare("INSERT IGNORE INTO CourseTracking (user_id, course_id, status, progress_percent, started_at)
                        VALUES (?, ?, 'not_started', 0, NOW())");
  $st->bind_param("ss", $user_id, $courseId);
  $st->execute();
  $st->close();
} catch (Throwable $e) {}

/* handle status updates */
$msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';
  try {
    if ($action === 'start') {
      $st = $conn->prepare("UPDATE CourseTracking SET status='in_progress', progress_percent=GREATEST(progress_percent,10), started_at=IFNULL(started_at,NOW()) WHERE user_id=? AND course_id=?");
      $st->bind_param("ss", $user_id, $courseId);
      $st->execute(); $st->close();
      $msg = 'Marked as In Progress';
    } else if ($action === 'complete') {
      $st = $conn->prepare("UPDATE CourseTracking SET status='completed', progress_percent=100 WHERE user_id=? AND course_id=?");
      $st->bind_param("ss", $user_id, $courseId);
      $st->execute(); $st->close();
      $msg = 'Marked as Completed';
    } else if ($action === 'set_progress') {
      $p = max(0, min(100, (int)($_POST['progress'] ?? 0)));
      $st = $conn->prepare("UPDATE CourseTracking SET status=IF(?=100,'completed','in_progress'), progress_percent=? WHERE user_id=? AND course_id=?");
      $st->bind_param("iiss", $p, $p, $user_id, $courseId);
      $st->execute(); $st->close();
      $msg = 'Progress updated';
    }
  } catch (Throwable $e) {
    $msg = 'Update failed: '.$e->getMessage();
  }
}

/* read tracking row */
$track = ['status'=>'not_started','progress_percent'=>0,'updated_at'=>null];
try {
  $st = $conn->prepare("SELECT status, progress_percent, updated_at FROM CourseTracking WHERE user_id=? AND course_id=? LIMIT 1");
  $st->bind_param("ss", $user_id, $courseId);
  $st->execute();
  $track = $st->get_result()->fetch_assoc() ?: $track;
  $st->close();
} catch (Throwable $e) {}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title><?php echo h($course['title']); ?> — Course Note</title>
  <link rel="stylesheet" href="courses.css"/>
  <style>
    .wrap{width:min(1100px,96%);margin:0 auto;padding:24px 28px 48px}
    .back{display:inline-grid;place-items:center;width:44px;height:44px;background:#e8efff;color:#1e40af;border-radius:10px;text-decoration:none;font-size:22px;margin-bottom:10px}
    .details{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:14px;box-shadow:0 16px 34px rgba(2,6,23,.08);padding:20px 22px}
    .head{display:flex;gap:16px;align-items:center}
    .thumb{width:120px;height:120px;border-radius:12px;background:#f1f5f9;object-fit:cover}
    .meta{display:flex;gap:14px;color:#475569;font-weight:800;margin:8px 0 12px}
    .msg{margin:10px 0;padding:10px 12px;border-radius:10px;background:#e0f2fe;color:#075985}
    .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:8px}
    .btn{border:0;border-radius:10px;padding:10px 14px;font-weight:900;cursor:pointer}
    .primary{background:#3b82f6;color:#fff}
    .ghost{background:#e5e7eb;color:#0f172a}
    input[type=number]{padding:8px 10px;border-radius:10px;border:1px solid #cbd5e1;width:90px}
  </style>
</head>
<body>
  <header class="topbar">
    <div class="topbar-inner">
      <img src="./JobGate_logo.png" alt="JobGate" class="logo"/>
      <nav class="top-actions"><a href="courses.php" class="tlink">Courses</a></nav>
    </div>
  </header>

  <main class="wrap">
    <a class="back" href="courses.php" onclick="if(history.length>1){history.back();return false;}">&#8592;</a>

    <section class="details">
      <?php if (!empty($msg)): ?><div class="msg"><?php echo h($msg); ?></div><?php endif; ?>

      <div class="head">
        <img class="thumb" src="<?php echo h($course['banner_url'] ?: './course_banner.jpg'); ?>" alt="" onerror="this.src='./course_banner.jpg'"/>
        <div>
          <h2 style="margin:0;font-size:24px;font-weight:900;"><?php echo h($course['title']); ?></h2>
          <div class="meta">
            <span><?php echo h($course['cat_title'] ?: $course['cat']); ?></span>
            <?php if(!empty($course['subtitle'])): ?><span>• <?php echo h($course['subtitle']); ?></span><?php endif; ?>
          </div>
          <div style="color:#0f172a;"><?php echo nl2br(h($course['blurb'] ?: '')); ?></div>
        </div>
      </div>

      <hr style="border:none;border-top:1px solid #e2e8f0;margin:16px 0">

      <div><strong>Status:</strong> <?php echo h($track['status']); ?>, <strong>Progress:</strong> <?php echo (int)$track['progress_percent']; ?>%</div>

      <form method="post" class="row">
        <button class="btn primary" name="action" value="start" type="submit">Mark In Progress</button>
        <button class="btn ghost"  name="action" value="complete" type="submit">Mark Completed</button>
        <span>Set progress</span>
        <input type="number" name="progress" min="0" max="100" value="<?php echo (int)$track['progress_percent']; ?>">
        <button class="btn ghost" name="action" value="set_progress" type="submit">Update</button>
      </form>
    </section>
  </main>
</body>
</html>
