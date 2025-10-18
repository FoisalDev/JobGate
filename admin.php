<?php
// admin.php — Admin dashboard (post Job Events + manage users)
session_start();
require_once 'db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* Guards */
if (!is_logged_in()) { redirect('login.php'); exit; }
$user_id   = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? '';
$full_name = $_SESSION['full_name'] ?? 'Admin';
if ($user_type !== 'admin') { redirect('home.php'); exit; }

/* Helpers */
function sanitize($v){ return trim(filter_var($v, FILTER_SANITIZE_FULL_SPECIAL_CHARS)); }
function guid(){
  $d = random_bytes(16);
  $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
  $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

/* Ensure JobEvents exists (safe) */
try {
  $conn->query("
    CREATE TABLE IF NOT EXISTS JobEvents (
      event_id VARCHAR(36) PRIMARY KEY,
      title VARCHAR(200) NOT NULL,
      description TEXT NOT NULL,
      organizer VARCHAR(150) NULL,
      start_date DATE NOT NULL,
      end_date DATE NULL,
      image_url VARCHAR(255) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
} catch (Throwable $e) {}

/* Avatar (optional) */
$avatarSrc = './avatar_placeholder.jpg';
try {
  $stp = $conn->prepare("SELECT profile_photo_url FROM Users WHERE user_id = ? LIMIT 1");
  $stp->bind_param("s", $user_id);
  $stp->execute();
  $r = $stp->get_result()->fetch_assoc();
  $stp->close();
  if (!empty($r['profile_photo_url'])) $avatarSrc = $r['profile_photo_url'];
} catch (Throwable $e) {}

$msg = ''; $msg_type = ''; // success | error

/* Add Event */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_event') {
  try {
    $title      = sanitize($_POST['title'] ?? '');
    $organizer  = sanitize($_POST['organizer'] ?? '');
    $start_date = sanitize($_POST['start_date'] ?? '');
    $end_date   = sanitize($_POST['end_date'] ?? '');
    $desc       = trim($_POST['description'] ?? '');

    if ($title === '' || $start_date === '' || $desc === '') {
      throw new Exception("Title, Start Date এবং Description আবশ্যক।");
    }

    // image (optional)
    $image_url = null;
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
      $dirFs  = __DIR__ . '/uploads/event_images/';
      $dirWeb = 'uploads/event_images/';
      if (!is_dir($dirFs)) mkdir($dirFs, 0777, true);

      $info = @getimagesize($_FILES['image']['tmp_name']);
      if ($info === false) throw new Exception("ভুল ইমেজ ফাইল।");
      $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) throw new Exception("শুধু JPG, JPEG, PNG, GIF, WEBP দেওয়া যাবে।");
      if ($_FILES['image']['size'] > 3*1024*1024) throw new Exception("সর্বোচ্চ 3MB ইমেজ আপলোড করা যাবে।");

      $fname = guid().'.'.$ext;
      if (!move_uploaded_file($_FILES['image']['tmp_name'], $dirFs.$fname)) {
        throw new Exception("ইমেজ সেভ করা যায়নি (uploads/event_images/ পারমিশন চেক করুন)।");
      }
      $image_url = $dirWeb.$fname;
    }

    $event_id = guid();
    $stmt = $conn->prepare("INSERT INTO JobEvents (event_id, title, description, organizer, start_date, end_date, image_url) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("sssssss", $event_id, $title, $desc, $organizer, $start_date, $end_date, $image_url);
    $stmt->execute(); $stmt->close();

    header("Location: admin.php?ok=1#events"); exit;

  } catch (Throwable $e) {
    $msg = "Error posting event: ".$e->getMessage();
    $msg_type = 'error';
  }
}

/* Edit Event */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_event') {
  try {
    $eid        = sanitize($_POST['event_id'] ?? '');
    $title      = sanitize($_POST['title'] ?? '');
    $organizer  = sanitize($_POST['organizer'] ?? '');
    $start_date = sanitize($_POST['start_date'] ?? '');
    $end_date   = sanitize($_POST['end_date'] ?? '');
    $desc       = trim($_POST['description'] ?? '');

    if ($eid === '') throw new Exception("Missing event id.");
    if ($title === '' || $start_date === '' || $desc === '') {
      throw new Exception("Title, Start Date এবং Description আবশ্যক।");
    }

    // current image
    $st = $conn->prepare("SELECT image_url FROM JobEvents WHERE event_id = ? LIMIT 1");
    $st->bind_param("s", $eid);
    $st->execute(); $current = $st->get_result()->fetch_assoc(); $st->close();
    $image_url = $current['image_url'] ?? null;

    // new image (optional)
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
      $dirFs  = __DIR__ . '/uploads/event_images/';
      $dirWeb = 'uploads/event_images/';
      if (!is_dir($dirFs)) mkdir($dirFs, 0777, true);

      $info = @getimagesize($_FILES['image']['tmp_name']);
      if ($info === false) throw new Exception("ভুল ইমেজ ফাইল।");
      $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) throw new Exception("শুধু JPG, JPEG, PNG, GIF, WEBP দেওয়া যাবে।");
      if ($_FILES['image']['size'] > 3*1024*1024) throw new Exception("সর্বোচ্চ 3MB ইমেজ আপলোড করা যাবে।");

      $fname = guid().'.'.$ext;
      if (!move_uploaded_file($_FILES['image']['tmp_name'], $dirFs.$fname)) {
        throw new Exception("ইমেজ সেভ করা যায়নি (uploads/event_images/ পারমিশন চেক করুন)।");
      }
      $image_url = $dirWeb.$fname;
    }

    $stmt = $conn->prepare("UPDATE JobEvents SET title=?, description=?, organizer=?, start_date=?, end_date=?, image_url=? WHERE event_id=?");
    $stmt->bind_param("sssssss", $title, $desc, $organizer, $start_date, $end_date, $image_url, $eid);
    $stmt->execute(); $stmt->close();

    header("Location: admin.php?ok=1#events"); exit;

  } catch (Throwable $e) {
    $msg = "Update failed: ".$e->getMessage();
    $msg_type = 'error';
  }
}

/* Delete User */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
  try {
    $del_id = sanitize($_POST['user_id'] ?? '');
    if ($del_id === '') throw new Exception("Missing user id.");
    if ($del_id === $user_id) throw new Exception("নিজেকে ডিলিট করা যাবে না।");

    $st = $conn->prepare("DELETE FROM Users WHERE user_id = ?");
    $st->bind_param("s", $del_id);
    $st->execute(); $st->close();

    header("Location: admin.php?ok=1#users"); exit;
  } catch (Throwable $e) {
    $msg = "Delete failed: ".$e->getMessage();
    $msg_type = 'error';
  }
}

/* Delete Event */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_event') {
  try {
    $eid = sanitize($_POST['event_id'] ?? '');
    if ($eid === '') throw new Exception("Missing event id.");
    $st = $conn->prepare("DELETE FROM JobEvents WHERE event_id = ?");
    $st->bind_param("s", $eid);
    $st->execute(); $st->close();

    header("Location: admin.php?ok=1#events"); exit;
  } catch (Throwable $e) {
    $msg = "Delete failed: ".$e->getMessage();
    $msg_type = 'error';
  }
}

/* Fetch Users */
$users = [];
try {
  $q = $conn->query("SELECT user_id, full_name, email, user_type, created_at FROM Users ORDER BY created_at DESC");
  while ($row = $q->fetch_assoc()) $users[] = $row;
  $q->close();
} catch (Throwable $e) {}

/* Fetch Events */
$events = [];
try {
  $q = $conn->query("
    SELECT event_id, title, organizer, start_date, end_date, image_url, description
    FROM JobEvents
    ORDER BY start_date DESC, COALESCE(end_date, start_date) DESC, event_id DESC
  ");
  while ($row = $q->fetch_assoc()) $events[] = $row;
  $q->close();
} catch (Throwable $e) {}

$profilePage = 'profile.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>JobGate — Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="admin.css" />
  <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>

  <style>
    /* Base / Topbar / Sidebar — structure same */
    *{box-sizing:border-box}
    body{margin:0;background:#f8fafc;color:#0f172a;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial}
    img{max-width:100%;display:block}
    .hidden{display:none !important;}

    .topbar{position:sticky;top:0;z-index:20;background:#fff;border-bottom:1px solid #e5e7eb}
    .topbar-inner{display:flex;align-items:center;gap:30px;height:86px;width:min(1380px,96%);margin:0 auto;padding:0 12px}
    .logo{height:clamp(80px,10vw,120px);width:auto;object-fit:contain}
    .top-actions{display:flex;align-items:center;gap:18px;margin-left:auto}
    .tlink{color:#0f172a;text-decoration:none;font-weight:800}
    .avatar{width:40px;height:40px;border-radius:999px;object-fit:cover;box-shadow:0 1px 6px rgba(0,0,0,.15)}

    .layout{display:grid;grid-template-columns:260px 1fr;min-height:calc(100vh - 86px)}
    .sidebar{background:#0b1d3a;color:#e2e8f0;padding:12px 14px;position:sticky;top:86px;z-index:10;display:flex;flex-direction:column;height:calc(100vh - 86px)}
    .sbtn{width:100%;display:flex;align-items:center;gap:14px;background:transparent;color:#e2e8f0;border:0;text-align:left;padding:14px 12px;border-radius:12px;cursor:pointer;font-weight:800;margin-bottom:6px;font-size:18px}
    .sbtn:hover{background:rgba(255,255,255,.06)}
    .sbtn.active{background:rgba(255,255,255,.08)}
    .spacer{flex:1}
    .logout{color:#fca5a5;margin-top:auto}
    .logout:hover{background:rgba(252,165,165,.12)}

    .content{padding:24px 28px 48px;background:#f8fafc}

    /* Cards / form */
    .card{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:12px;box-shadow:0 14px 32px rgba(2,6,23,.06);padding:16px;margin-bottom:16px}
    .section-title{margin:0 0 12px 0;font-size:22px;font-weight:900}

    .row2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .form-group{display:flex;flex-direction:column;margin-bottom:10px}
    .form-group label{font-weight:800;margin-bottom:6px}
    .form-group input,.form-group textarea{border:1px solid #e2e8f0;border-radius:10px;padding:10px;font-size:14px}
    textarea{min-height:120px;resize:vertical}

    .btn{padding:10px 14px;border-radius:10px;font-weight:800;border:0;cursor:pointer}
    .btn-primary{background:#3b82f6;color:#fff}
    .btn-danger{background:#ef4444;color:#fff}
    .btn-ghost{background:#f8fafc;border:1px solid #e2e8f0}

    /* Users table: scrollable container */
    .table-scroll{max-height:420px; overflow:auto; border:1px solid #e2e8f0; border-radius:12px; padding:6px;}
    .table-scroll::-webkit-scrollbar{width:8px;height:8px}
    .table-scroll::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:8px}

    table{width:100%;border-collapse:separate;border-spacing:0 8px}
    th,td{text-align:left;padding:10px}
    thead th{position:sticky;top:0;background:#fff;z-index:1;font-weight:900;color:#334155;border-bottom:1px solid #e2e8f0}
    tbody tr{background:#fff;border:1px solid #e2e8f0;border-radius:10px}
    tbody tr td:first-child{border-top-left-radius:10px;border-bottom-left-radius:10px}
    tbody tr td:last-child{border-top-right-radius:10px;border-bottom-right-radius:10px}

    /* Event cards */
    .events-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px}
    .event-item{background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden}
    .event-img{width:100%;height:160px;object-fit:cover;display:block;background:#f1f5f9}
    .event-body{padding:12px}
    .muted{color:#64748b;font-size:13px}

    /* Edit area scrollable */
    .edit-wrap{margin-top:8px;border-top:1px dashed #e5e7eb;padding-top:8px; max-height:340px; overflow:auto; border-radius:8px; background:#f8fafc}
    .edit-wrap::-webkit-scrollbar{width:8px}
    .edit-wrap::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:8px}

    @media (max-width:900px){
      .row2{grid-template-columns:1fr}
      .layout{grid-template-columns:1fr}
      .top-actions{display:none}
    }

    .alert{padding:10px 12px;border-radius:10px;margin-bottom:12px}
    .alert-success{background:#dcfce7;color:#14532d;border:1px solid #bbf7d0}
    .alert-error{background:#fee2e2;color:#7f1d1d;border:1px solid #fecaca}
  </style>
</head>
<body>
  <!-- Topbar -->
  <header class="topbar">
    <div class="topbar-inner">
      <img src="./JobGate_logo.png" alt="JobGate" class="logo" />
      <nav class="top-actions" aria-label="Top actions"></nav>
    </div>
  </header>

  <div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <button class="sbtn active" onclick="location.href='admin.php'">
        <iconify-icon icon="mdi:view-dashboard"></iconify-icon>Admin Dashboard
      </button>
      <button class="sbtn" onclick="document.getElementById('postEvent').scrollIntoView({behavior:'smooth'})">
        <iconify-icon icon="mdi:calendar-plus"></iconify-icon>Post Job Event
      </button>
      <button class="sbtn" onclick="document.getElementById('users').scrollIntoView({behavior:'smooth'})">
        <iconify-icon icon="mdi:account-multiple"></iconify-icon>Manage Users
      </button>
      <button class="sbtn" onclick="document.getElementById('events').scrollIntoView({behavior:'smooth'})">
        <iconify-icon icon="mdi:calendar-search"></iconify-icon>Manage Events
      </button>
      <div class="spacer"></div>
      <button class="sbtn logout" onclick="location.href='logout.php'">
        <iconify-icon icon="mdi:logout"></iconify-icon>Log out
      </button>
    </aside>

    <!-- Main -->
    <main class="content">
      <?php if (!empty($msg)): ?>
        <div class="alert <?php echo ($msg_type==='success'?'alert-success':'alert-error'); ?>">
          <?php echo htmlspecialchars($msg); ?>
        </div>
      <?php endif; ?>

      <!-- Post Job Event -->
      <section id="postEvent" class="card">
        <h2 class="section-title">Post a Job Event</h2>
        <form method="POST" action="admin.php#events" enctype="multipart/form-data">
          <input type="hidden" name="action" value="add_event" />
          <div class="row2">
            <div class="form-group">
              <label for="title">Event Title *</label>
              <input type="text" id="title" name="title" required />
            </div>
            <div class="form-group">
              <label for="organizer">Organizer</label>
              <input type="text" id="organizer" name="organizer" />
            </div>
          </div>

          <div class="row2">
            <div class="form-group">
              <label for="start_date">Start Date *</label>
              <input type="date" id="start_date" name="start_date" required />
            </div>
            <div class="form-group">
              <label for="end_date">End Date</label>
              <input type="date" id="end_date" name="end_date" />
            </div>
          </div>

          <div class="form-group">
            <label for="description">Description *</label>
            <textarea id="description" name="description" required></textarea>
          </div>

          <div class="form-group">
            <label for="image">Poster / Banner (optional, ≤ 3MB)</label>
            <input type="file" id="image" name="image" accept="image/*" />
            <div class="muted">Saved under uploads/event_images/</div>
          </div>

          <button class="btn btn-primary" type="submit">
            <iconify-icon icon="mdi:send"></iconify-icon> Publish Event
          </button>
        </form>
      </section>

      <!-- Users (scrollable) -->
      <section id="users" class="card">
        <h2 class="section-title">Users</h2>
        <?php if (empty($users)): ?>
          <p class="muted">No users found.</p>
        <?php else: ?>
          <div class="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Full Name</th>
                  <th>Email</th>
                  <th>Type</th>
                  <th>Joined</th>
                  <th style="width:130px;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($users as $u): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($u['full_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($u['email'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($u['user_type'] ?? '—'); ?></td>
                    <td><?php echo !empty($u['created_at']) ? date('M d, Y', strtotime($u['created_at'])) : '—'; ?></td>
                    <td>
                      <form method="POST" action="admin.php#users" onsubmit="return confirm('Delete this user? This cannot be undone.');" style="display:inline;">
                        <input type="hidden" name="action" value="delete_user" />
                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($u['user_id']); ?>" />
                        <button type="submit" class="btn btn-danger">
                          <iconify-icon icon="mdi:delete"></iconify-icon> Delete
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <!-- Events -->
      <section id="events" class="card">
        <h2 class="section-title">Events</h2>
        <?php if (empty($events)): ?>
          <p class="muted">No events posted yet.</p>
        <?php else: ?>
          <div class="events-list">
            <?php foreach ($events as $ev): $eid = htmlspecialchars($ev['event_id']); ?>
              <article class="event-item" id="card-<?php echo $eid; ?>">
                <?php if (!empty($ev['image_url'])): ?>
                  <img class="event-img" src="<?php echo htmlspecialchars($ev['image_url']); ?>" alt="Event Image" onerror="this.style.display='none';" />
                <?php else: ?>
                  <div class="event-img" style="display:grid;place-items:center;color:#64748b">No Image</div>
                <?php endif; ?>
                <div class="event-body">
                  <h3 style="margin:0 0 6px;"><?php echo htmlspecialchars($ev['title']); ?></h3>
                  <div class="muted" style="margin-bottom:6px;">
                    <?php if (!empty($ev['organizer'])): ?>
                      Organizer: <?php echo htmlspecialchars($ev['organizer']); ?> ·
                    <?php endif; ?>
                    <?php
                      $sd = !empty($ev['start_date']) ? date('M d, Y', strtotime($ev['start_date'])) : '';
                      $ed = !empty($ev['end_date']) ? date('M d, Y', strtotime($ev['end_date'])) : '';
                      echo $sd ? "From {$sd}" : "";
                      echo ($sd && $ed) ? " to {$ed}" : ($ed ? "Until {$ed}" : "");
                    ?>
                  </div>

                  <!-- Edit toggle (only open on click; close others) -->
                  <button class="btn btn-ghost" type="button" onclick="toggleEdit('edit-<?php echo $eid; ?>')">
                    <iconify-icon icon="mdi:pencil-outline"></iconify-icon> Edit
                  </button>

                  <!-- Delete -->
                  <form method="POST" action="admin.php#events" onsubmit="return confirm('Delete this event?');" style="display:inline;">
                    <input type="hidden" name="action" value="delete_event" />
                    <input type="hidden" name="event_id" value="<?php echo $eid; ?>" />
                    <button type="submit" class="btn btn-ghost">
                      <iconify-icon icon="mdi:delete-outline"></iconify-icon> Delete
                    </button>
                  </form>

                  <!-- Inline edit form (scrollable) -->
                  <div id="edit-<?php echo $eid; ?>" class="edit-wrap hidden">
                    <form method="POST" action="admin.php#events" enctype="multipart/form-data">
                      <input type="hidden" name="action" value="edit_event" />
                      <input type="hidden" name="event_id" value="<?php echo $eid; ?>" />
                      <div class="form-group">
                        <label>Title *</label>
                        <input type="text" name="title" required value="<?php echo htmlspecialchars($ev['title']); ?>" />
                      </div>
                      <div class="row2">
                        <div class="form-group">
                          <label>Organizer</label>
                          <input type="text" name="organizer" value="<?php echo htmlspecialchars($ev['organizer'] ?? ''); ?>" />
                        </div>
                        <div class="form-group">
                          <label>Start Date *</label>
                          <input type="date" name="start_date" required value="<?php echo htmlspecialchars($ev['start_date']); ?>" />
                        </div>
                      </div>
                      <div class="row2">
                        <div class="form-group">
                          <label>End Date</label>
                          <input type="date" name="end_date" value="<?php echo htmlspecialchars($ev['end_date'] ?? ''); ?>" />
                        </div>
                        <div class="form-group">
                          <label>Change Poster (optional)</label>
                          <input type="file" name="image" accept="image/*" />
                        </div>
                      </div>
                      <div class="form-group">
                        <label>Description *</label>
                        <textarea name="description" required><?php echo htmlspecialchars($ev['description']); ?></textarea>
                      </div>
                      <button class="btn btn-primary" type="submit">
                        <iconify-icon icon="mdi:content-save-outline"></iconify-icon> Save
                      </button>
                      <button class="btn btn-ghost" type="button" onclick="toggleEdit('edit-<?php echo $eid; ?>', true)">Cancel</button>
                    </form>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>

  <script>
    // একসাথে একটাই এডিট ফর্ম খোলা থাকবে; খুললে সেটার দিকে স্ক্রল করবে
    function toggleEdit(id, closeOnly){
      // close all others
      document.querySelectorAll('.edit-wrap').forEach(el => el.classList.add('hidden'));
      const el = document.getElementById(id);
      if (!el) return;
      if (closeOnly) { el.classList.add('hidden'); return; }
      el.classList.remove('hidden');
      // scroll the card into view nicely
      el.closest('.event-item').scrollIntoView({behavior: 'smooth', block: 'center'});
    }
    // default: সব এডিট ফর্ম হাইড
    document.querySelectorAll('.edit-wrap').forEach(el => el.classList.add('hidden'));
  </script>
</body>
</html>
