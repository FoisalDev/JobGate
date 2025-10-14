<?php
// NOTE: We assume db_connect.php, is_logged_in(), and redirect() are defined elsewhere.
session_start();

// Ensure all necessary PHP files are loaded
require_once 'db_connect.php'; 

// Check if user is logged in
if (!is_logged_in()) {
    redirect('login.php');
}

$user_id   = $_SESSION['user_id'] ?? null;
$full_name = $_SESSION['full_name'] ?? 'User';
$user_type = $_SESSION['user_type'] ?? 'applicant';

$homePage = 'home.php';
$profilePage = ($user_type === 'recruiter') ? 'recruiter_profile.php' : 'profile.php';
$avatarSrc = './avatar_placeholder.jpg';

// --- FIX 1: PHP Logic to load User Profile Picture ---
try {
  // Assuming $conn is the database connection variable from db_connect.php
  if (isset($conn) && $user_id) {
      $stmt = $conn->prepare("SELECT profile_photo_url FROM Users WHERE user_id = ? LIMIT 1");
      $stmt->bind_param("s", $user_id);
      $stmt->execute();
      $u = $stmt->get_result()->fetch_assoc();
      $stmt->close();
      if ($u && !empty($u['profile_photo_url'])) {
        $avatarSrc = $u['profile_photo_url'];
      }
  }
} catch (Throwable $e) { /* ignore DB errors */ }

$avatarSrc = htmlspecialchars($avatarSrc);
// --- END FIX 1 ---
?>
<!DOCTYPE html>
<html lang="bn">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>JobGate — Career Tips</title>
    <link rel="stylesheet" href="career_tips.css" />
    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
    
    <style>
/* ---------------------------------------------------------------------- */
/* --- CORE CSS FIXES FOR FIXED TOPBAR & SIDEBAR --- */
/* ---------------------------------------------------------------------- */

/* 1. HTML/Body Base Settings (Essential for fixed positioning) */
html,
body {
  height: 100%;
  margin: 0; 
  /* FIX: Added margin: 0 to ensure no default browser margin breaks fixed layout */
}

/* 2. Topbar FIX: Must be fixed to prevent scrolling */
.topbar {
    position: fixed; 
    top: 0;
    width: 100%; 
    z-index: 1000;
}
/* Compensate for the fixed topbar */
body {
    padding-top: 86px; /* Assuming topbar height is 86px */
}

/* 3. Sidebar FIX: Make it permanently Fixed right below the Topbar */
.sidebar {
  /* CORE FIX: Change to FIXED and pin */
  position: fixed; 
  left: 0; /* Pin to the very left of the viewport */
  top: 86px; /* Pinned exactly below the fixed topbar */
  z-index: 999;
  
  /* CORE FIX: Flexbox for alignment */
  display: flex;
  flex-direction: column;

  /* Calculate height: 100vh - topbar height - small buffer */
  height: calc(100vh - 86px - 2px); /* Subtracted 2px buffer for rendering stability */
  overflow-y: auto; 
  
  /* Retaining necessary inherited styles */
  padding: 12px 14px;
  gap: 6px;
  width: 260px; /* Assuming its width */
}

/* 4. Layout: Must adjust the content area to clear the fixed sidebar */
/* CRITICAL: We need to push the main content over by the fixed sidebar width */
.layout {
    /* Retaining original layout's structure */
    width: min(1380px, 96%); 
    margin: 0 auto; 
    padding: 0; 
    
    /* CRITICAL FIX: Add margin to the main layout wrapper to clear the fixed sidebar */
    margin-left: 260px;
    width: calc(100% - 260px); 

    /* The content div will now handle the scrolling and internal layout */
    display: block; /* Removing grid/flex on .layout to simplify flow */
}

/* 5. Spacer and Logout Fix */
.spacer {
  flex-grow: 1;
}
.sbtn.logout {
  margin-top: auto; 
  margin-bottom: 0;
}

/* --- The rest of your original CSS/HTML structure remains unchanged --- */
</style>

  </head>
  <body>
    <header class="topbar">
      <div class="topbar-inner">
        <img src="./JobGate_logo.png" alt="JobGate" class="logo" />
        
        <nav class="top-actions">
          <a href="<?php echo htmlspecialchars($homePage); ?>" class="tlink">Home</a>
          <a href="<?php echo htmlspecialchars($profilePage); ?>" class="tlink">Profile</a>
          <img src="<?php echo $avatarSrc; ?>" 
               class="avatar" 
               alt="<?php echo htmlspecialchars($full_name); ?> avatar"
               onerror="this.src='./avatar_placeholder.jpg';" />
        </nav>
      </div>
    </header>

    <div class="layout">
      <aside class="sidebar">
        <button class="sbtn" onclick="window.location.href='<?php echo htmlspecialchars($homePage); ?>'">
          <iconify-icon icon="mdi:home" class="sib"></iconify-icon>Feed
        </button>
        <button class="sbtn active">
          <iconify-icon
            icon="mdi:lightbulb-on-outline"
            class="sib"
          ></iconify-icon
          >Career Tips
        </button>
        <button class="sbtn" onclick="window.location.href='job_events.html'">
          <iconify-icon icon="mdi:calendar-star" class="sib"></iconify-icon>Job
          Events
        </button>
        <button class="sbtn" onclick="window.location.href='courses.html'">
          <iconify-icon icon="mdi:book-open-variant" class="sib"></iconify-icon
          >Courses
        </button>
        <button
          class="sbtn"
          onclick="window.location.href='skill_assessment.html'"
        >
          <iconify-icon
            icon="mdi:account-check-outline"
            class="sib"
          ></iconify-icon
          >Skill Assessment
        </button>
        <button class="sbtn" onclick="window.location.href='jobs.html'">
          <iconify-icon icon="mdi:briefcase-outline" class="sib"></iconify-icon
          >Jobs
        </button>
        <div class="spacer"></div>
        <button class="sbtn logout" onclick="window.location.href='logout.php'">
          <iconify-icon icon="mdi:logout" class="sib"></iconify-icon>Log out
        </button>
      </aside>

      <main class="content">
        <h2 class="section-title">Career Advice</h2>
        <p class="intro">Explore career tips by category, curated for you.</p>

        <section class="tips-section">
          <h3 class="sub-title">Most Popular</h3>
          <div class="tips-grid">
            <article class="tip-card">
              <img src="./career_tips_photo/career1.jpg" alt="Winning Resume" />
              <div class="tip-body">
                <p class="author">BY JOHN DOE</p>
                <h4>How to Write a Winning Resume</h4>
                <p class="desc">
                  Learn proven strategies to stand out in your resume and
                  attract top employers.
                </p>
                <span class="tag">RESUME</span>
              </div>
            </article>
            <article class="tip-card">
              <img src="./career_tips_photo/career2.jpg" alt="Interview Mistakes" />
              <div class="tip-body">
                <p class="author">BY JANE SMITH</p>
                <h4>Interview Mistakes You Must Avoid</h4>
                <p class="desc">
                  From body language to tough questions — here’s how to ace your
                  interview.
                </p>
                <span class="tag">INTERVIEW</span>
              </div>
            </article>
            
            <article class="tip-card">
              <img src="./career_tips_photo/career_communication.jpg" alt="Communication Tip" />
              <div class="tip-body">
                <p class="author">BY AHMAD KHAN</p>
                <h4>Mastering Workplace Communication</h4>
                <p class="desc">
                  Effective communication is the cornerstone of career growth. Learn to articulate clearly and listen actively.
                </p>
                <span class="tag">SOFT SKILLS</span>
              </div>
            </article>
            
            <article class="tip-card">
              <img src="./career_tips_photo/career_portfolio.jpg" alt="Portfolio Strategy" />
              <div class="tip-body">
                <p class="author">BY NUPUR DUTTA</p>
                <h4>Creating a Portfolio That Converts</h4>
                <p class="desc">
                  For creative and technical roles, your portfolio is your best resume. Structure it to showcase impact.
                </p>
                <span class="tag">PORTFOLIO</span>
              </div>
            </article>

          </div>
        </section>

        <section class="tips-section">
          <h3 class="sub-title">Growth and Advancement</h3>
          <div class="tips-grid">
            <article class="tip-card">
              <img src="./career_tips_photo/career_salary.jpg" alt="Salary Negotiation" />
              <div class="tip-body">
                <p class="author">BY SAMUEL LEE</p>
                <h4>Salary Negotiation: Know Your Worth</h4>
                <p class="desc">
                  Strategies for successful salary negotiation without appearing greedy or desperate. Research is key!
                </p>
                <span class="tag">FINANCE</span>
              </div>
            </article>
            <article class="tip-card">
              <img src="./career_tips_photo/career_mentor.jpg" alt="Mentorship" />
              <div class="tip-body">
                <p class="author">BY CHLOE WANG</p>
                <h4>Finding & Utilizing a Career Mentor</h4>
                <p class="desc">
                  A mentor accelerates growth. Learn where to find industry leaders willing to guide your career path.
                </p>
                <span class="tag">MENTORSHIP</span>
              </div>
            </article>
          </div>
        </section>
        
        <section class="tips-section">
          <h3 class="sub-title">In the News</h3>
          <div class="tips-grid">
            <article class="tip-card">
              <img src="./career_tips_photo/career3.jpg" alt="AI Career Trends" />
              <div class="tip-body">
                <p class="author">BY EDITORIAL TEAM</p>
                <h4>AI is Changing Careers: What You Need to Know</h4>
                <p class="desc">
                  Discover how AI is reshaping industries and what skills will
                  be in demand.
                </p>
                <span class="tag">TECH</span>
              </div>
            </article>
             
            <article class="tip-card">
              <img src="./career_tips_photo/career_burnout.jpg" alt="Preventing Burnout" />
              <div class="tip-body">
                <p class="author">BY DR. ALI HASSAN</p>
                <h4>Strategies to Beat Workplace Burnout</h4>
                <p class="desc">
                  Recognize the signs of burnout early and implement practical strategies for lasting work-life balance.
                </p>
                <span class="tag">WELLNESS</span>
              </div>
            </article>
          </div>
        </section>
        
      </main>
    </div>
  </body>
</html>