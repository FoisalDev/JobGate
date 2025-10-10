<!DOCTYPE html>
<html lang="bn">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>JobGate — Career Tips</title>
    <link rel="stylesheet" href="career_tips.css" />
    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
  </head>
  <body>
    <header class="topbar">
      <div class="topbar-inner">
        <img src="./JobGate_logo.png" alt="JobGate" class="logo" />
        <div class="search-wrap">
          <iconify-icon icon="mdi:magnify" class="sicon"></iconify-icon>
          <input type="text" placeholder="Search JobGate" />
        </div>
        <nav class="top-actions">
          <a href="home.html" class="tlink">Home</a>
          <a href="profile.html" class="tlink">Profile</a>
          <img src="./avatar_placeholder.jpg" class="avatar" />
        </nav>
      </div>
    </header>

    <div class="layout">
      <aside class="sidebar">
        <button class="sbtn" onclick="window.location.href='home.html'">
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
        <button class="sbtn logout" onclick="window.location.href='login.html'">
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
              <img src="./career_tips_photo/career1.jpg" alt="Career Tip" />
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
              <img src="./career_tips_photo/career2.jpg" alt="Career Tip" />
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
              <img src="./career_tips_photo/career3.jpg" alt="Career Tip" />
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