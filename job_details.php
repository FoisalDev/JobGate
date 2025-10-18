<?php
session_start();
require_once 'db_connect.php';
if (!is_logged_in()) { redirect('login.php'); }

$jobId = $_GET['jobId'] ?? '';
if ($jobId === '') { redirect('jobs.php'); }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---- schema helpers
function tableExists(mysqli $c, $t){
  $t = $c->real_escape_string($t);
  $q = $c->query("SHOW TABLES LIKE '{$t}'");
  return $q && $q->num_rows > 0;
}
function colExists(mysqli $c, $t, $col){
  $t = $c->real_escape_string($t);
  $col = $c->real_escape_string($col);
  $q = $c->query("SHOW COLUMNS FROM `{$t}` LIKE '{$col}'");
  return $q && $q->num_rows > 0;
}

$job = null; $error = '';

try {
  // what do we have in this DB?
  $hasJobs       = tableExists($conn, 'Jobs');
  if (!$hasJobs) throw new Exception("Jobs table not found.");

  $hasRecruiters = tableExists($conn, 'Recruiters');
  $hasUsers      = tableExists($conn, 'Users');

  // columns in Jobs
  $colsInJobs = [];
  foreach (['title','description','type','location','salary','job_logo_url','application_deadline','posted_at','recruiter_id'] as $cname){
    if (colExists($conn,'Jobs',$cname)) $colsInJobs[$cname] = true;
  }

  // can we join to users for company name?
  $canJoinCompany = $hasRecruiters && $hasUsers
                    && colExists($conn,'Jobs','recruiter_id')
                    && colExists($conn,'Recruiters','recruiter_id')
                    && colExists($conn,'Recruiters','user_id')
                    && colExists($conn,'Users','user_id')
                    && colExists($conn,'Users','full_name');

  // build SELECT list
  $select = ['j.job_id'];
  foreach (['title','description','type','location','salary','job_logo_url','application_deadline','posted_at'] as $c){
    if (!empty($colsInJobs[$c])) $select[] = "j.`{$c}`";
  }
  if ($canJoinCompany) $select[] = "u.full_name AS company_name";

  $joinSql = '';
  if ($canJoinCompany){
    $joinSql = " LEFT JOIN Recruiters r ON j.recruiter_id = r.recruiter_id
                 LEFT JOIN Users u ON r.user_id = u.user_id ";
  }

  $sql = "SELECT ".implode(', ', $select)." FROM Jobs j {$joinSql} WHERE j.job_id = ? LIMIT 1";
  $st = $conn->prepare($sql);
  if (!$st) throw new Exception($conn->error ?: 'Failed to prepare SQL');
  $st->bind_param("s", $jobId);
  $st->execute();
  $job = $st->get_result()->fetch_assoc();
  $st->close();

  if (!$job) throw new Exception("Job not found or removed.");
} catch (Throwable $e) {
  $error = $e->getMessage();
}

// derived fields with graceful fallbacks
$logo = (!empty($job['job_logo_url'])) ? $job['job_logo_url'] : './avatar_placeholder.jpg';

$salaryText = 'Not disclosed';
if (isset($job['salary']) && $job['salary'] !== '' && $job['salary'] !== null) {
  $salaryNum = (float)$job['salary'];
  $salaryText = '$'.number_format($salaryNum);
}

$deadlineText = 'Open until filled';
if (!empty($job['application_deadline']) && $job['application_deadline'] !== '0000-00-00') {
  $deadlineText = date('M d, Y', strtotime($job['application_deadline']));
}

$postedText = '';
if (!empty($job['posted_at'])) $postedText = date('M d, Y', strtotime($job['posted_at']));

$company = $job['company_name'] ?? 'Company';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title><?=h($job['title'] ?? 'Job Details')?> — JobGate</title>
  <link rel="stylesheet" href="job_details.css"/>
  <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js" defer></script>
  <style>
    .notice{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px;margin:24px auto;width:min(900px,95%);box-shadow:0 10px 26px rgba(2,6,23,.06)}
    .notice h3{margin:0 0 8px;font-size:20px;font-weight:900}
    .back-link{display:inline-flex;align-items:center;gap:6px;text-decoration:none;font-weight:800;color:#0f172a;border:1px solid #cbd5e1;padding:8px 12px;border-radius:10px}
    .back-link:hover{background:#f1f5f9}
  </style>
</head>
<body>
  <header class="topbar">
    <div class="topbar-inner">
      <img src="./JobGate_logo.png" alt="JobGate" class="logo"/>
      <nav class="top-actions">
        <a href="jobs.php" class="tlink"><iconify-icon icon="mdi:arrow-left"></iconify-icon> Back to Jobs</a>
      </nav>
    </div>
  </header>

  <?php if ($error): ?>
    <div class="notice">
      <h3>Couldn’t load this job</h3>
      <p><?=h($error)?></p>
      <p><a class="back-link" href="jobs.php"><iconify-icon icon="mdi:arrow-left"></iconify-icon> Back to Jobs</a></p>
    </div>
  <?php else: ?>
    <main class="wrap">
      <section class="details">
        <header class="h">
          <img class="logoimg" src="<?=h($logo)?>" alt="Company logo" onerror="this.src='./avatar_placeholder.jpg'"/>
          <div>
            <h2><?=h($job['title'] ?? 'Job')?></h2>
            <p class="company"><?=h($company)?></p>
          </div>
        </header>

        <dl class="meta">
          <div><dt>Type</dt><dd><?=h($job['type'] ?? '—')?></dd></div>
          <div><dt>Location</dt><dd><?=h($job['location'] ?? '—')?></dd></div>
          <div><dt>Salary</dt><dd><?=h($salaryText)?></dd></div>
          <div><dt>Deadline</dt><dd><?=h($deadlineText)?></dd></div>
          <div><dt>Posted</dt><dd><?=h($postedText ?: '—')?></dd></div>
        </dl>

        <article class="desc">
          <h3>Description</h3>
          <p><?=nl2br(h($job['description'] ?? 'No description available.'))?></p>
        </article>

        <div class="cta-row">
          <button class="btn-apply" onclick="alert('Apply feature coming soon!')">Apply Now</button>
        </div>
      </section>
    </main>
  <?php endif; ?>
</body>
</html>
