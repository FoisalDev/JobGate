<!DOCTYPE html>
<html lang="bn">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>JobGate — Courses</title>
    <link rel="stylesheet" href="courses.css" />
    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
  </head>
  <body>
    <!-- Top bar -->
    <header class="topbar">
      <div class="topbar-inner">
        <img src="./JobGate_logo.png" alt="JobGate" class="logo" />
        <!-- <div class="search-wrap" role="search">
          <iconify-icon icon="mdi:magnify" class="sicon"></iconify-icon>
          <input id="globalSearch" type="text" placeholder="Search JobGate" />
        </div> -->
        <nav class="top-actions">
          <a href="home.php" class="tlink">Home</a>
          <a href="profile.php" class="tlink">Profile</a>
          <img src="./avatar_placeholder.jpg" class="avatar" />
        </nav>
      </div>
    </header>

    <div class="layout">
      <!-- Sidebar -->
      <aside class="sidebar">
        <button class="sbtn" onclick="window.location.href='home.php'">
          <iconify-icon icon="mdi:home"></iconify-icon>Feed
        </button>
        <button class="sbtn" onclick="window.location.href='career_tips.php'">
          <iconify-icon icon="mdi:lightbulb-on-outline"></iconify-icon>Career
          Tips
        </button>
        <button class="sbtn" onclick="window.location.href='job_events.php'">
          <iconify-icon icon="mdi:calendar-star"></iconify-icon>Job Events
        </button>
        <button class="sbtn active">
          <iconify-icon icon="mdi:book-open-variant"></iconify-icon>Courses
        </button>
        <button
          class="sbtn"
          onclick="window.location.href='skill_assessment.html'"
        >
          <iconify-icon icon="mdi:account-check-outline"></iconify-icon>Skill
          Assessment
        </button>
        <button class="sbtn" onclick="window.location.href='jobs.php'">
          <iconify-icon icon="mdi:briefcase-outline"></iconify-icon>Jobs
        </button>
        <div class="spacer"></div>
        <button class="sbtn logout" onclick="window.location.href='login.php'">
          <iconify-icon icon="mdi:logout"></iconify-icon>Log out
        </button>
      </aside>

      <!-- Main -->
      <main class="content" style="margin-left: 20px;">
        <h1 class="page-title">  Skill Up with Courses</h1>

        <!-- Controls -->
        <div class="controls">
          <div class="input">
            <iconify-icon icon="mdi:magnify"></iconify-icon>
            <input
              id="searchTitle"
              type="text"
              placeholder="Search Course By Title"
            />
          </div>

          <select id="categoryFilter" class="select">
            <option value="all" selected>All Courses</option>
            <option value="ai-ml">AI & ML</option>
            <option value="testing">Testing</option>
            <option value="programming">Programming</option>
          </select>

          <!-- <a
            href="#"
            class="btn-primary"
            onclick="alert('Hook to Create Note'); return false;"
          >
            <iconify-icon icon="mdi:plus-circle"></iconify-icon>
            Create Note
          </a> -->
        </div>

        <!-- Sections -->
        <div id="sections"></div>
      </main>
    </div>

    <script>
      // Demo course data
      const COURSES = [
        {
          id: "c1",
          cat: "ai-ml",
          catTitle: "Programming",
          title: "AI and ML",
          subtitle: "Natural Language Processing",
          blurb: "This course covers techniques and tools used in NLP.",
          noteUrl: "course_note.php?courseId=c1",
        },
        {
          id: "c2",
          cat: "ai-ml",
          catTitle: "Programming",
          title: "AI and ML",
          subtitle: "Deep Learning Fundamentals",
          blurb: "Comprehensive overview of deep learning and neural nets.",
          noteUrl: "course_note.php?courseId=c2",
        },
        {
          id: "c3",
          cat: "testing",
          catTitle: "Testing",
          title: "Software Testing",
          subtitle: "SDLC",
          blurb: "Covers the entire Software Development Life Cycle.",
          noteUrl: "course_note.php?courseId=c3",
        },
        {
          id: "c4",
          cat: "testing",
          catTitle: "Testing",
          title: "Software Testing",
          subtitle: "Test Automation",
          blurb: "Dive into test automation techniques, tools, and frameworks.",
          noteUrl: "course_note.php?courseId=c4",
        },
      ];

      const sectionsWrap = document.getElementById("sections");

      function render() {
        const q = (
          document.getElementById("searchTitle").value || ""
        ).toLowerCase();
        const cat = document.getElementById("categoryFilter").value;

        const filtered = COURSES.filter((c) => {
          const matchesCat = cat === "all" || c.cat === cat;
          const matchesQ =
            !q ||
            c.title.toLowerCase().includes(q) ||
            c.subtitle.toLowerCase().includes(q);
          return matchesCat && matchesQ;
        });

        const groups = {};
        filtered.forEach((c) => {
          groups[c.catTitle] = groups[c.catTitle] || [];
          groups[c.catTitle].push(c);
        });

        sectionsWrap.innerHTML = "";
        Object.keys(groups).forEach((head) => {
          const s = document.createElement("section");
          s.className = "course-section";
          s.innerHTML = `
            <h3 class="cat-head">${head}</h3>
            <div class="row">${groups[head].map(cardTpl).join("")}</div>
          `;
          sectionsWrap.appendChild(s);
        });
      }

      function cardTpl(c) {
        return `
          <article class="course-card">
            <div class="thumb" style="--thumb:url('./course_banner.jpg')"></div>
            <div class="card-inner">
              <header>
                <h4 class="title">${c.title}</h4>
                <p class="subtitle">${c.subtitle}</p>
              </header>
              <p class="blurb">${c.blurb}</p>
              <div class="footer">
                <a class="note" href="${c.noteUrl}" target="_blank">
                  View Note <iconify-icon icon="mdi:open-in-new"></iconify-icon>
                </a>
              </div>
            </div>
          </article>
        `;
      }

      document.getElementById("searchTitle").addEventListener("input", render);
      document
        .getElementById("categoryFilter")
        .addEventListener("change", render);

      render();
    </script>
  </body>
</html>
