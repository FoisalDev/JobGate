<!DOCTYPE html>
<html lang="bn">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>JobGate — Course Note</title>
    <link rel="stylesheet" href="course_note.css" />
    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
  </head>
  <body>
    <!-- Top bar -->
    <header class="topbar">
      <div class="topbar-inner">
        <img src="./JobGate_logo.png" alt="JobGate" class="logo" />
        <!-- <div class="search-wrap">
          <iconify-icon icon="mdi:magnify"></iconify-icon>
          <input type="text" placeholder="Search JobGate" />
        </div> -->
        <nav class="top-actions">
          <a href="home.php" class="tlink">Home</a>
          <a href="profile.php" class="tlink">Profile</a>
          <img src="./avatar_placeholder.jpg" class="avatar" />
        </nav>
      </div>
    </header>

    <main class="wrap">
      <div class="topline">
        <a
          class="back"
          href="courses.php"
          onclick="if(history.length>1){history.back();return false;}"
        >
          <iconify-icon icon="mdi:arrow-left"></iconify-icon>
        </a>
        <h1 class="page-title">Skill Up with Courses</h1>
      </div>

      <section class="note-card">
        <header class="note-head">
          <h2 id="cTitle">Course Title</h2>
          <a id="cSubtitle" href="#">Subtitle</a>
        </header>

        <article id="noteBody" class="note-body"></article>

        <div class="cta-row">
          <button class="btn-done" id="btnDone">Done</button>
        </div>
      </section>
    </main>
    <a href=""></a>
    <script>
      const id = new URLSearchParams(location.search).get("courseId");

      const NOTES = {
        c1: {
          title: "AI and ML",
          subtitle: "Natural Language Processing",
          html: `
            <p>Learn how machines process language using AI & ML.</p>
            <h3>Topics</h3>
            <ol>
              <li><a href="https://www.youtube.com/watch?v=CMrHM8a3hqw"> What is the NPL and How Does it work</a> </li>
              <li><a href="https://www.youtube.com/watch?v=G6wdZQw4d8Y">Natural Language full course</a></li>
              <li> <a href="https://web.stanford.edu/~jurafsky/slp3/?utm_source=chatgpt.com">An Introduction to Natural Language Processing</a></li>
              <li><a href="https://tjzhifei.github.io/resources/NLTK.pdf?utm_source=chatgpt.com">Natural Language Processing with Python</a></li>
            </ol>
          `,
        },
        c2: {
          title: "AI and ML",
          subtitle: "Deep Learning Fundamentals",
          html: `<p>Deep Learning core concepts with examples.</p>
          <ol>
              <li><a href="youtube.com/watch?v=VyWAvY2CF9c&utm_source=chatgpt.com">Introduction to Deep Learning (6.S191)</a> </li>
        
              <li> <a href="https://d2l.ai/d2l-en.pdf?utm_source=chatgpt.com">Dive into Deep Learning</a></li>
              
            </ol>
          `,
        },
        c3: {
          title: "Software Testing",
          subtitle: "SDLC",
          html: `<p>Overview of Software Development Life Cycle in Testing.</p>
          <ol>
              <li><a href="https://www.youtube.com/watch?v=sTLZDNQq5C4">Software Testing phase</a> </li>
              <li><a href="https://www.accelq.com/wp-content/uploads/2023/05/A-Complete-Guide-To-Software-Testing-Life-Cycle-3-1.pdf?utm_source=chatgpt.com">Complete Guide to Software Testing Life Cycle </a> </li>
              <li> <a href="https://dahlan.unimal.ac.id/files/ebooks/2007%20%5BMcLeod_R.%5D_Software_Testing_Testing_Across_the_E.pdf?utm_source=chatgpt.com">Testing Accross in SDLC</a></li>
              
            </ol>
          `,
        },
        c4: {
          title: "Software Testing",
          subtitle: "Test Automation",
          html: `<p>Automation frameworks, tools, and CI/CD integration.</p>
          <ol>
              <li><a href="https://exactpro.com/sites/default/files/attachments/test_automation_principles.pdf?utm_source=chatgpt.com">Automation Test Principels</a> </li>
              <li><a href="https://www.youtube.com/watch?v=-WnTkcq_By0">How to learn Automation Test in present </a> </li>
              <li> <a href="https://www.youtube.com/watch?v=HmQv8Z4om4I&utm_source=chatgpt.com">Learn Automation Test</a></li>
              
            </ol>
          `,
        },
      };

      const data = NOTES[id] || {
        title: "Unknown",
        subtitle: "—",
        html: "<p>No details available</p>",
      };

      document.getElementById("cTitle").textContent = data.title;
      document.getElementById("cSubtitle").textContent = data.subtitle;
      document.getElementById("noteBody").innerHTML = data.html;

      document.getElementById("btnDone").addEventListener("click", () => {
        if (window.opener) {
          window.close();
        } else {
          location.href = "courses.html";
        }
      });
    </script>
  </body>
</html>
