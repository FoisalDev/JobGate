<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

$user_id = $_SESSION['user_id'];
$assessment_id = $_GET['assessmentId'] ?? '';
if ($assessment_id === '') {
  header("Location: skill_assessment.php");
  exit;
}

/* 🔹 Load assessment details */
$assessment = null;
try {
  $st = $conn->prepare("SELECT * FROM Assessments WHERE assessment_id = ? LIMIT 1");
  $st->bind_param("s", $assessment_id);
  $st->execute();
  $assessment = $st->get_result()->fetch_assoc();
  $st->close();
  if (!$assessment) die("Assessment not found!");
} catch (Throwable $e) {
  die("Error loading assessment: ".$e->getMessage());
}

/* 🔹 Load questions + options (POST গ্রেডিংয়ের জন্য এটাকে আগে লোড করা দরকার) */
$questions = [];
try {
  $sql = "SELECT q.question_id, q.question_text, q.correct_option_index,
                 o.option_index, o.option_text
          FROM Questions q
          LEFT JOIN QuestionOptions o ON q.question_id = o.question_id
          WHERE q.assessment_id = ?
          ORDER BY q.question_id, RAND()";
  $st = $conn->prepare($sql);
  $st->bind_param("s", $assessment_id);
  $st->execute();
  $res = $st->get_result();
  while ($row = $res->fetch_assoc()) {
    if (empty($row['option_text'])) continue;
    $qid = $row['question_id'];
    if (!isset($questions[$qid])) {
      $questions[$qid] = [
        'text'    => $row['question_text'],
        'correct' => (int)$row['correct_option_index'],
        'options' => []
      ];
    }
    $questions[$qid]['options'][] = [
      'index' => (int)$row['option_index'],
      'text'  => $row['option_text']
    ];
  }
  $st->close();
} catch (Throwable $e) {
  die("Error fetching questions: ".$e->getMessage());
}

/* 🔸 Handle submission — HTML এর আগে */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // ✅ applicant_id আগে বের করি
  try {
    $st = $conn->prepare("SELECT applicant_id FROM Applicants WHERE user_id = ? LIMIT 1");
    $st->bind_param("s", $user_id);
    $st->execute();
    $app = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$app) die("Applicant not found for this user!");
    $applicant_id = $app['applicant_id'];
  } catch (Throwable $e) {
    die("Error getting applicant: ".$e->getMessage());
  }

  // ✅ স্কোরিং
  $score = 0;
  $total = max(count($questions), 1);
  foreach ($questions as $qid => $q) {
    $ans = isset($_POST[$qid]) ? (int)$_POST[$qid] : -1;
    if ($ans === (int)$q['correct']) $score++;
  }
  $percent = (int)round(($score / $total) * 100);
  $pass    = ($percent >= (int)$assessment['pass_score_percent']) ? 1 : 0;

  // ✅ attempt number
  $st = $conn->prepare("SELECT MAX(attempt_number) AS last_attempt 
                        FROM AssessmentResults 
                        WHERE applicant_id=? AND assessment_id=?");
  $st->bind_param("ss", $applicant_id, $assessment_id);
  $st->execute();
  $last = $st->get_result()->fetch_assoc();
  $st->close();
  $attempt_num = (int)($last['last_attempt'] ?? 0) + 1;

  // ✅ insert result (সঠিক bind types সহ)
  try {
    $conn->begin_transaction();

    $rid = uniqid("RES_");
    $st = $conn->prepare("INSERT INTO AssessmentResults 
      (result_id, applicant_id, assessment_id, 
       score_obtained, score_percent, attempt_number, is_passed, exam_date)
      VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");

    // sssiiii = string,string,string,int,int,int,int
    $st->bind_param("sssiiii", $rid, $applicant_id, $assessment_id,
                              $score, $percent, $attempt_num, $pass);
    $st->execute();
    $st->close();

    $conn->commit();

    // ✅ success message + redirect (এখন header কাজ করবে, কারণ এখনও কিছু আউটপুট হয়নি)
    $_SESSION['result_msg'] = "✅ Exam Submitted!<br>Score: {$percent}%<br>Attempt: {$attempt_num}<br>".($pass ? "🎉 Passed" : "❌ Failed");
    header("Location: skill_assessment.php");
    exit;
  } catch (Throwable $e) {
    $conn->rollback();
    die("Error saving result: ".$e->getMessage());
  }
}

/* ▶️ GET হলে—options shuffle করে UI রেন্ডার */
foreach ($questions as &$q) shuffle($q['options']);
unset($q);
$qJSON = json_encode($questions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>JobGate — Attempt Exam</title>
<link rel="stylesheet" href="skill_exam.css">
<style>
  body { background:#f9fafb; font-family:'Segoe UI',Roboto,sans-serif; margin:0; }
  .exam-wrap { width:min(900px,95%); margin:40px auto; background:#fff; border-radius:16px;
    box-shadow:0 14px 36px rgba(2,6,23,0.12); padding:32px 40px; }
  h1 { font-size:28px; font-weight:900; color:#0f172a; margin-bottom:6px; }
  .meta { display:flex; justify-content:space-between; align-items:center; color:#475569; font-weight:700;
    border-bottom:1px solid #e2e8f0; padding-bottom:10px; margin-bottom:24px; }
  #timer { font-size:18px; font-weight:800; color:#dc2626; }
  .progress-bar { width:100%; height:10px; border-radius:999px; background:#e2e8f0; overflow:hidden; margin:10px 0 30px; }
  .progress-inner { height:100%; background:linear-gradient(90deg,#3b82f6,#60a5fa); width:100%; transition:width 1s linear; }
  .question { background:#f9fafb; border:1px solid #e2e8f0; border-radius:14px; padding:16px 20px; margin-bottom:20px; box-shadow:0 4px 10px rgba(15,23,42,0.04); }
  .question h3 { font-size:17px; font-weight:800; margin:0 0 10px; }
  label { display:block; background:#fff; padding:8px 10px; border-radius:10px; border:1px solid #cbd5e1; margin:6px 0; cursor:pointer; transition:0.2s ease; }
  input[type="radio"] { margin-right:8px; }
  label:hover { background:#e0f2fe; }
  .submit-row { text-align:center; margin-top:30px; }
  .btn-primary { background:#3b82f6; color:#fff; border:none; padding:12px 26px; font-weight:900; border-radius:12px; cursor:pointer; box-shadow:0 8px 20px rgba(59,130,246,0.25); }
  .btn-primary:hover { background:#2563eb; }
</style>
</head>
<body>
  <div class="exam-wrap">
    <h1><?php echo htmlspecialchars($assessment['title']); ?></h1>
    <div class="meta">
      <span>⏱ Duration: <?php echo (int)$assessment['duration_minutes']; ?> min</span>
      <span id="timer">Loading...</span>
    </div>
    <div class="progress-bar"><div class="progress-inner" id="progressBar"></div></div>

    <form id="examForm" method="POST">
      <div id="questions"></div>
      <div class="submit-row">
        <button type="submit" class="btn-primary">Submit Exam</button>
      </div>
    </form>

    <?php if (!empty($_SESSION['result_msg'])): ?>
      <div class="result-box"><?php echo $_SESSION['result_msg']; unset($_SESSION['result_msg']); ?></div>
    <?php endif; ?>
  </div>

<script>
const QUESTIONS = <?php echo $qJSON; ?>;
const totalSec = <?php echo (int)$assessment['duration_minutes'] * 60; ?>;
let sec = totalSec;

const wrap = document.getElementById("questions");
Object.keys(QUESTIONS).forEach((qid, i) => {
  const q = QUESTIONS[qid];
  const opts = (q.options || []).filter(o => o.text && o.text.trim() !== "");
  const div = document.createElement("div");
  div.className = "question";
  div.innerHTML = `
    <h3>${i + 1}. ${q.text}</h3>
    ${opts.map(o => `<label><input type="radio" name="${qid}" value="${o.index}" required> ${o.text}</label>`).join("")}
  `;
  wrap.appendChild(div);
});

const timer = document.getElementById("timer");
const progress = document.getElementById("progressBar");
function tick() {
  const m = String(Math.floor(sec / 60)).padStart(2, "0");
  const s = String(sec % 60).padStart(2, "0");
  timer.textContent = `🕐 ${m}:${s}`;
  progress.style.width = ((sec / totalSec) * 100) + "%";
  if (sec <= 0) document.getElementById("examForm").submit();
  else { sec--; setTimeout(tick, 1000); }
}
tick();
</script>
</body>
</html>
