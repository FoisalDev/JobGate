<?php
// Ensure this file is saved as recruiter_profile.php

// 1. Connect to the database and start session
require_once 'db_connect.php';

// Check if user is logged in and is a Recruiter
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'recruiter') {
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'applicant') {
        redirect('home.php'); // Redirect Applicant to home
    } else {
        redirect('login.php'); // Redirect unauthenticated users to login
    }
}

$recruiter_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

$message = '';
$message_type = '';

// --- 2. Handle Job Posting Logic (INSERT into Jobs, JobAssessmentRequirements) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'post_job') {
    // Collect and sanitize inputs
    $job_id = GUID();
    $title = sanitize_input($_POST['title']);
    $job_role = sanitize_input($_POST['job_role']);
    $sector_id = sanitize_input($_POST['sector_id']);
    $type = sanitize_input($_POST['type']);
    $salary = (int) $_POST['salary'];
    $deadline = sanitize_input($_POST['deadline']);
    $description = sanitize_input($_POST['description']);
    $requirements = sanitize_input($_POST['requirements']);
    $featured = isset($_POST['featured']) ? 1 : 0;
    $assessment_id = sanitize_input($_POST['assessment_id']);
    
    // Simple validation (can be expanded)
    if (empty($title) || empty($job_role) || empty($sector_id) || empty($deadline) || empty($description)) {
        $message = "Please fill in all required fields.";
        $message_type = 'error';
    } else {
        $mysqli->begin_transaction();
        try {
            // A. Insert into Jobs table
            $stmt = $mysqli->prepare("INSERT INTO Jobs (job_id, recruiter_id, sector_id, title, job_role, job_type, salary, description, requirements, deadline, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssisssi", $job_id, $recruiter_id, $sector_id, $title, $job_role, $type, $salary, $description, $requirements, $deadline, $featured);
            $stmt->execute();
            
            // B. Insert into JobAssessmentRequirements (Link job to assessment)
            if (!empty($assessment_id)) {
                $req_id = GUID();
                $stmt_req = $mysqli->prepare("INSERT INTO JobAssessmentRequirements (req_id, job_id, assessment_id) VALUES (?, ?, ?)");
                $stmt_req->bind_param("sss", $req_id, $job_id, $assessment_id);
                $stmt_req->execute();
            }

            $mysqli->commit();
            $message = "Job posted successfully!";
            $message_type = 'success';
            
            // Clear form data after successful submission (Optional)
            unset($_POST);

        } catch (mysqli_sql_exception $e) {
            $mysqli->rollback();
            $message = "Error posting job: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// --- 3. Data Fetching for Dropdowns and Job History ---

// Fetch Job Sectors for dropdown
$sectors = [];
$result = $mysqli->query("SELECT sector_id, name FROM JobSectors ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $sectors[] = $row;
}

// Fetch Assessments for dropdown
$assessments = [];
$result = $mysqli->query("SELECT assessment_id, title FROM Assessments ORDER BY title");
while ($row = $result->fetch_assoc()) {
    $assessments[] = $row;
}

// Fetch Recruiter's Job History
$job_history = [];
$stmt = $mysqli->prepare("SELECT job_id, title, job_role, deadline, creation_date FROM Jobs WHERE recruiter_id = ? ORDER BY creation_date DESC");
$stmt->bind_param("s", $recruiter_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $job_history[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>JobGate — Recruiter Profile</title>
    <link rel="stylesheet" href="recruiter_profile.css" />
    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
  </head>
  <body>
    <!-- Top bar -->
    <header class="topbar">
      <div class="topbar-inner">
        <img src="./JobGate_logo.png" alt="JobGate" class="logo" />
        <nav class="top-actions">
          <a href="home.php" class="tlink">Home</a>
          <a href="recruiter_profile.php" class="tlink">Profile</a>
          <span class="tlink"><?= htmlspecialchars($full_name) ?></span>
          <img
            src="./avatar_placeholder.jpg"
            class="avatar"
            alt="User avatar"
          />
        </nav>
      </div>
    </header>

    <div class="layout">
      <!-- Sidebar -->
      <aside class="sidebar">
        <!-- Sidebar links relevant to Recruiter -->
        <a class="sbtn" href="home.php">
          <iconify-icon icon="mdi:view-dashboard"></iconify-icon>Dashboard
        </a>
        <a class="sbtn active" href="recruiter_profile.php">
          <iconify-icon icon="mdi:briefcase-edit-outline"></iconify-icon>Post Job
        </a>
        <a class="sbtn" href="jobs.php">
          <iconify-icon icon="mdi:account-group-outline"></iconify-icon>View Applicants
        </a>
        <div class="spacer"></div>
        <a class="sbtn logout" href="logout.php">
          <iconify-icon icon="mdi:logout"></iconify-icon>Log out
        </a>
      </aside>

      <!-- Main Content -->
      <main class="content">
        <!-- Profile Header -->
        <section class="profile-head">
          <div class="ph-left">
            <h1>Welcome, <?= htmlspecialchars($full_name) ?></h1>
            <p>You are managing job postings for your organization.</p>
          </div>
          <div class="ph-right">
            <a class="btn-primary" href="#">
                <iconify-icon icon="mdi:settings"></iconify-icon> Settings
            </a>
          </div>
        </section>

        <!-- Message Alert -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Job Posting Form -->
        <section class="job-post-card">
          <h3 class="card-title">Post a New Job Opening</h3>
          <form method="POST" action="recruiter_profile.php">
            <input type="hidden" name="action" value="post_job" />
            <div class="form-grid">
              <!-- Job Title -->
              <div class="form-group">
                <label for="title">Job Title *</label>
                <input type="text" id="title" name="title" required value="<?= $_POST['title'] ?? '' ?>"/>
              </div>

              <!-- Job Role (Specific Job Role/Title) -->
              <div class="form-group">
                <label for="job_role">Specific Role/Title *</label>
                <input type="text" id="job_role" name="job_role" required value="<?= $_POST['job_role'] ?? '' ?>"/>
              </div>
              
              <!-- Job Sector -->
              <div class="form-group">
                <label for="sector_id">Job Sector *</label>
                <select id="sector_id" name="sector_id" required>
                  <option value="">Select Sector</option>
                  <?php foreach ($sectors as $sector): ?>
                    <option value="<?= htmlspecialchars($sector['sector_id']) ?>"
                            <?= (isset($_POST['sector_id']) && $_POST['sector_id'] === $sector['sector_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sector['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- Job Type -->
              <div class="form-group">
                <label for="type">Employment Type *</label>
                <select id="type" name="type" required>
                  <option value="Full-time" <?= (isset($_POST['type']) && $_POST['type'] === 'Full-time') ? 'selected' : '' ?>>Full-time</option>
                  <option value="Part-time" <?= (isset($_POST['type']) && $_POST['type'] === 'Part-time') ? 'selected' : '' ?>>Part-time</option>
                  <option value="Contract" <?= (isset($_POST['type']) && $_POST['type'] === 'Contract') ? 'selected' : '' ?>>Contract</option>
                </select>
              </div>

              <!-- Salary (Input as integer) -->
              <div class="form-group">
                <label for="salary">Salary (USD/Month)</label>
                <input type="number" id="salary" name="salary" value="<?= $_POST['salary'] ?? '0' ?>" min="0" step="100"/>
              </div>

              <!-- Deadline -->
              <div class="form-group">
                <label for="deadline">Application Deadline *</label>
                <input type="date" id="deadline" name="deadline" required value="<?= $_POST['deadline'] ?? '' ?>"/>
              </div>

              <!-- Required Assessment (Job Gate Feature) -->
              <div class="form-group form-full">
                <label for="assessment_id">Mandatory Skill Assessment (Optional)</label>
                <select id="assessment_id" name="assessment_id">
                  <option value="">No Mandatory Assessment</option>
                  <?php foreach ($assessments as $assessment): ?>
                    <option value="<?= htmlspecialchars($assessment['assessment_id']) ?>"
                            <?= (isset($_POST['assessment_id']) && $_POST['assessment_id'] === $assessment['assessment_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($assessment['title']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <small class="text-muted">Selecting an assessment gates the application process.</small>
              </div>

              <!-- Description -->
              <div class="form-group form-full">
                <label for="description">Job Description *</label>
                <textarea id="description" name="description" required rows="5"><?= $_POST['description'] ?? '' ?></textarea>
              </div>

              <!-- Requirements -->
              <div class="form-group form-full">
                <label for="requirements">Key Requirements / Skills (Separate lines with commas/bullets)</label>
                <textarea id="requirements" name="requirements" rows="3"><?= $_POST['requirements'] ?? '' ?></textarea>
              </div>
            </div>

            <div class="form-actions">
              <label style="margin-right: 16px; font-weight: normal;">
                  <input type="checkbox" name="featured" <?= (isset($_POST['featured']) && $_POST['featured']) ? 'checked' : '' ?> style="margin-right: 5px;"> Feature on Homepage
              </label>
              <button type="submit" class="btn-primary">
                <iconify-icon icon="mdi:send"></iconify-icon> Publish Job
              </button>
            </div>
          </form>
        </section>

        <!-- Job History -->
        <section class="history-card">
          <h3 class="card-title">Your Posted Jobs (<?= count($job_history) ?>)</h3>
          <?php if (empty($job_history)): ?>
              <p class="text-muted">You have not posted any jobs yet.</p>
          <?php else: ?>
              <div class="job-history-list">
                  <?php foreach ($job_history as $job): ?>
                      <div class="history-item">
                          <div>
                              <div class="job-title"><?= htmlspecialchars($job['title']) ?></div>
                              <div class="job-meta">
                                  Role: <?= htmlspecialchars($job['job_role']) ?> | Posted: <?= date('M d, Y', strtotime($job['creation_date'])) ?> | Deadline: <?= date('M d, Y', strtotime($job['deadline'])) ?>
                              </div>
                          </div>
                          <a href="job_details.php?jobId=<?= htmlspecialchars($job['job_id']) ?>" class="btn-ghost">View</a>
                      </div>
                  <?php endforeach; ?>
              </div>
          <?php endif; ?>
        </section>
      </main>
    </div>
  </body>
</html>
