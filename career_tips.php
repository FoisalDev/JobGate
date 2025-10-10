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
/* --- INLINED CSS FIXES --- */
/* ---------------------------------------------------------------------- */

/* Core Height and Body Fixes (Essential for sticky positioning) */
html,
body {
  height: 100%;
}

/* Sidebar FIX: Use STICKY position, set explicit top pin, and enforce column flex */
.sidebar {
  /* Inherits dark background: #0b1d3a; color: #e2e8f0; */
  
  /* CORE FIX: The combination that makes it stick */
  position: sticky;
  top: 86px; /* FIXED PIN: Set to 86px to clear the topbar */
  z-index: 100;

  /* CORE FIX: Flexbox for alignment */
  display: flex;
  flex-direction: column;

  /* Ensure it fills the rest of the viewport height */
  height: calc(100vh - 86px); 
  overflow-y: auto; 
  
  padding: 12px 14px;
  gap: 6px;
}

/* Spacer: The key to pushing the logout button down */
.spacer {
  flex-grow: 1;
}

/* Logout Button: Ensures it stays pinned at the bottom */
.sbtn.logout {
  margin-top: auto; 
  margin-bottom: 0;
}
/* --- Other CSS remains unchanged (assumed in career_tips.css) --- */
/* --- Your original CSS follows for completeness, assuming it's in a file --- */

@import url("home.css");

/* Intro */
.intro {
  font-size: 16px;
  color: #475569;
  margin-bottom: 28px;
  line-height: 1.6;
  max-width: 820px;
}

/* Sections */
.tips-section {
  margin-bottom: 48px;
}
.sub-title {
  font-size: 20px;
  font-weight: 800;
  margin: 12px 0 20px;
  color: #0f172a;
}

/* Grid */
.tips-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 24px;
}

/* Cards */
.tip-card {
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 14px;
  box-shadow: 0 8px 20px rgba(2, 6, 23, 0.06);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.tip-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 28px rgba(2, 6, 23, 0.12);
}
.tip-card img {
  width: 100%;
  height: 160px;
  object-fit: cover;
}
.tip-body {
  padding: 14px 16px 18px;
}
.author {
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  margin-bottom: 6px;
  text-transform: uppercase;
}
.tip-body h4 {
  font-size: 16px;
  font-weight: 700;
  margin: 4px 0 10px;
  color: #0f172a;
  line-height: 1.4;
}
.desc {
  font-size: 14px;
  color: #334155;
  margin-bottom: 10px;
  line-height: 1.5;
}
.tag {
  font-size: 12px;
  font-weight: 700;
  color: #334155;
  background: #f1f5f9;
  padding: 4px 10px;
  border-radius: 999px;
  display: inline-block;
}

@media (max-width: 820px) {
  /* Ensuring media queries don't conflict with main fix */
  .layout {
    grid-template-columns: 1fr;
  }
  .sidebar {
    position: static;
    height: auto;
    width: 100%;
  }
}
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