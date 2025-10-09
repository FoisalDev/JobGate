<?php
// profile.php — server-backed avatar + name (no design change)
session_start();
require_once 'db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!is_logged_in()) { redirect('login.php'); exit; }

$user_id   = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? 'applicant';
$full_name = $_SESSION['full_name'] ?? 'Your Name';

// ---- Ensure Users.profile_photo_url column exists (adds once if missing) ----
try {
  $chk = $conn->prepare("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='Users' AND COLUMN_NAME='profile_photo_url'");
  $chk->execute();
  $c = $chk->get_result()->fetch_assoc();
  $chk->close();
  if (empty($c['c'])) {
    $conn->query("ALTER TABLE Users ADD COLUMN profile_photo_url VARCHAR(255) NULL");
  }
} catch (Throwable $e) {
  // ignore schema add errors (shared hosting etc.)
}

// ---- Handle avatar upload ----
$profile_photo_url = null;
$msg = '';
try {
  // Load current value
  $stmt = $conn->prepare("SELECT profile_photo_url, full_name FROM Users WHERE user_id = ? LIMIT 1");
  $stmt->bind_param("s", $user_id);
  $stmt->execute();
  $info = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($info) {
    $profile_photo_url = $info['profile_photo_url'] ?: null;
    // refresh full_name in session (if changed elsewhere)
    if (!empty($info['full_name'])) {
      $full_name = $info['full_name'];
      $_SESSION['full_name'] = $full_name;
    }
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_avatar') {
    if (!empty($_FILES['avatar_file']['name']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
      $dirFs  = __DIR__ . '/uploads/profile_photos/';
      $dirWeb = 'uploads/profile_photos/';
      if (!is_dir($dirFs)) { mkdir($dirFs, 0777, true); }

      $info = @getimagesize($_FILES['avatar_file']['tmp_name']);
      if ($info === false) { throw new Exception("Invalid image file."); }

      $ext = strtolower(pathinfo($_FILES['avatar_file']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
        throw new Exception("Only JPG, JPEG, PNG, GIF, WEBP allowed.");
      }
      if ($_FILES['avatar_file']['size'] > 2*1024*1024) {
        throw new Exception("File too large (max 2MB).");
      }

      // unique filename
      $name = bin2hex(random_bytes(8)).'.'.$ext;
      if (!move_uploaded_file($_FILES['avatar_file']['tmp_name'], $dirFs.$name)) {
        throw new Exception("Failed to save file. Check folder permissions.");
      }

      $newUrl = $dirWeb.$name;

      // Update DB
      $up = $conn->prepare("UPDATE Users SET profile_photo_url = ? WHERE user_id = ?");
      $up->bind_param("ss", $newUrl, $user_id);
      $up->execute(); $up->close();

      // Reflect immediately
      $profile_photo_url = $newUrl;
      $msg = 'Profile photo updated.';
    } else {
      throw new Exception("Please select a valid image.");
    }

    // Hard redirect to avoid resubmission + to refresh <img>
    header("Location: profile.php");
    exit;
  }
} catch (Throwable $e) {
  $msg = $e->getMessage();
}

// Decide header profile link
$profilePage = ($user_type === 'recruiter') ? 'recruiter_profile.php' : 'profile.php';

// Avatar fallback
$avatarSrc = $profile_photo_url ?: './avatar_placeholder.jpg';

// Split name (first + last) just for header display if needed later
function split_name($full) {
  $full = trim($full);
  if ($full === '') return ['first'=>'Your', 'last'=>'Name'];
  $parts = preg_split('/\s+/', $full);
  $first = array_shift($parts);
  $last = count($parts) ? implode(' ', $parts) : '';
  return ['first'=>$first, 'last'=>$last];
}
$nm = split_name($full_name);
?>
<!DOCTYPE html>
<html lang="bn">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>JobGate — Profile</title>

    <link rel="stylesheet" href="profile.css" />
    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
  </head>
  <body>
    <header class="topbar">
      <div class="topbar-inner">
        <img src="./JobGate_logo.png" alt="JobGate" class="logo" />
        <div class="search-wrap" role="search">
          <iconify-icon icon="mdi:magnify" class="sicon" aria-hidden="true"></iconify-icon>
          <input type="text" placeholder="Search JobGate" aria-label="Search JobGate" />
        </div>
        <nav class="top-actions" aria-label="Top actions">
          <a href="home.php" class="tlink">Home</a>
          <a href="<?php echo htmlspecialchars($profilePage); ?>" class="tlink">Profile</a>
          <!-- Header avatar from DB -->
          <img src="<?php echo htmlspecialchars($avatarSrc); ?>" class="avatar" alt="User avatar" />
        </nav>
      </div>
    </header>

    <div class="layout">
      <aside class="sidebar">
        <button class="sbtn" onclick="window.location.href='home.php'">
          <iconify-icon icon="mdi:home" class="sib"></iconify-icon>Feed
        </button>
        <button class="sbtn" onclick="window.location.href='career_tips.php'">
          <iconify-icon icon="mdi:lightbulb-on-outline" class="sib"></iconify-icon>Career Tips
        </button>
        <button class="sbtn" onclick="window.location.href='job_events.php'">
          <iconify-icon icon="mdi:calendar-star" class="sib"></iconify-icon>Job Events
        </button>
        <button class="sbtn" onclick="window.location.href='courses.php'">
          <iconify-icon icon="mdi:book-open-variant" class="sib"></iconify-icon>Courses
        </button>
        <button class="sbtn" onclick="window.location.href='skill_assessment.php'">
          <iconify-icon icon="mdi:account-check-outline" class="sib"></iconify-icon>Skill Assessment
        </button>
        <button class="sbtn" onclick="window.location.href='jobs.php'">
          <iconify-icon icon="mdi:briefcase-outline" class="sib"></iconify-icon>Jobs
        </button>
        <div class="spacer"></div>
        <button class="sbtn logout" onclick="window.location.href='logout.php'">
          <iconify-icon icon="mdi:logout" class="sib"></iconify-icon>Log out
        </button>
      </aside>

      <main class="content">
        <?php if (!empty($msg)): ?>
          <div style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;padding:10px 12px;border-radius:10px;margin-bottom:10px;">
            <?php echo htmlspecialchars($msg); ?>
          </div>
        <?php endif; ?>

        <section class="profile-head">
          <div class="ph-left">
            <div class="avatar-lg">
              <!-- Big profile avatar from DB -->
              <img id="phAvatar" src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="avatar" />
              <button class="cam" id="btnAvatar" title="Upload photo from device">
                <iconify-icon icon="mdi:camera"></iconify-icon>
              </button>
              <!-- Hidden form for avatar upload (no design change) -->
              <form id="avatarForm" method="POST" action="profile.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_avatar">
                <input type="file" name="avatar_file" id="avatarInput" accept="image/*" hidden />
              </form>
            </div>
            <figcaption id="quote">“Life is a full of journey”</figcaption>
          </div>

          <div class="ph-mid">
            <!-- Auto name from DB -->
            <h1 id="fullName"><?php echo htmlspecialchars($full_name); ?></h1>
            <ul class="facts">
              <li><iconify-icon icon="mdi:map-marker-outline"></iconify-icon>
                Address: <span id="locLine">—</span>
              </li>
              <li><iconify-icon icon="mdi:briefcase-variant-outline"></iconify-icon>
                Works at: <span id="workLine">—</span>
              </li>
              <li><iconify-icon icon="mdi:school-outline"></iconify-icon>
                Studies: <span id="eduLine">—</span>
              </li>
              <li><iconify-icon icon="mdi:star-outline"></iconify-icon>
                Skills: <span id="skillsLine">—</span>
              </li>
              <li><iconify-icon icon="mdi:file-document-outline"></iconify-icon>
                <span id="resumeLine">Resume — Not generated</span>
              </li>
              <li><iconify-icon icon="mdi:check-decagram-outline"></iconify-icon>
                Profile complete: <strong id="complete">0%</strong>
              </li>
            </ul>
          </div>

          <div class="ph-right">
            <button id="btnEdit" class="btn-edit">
              <iconify-icon icon="mdi:pencil"></iconify-icon> Edit
            </button>
            <button id="btnGenerate" class="btn-primary">
              <iconify-icon icon="mdi:magic-staff"></iconify-icon> Generate Resume
            </button>
            <button id="btnDownload" class="btn-success" disabled>
              <iconify-icon icon="mdi:download"></iconify-icon> Download PDF
            </button>
          </div>
        </section>

        <!-- ====== rest of your original form & resume card (UNCHANGED) ====== -->


        <section class="grid">
          <form id="form" class="card form">
            <h3 class="card-title">Profile Editor</h3>

            <div class="row2">
              <label
                >Full Name
                <input
                  type="text"
                  id="fName"
                  placeholder="e.g., Md. Foisal Arefin"
                />
              </label>
              <label
                >Location / Address
                <input
                  type="text"
                  id="fLocation"
                  placeholder="e.g., Dhaka, Bangladesh"
                />
              </label>
            </div>

            <div class="row2">
              <label
                >Current Role / Org
                <input
                  type="text"
                  id="fWork"
                  placeholder="e.g., Junior Web Developer at XYZ"
                />
              </label>
              <label
                >Education (short line)
                <input
                  type="text"
                  id="fEdu"
                  placeholder="e.g., BSc in CSE, UIU"
                />
              </label>
            </div>

            <label
              >Short Quote
              <input
                type="text"
                id="fQuote"
                placeholder="e.g., Always learning, always building."
              />
            </label>

            <label
              >About Me
              <textarea
                id="fAbout"
                rows="4"
                placeholder="e.g., Passionate developer with strong problem-solving skills..."
              ></textarea>
            </label>

            <label
              >Skills (press Enter to add)
              <div class="tagbox">
                <input
                  id="fSkillInput"
                  type="text"
                  placeholder="e.g., HTML, CSS, JavaScript, React"
                />
                <div id="skillList" class="tags"></div>
              </div>
            </label>

            <div class="group">
              <h4>Education Entries</h4>
              <div
                id="eduList"
                class="list empty-hint"
                data-hint="No education added yet. Use the inputs below."
              ></div>
              <div class="row2">
                <input
                  id="eduTitle"
                  type="text"
                  placeholder="e.g., BSc in CSE"
                />
                <input
                  id="eduMeta"
                  type="text"
                  placeholder="e.g., United International University, 2021–2025"
                />
              </div>
              <button type="button" class="btn-secondary" id="addEdu">
                Add Education
              </button>
            </div>

            <div class="group">
              <h4>Experience</h4>
              <div
                id="expList"
                class="list empty-hint"
                data-hint="No experience added yet. Use the inputs below."
              ></div>
              <div class="row2">
                <input
                  id="expTitle"
                  type="text"
                  placeholder="e.g., Frontend Intern"
                />
                <input
                  id="expMeta"
                  type="text"
                  placeholder="e.g., ABC Tech, Jun 2024 – Sep 2024"
                />
              </div>
              <button type="button" class="btn-secondary" id="addExp">
                Add Experience
              </button>
            </div>

            <div class="group">
              <h4>Links</h4>
              <div
                id="linkList"
                class="list empty-hint"
                data-hint="No links yet. Add Portfolio, GitHub, LinkedIn…"
              ></div>
              <div class="row2">
                <input
                  id="linkText"
                  type="text"
                  placeholder="e.g., Portfolio"
                />
                <input
                  id="linkUrl"
                  type="url"
                  placeholder="e.g., https://your-portfolio.com"
                />
              </div>
              <button type="button" class="btn-secondary" id="addLink">
                Add Link
              </button>
            </div>

            <div class="actions">
              <button type="button" class="btn-primary" id="saveBtn">
                Save
              </button>
              <button type="button" class="btn-ghost" id="clearBtn">
                Reset
              </button>
            </div>
          </form>

          <section id="resumeCard" class="card resume resume-print">
            <div class="resume-head">
              <div class="r-left">
                <img id="rAvatar" src="./avatar_placeholder.jpg" alt="avatar" />
              </div>
              <div class="r-mid">
                <h2 id="rName">Your Name</h2>
                <p id="rTitle">—</p>
              </div>
            </div>

            <div class="resume-body">
              <div class="r-block">
                <h4>About Me</h4>
                <p id="rAbout">—</p>
              </div>

              <div class="r-block">
                <h4>Skills</h4>
                <ul id="rSkills" class="r-skill-list"></ul>
              </div>

              <div class="r-block">
                <h4>Education</h4>
                <ul id="rEdu" class="r-list"></ul>
              </div>

              <div class="r-block">
                <h4>Experience</h4>

                <div class="address-block">
                  <div class="addr-title">Address</div>
                  <div class="addr-text" id="rAddress">—</div>
                </div>

                <ul id="rExp" class="r-list"></ul>
              </div>

              <div class="r-block" id="rLinksBlock" style="display: none">
                <h4>Links</h4>
                <ul id="rLinks" class="r-list"></ul>
              </div>
            </div>
          </section>
        </section>
      </main>
    </div>

    <script>
        // ---- minimal JS to trigger upload without UI change ----
        const btnAvatar = document.getElementById('btnAvatar');
      const avatarInput = document.getElementById('avatarInput');
      const avatarForm = document.getElementById('avatarForm');

      btnAvatar?.addEventListener('click', () => avatarInput?.click());
      avatarInput?.addEventListener('change', () => {
        if (avatarInput.files && avatarInput.files[0]) {
          avatarForm.submit(); // post to server -> saves -> redirects
        }
      });

      // ====== State & storage ======
      const KEY = "jobgate_profile_v9";
      const load = () => JSON.parse(localStorage.getItem(KEY) || "null");
      const save = (d) => localStorage.setItem(KEY, JSON.stringify(d));
      const clear = () => localStorage.removeItem(KEY);

      let state = load() || {
        name: "Your Name",
        location: "",
        work: "",
        eduLine: "",
        quote: "",
        about: "",
        avatar: "./avatar_placeholder.jpg",
        skills: [],
        education: [],
        experience: [],
        links: [],
        generated: false,
      };

      // ====== DOM refs ======
      const fullName = document.getElementById("fullName");
      const quote = document.getElementById("quote");
      const phAvatar = document.getElementById("phAvatar");
      const locLine = document.getElementById("locLine");
      const workLine = document.getElementById("workLine");
      const eduLine = document.getElementById("eduLine");
      const skillsLine = document.getElementById("skillsLine");
      const resumeLine = document.getElementById("resumeLine");
      const complete = document.getElementById("complete");

      const fName = document.getElementById("fName");
      const fLocation = document.getElementById("fLocation");
      const fWork = document.getElementById("fWork");
      const fEdu = document.getElementById("fEdu");
      const fQuote = document.getElementById("fQuote");
      const fAbout = document.getElementById("fAbout");
      const fSkillInput = document.getElementById("fSkillInput");
      const skillList = document.getElementById("skillList");
      const eduList = document.getElementById("eduList");
      const expList = document.getElementById("expList");
      const linkList = document.getElementById("linkList");

      const rAvatar = document.getElementById("rAvatar");
      const rName = document.getElementById("rName");
      const rTitle = document.getElementById("rTitle");
      const rAbout = document.getElementById("rAbout");
      const rSkills = document.getElementById("rSkills");
      const rEdu = document.getElementById("rEdu");
      const rExp = document.getElementById("rExp");
      const rAddress = document.getElementById("rAddress");
      const rLinks = document.getElementById("rLinks");
      const rLinksBlock = document.getElementById("rLinksBlock");

      const btnEdit = document.getElementById("btnEdit");
      const btnGenerate = document.getElementById("btnGenerate");
      const btnDownload = document.getElementById("btnDownload");
      const btnSave = document.getElementById("saveBtn");
      const btnReset = document.getElementById("clearBtn");

      const avatarInput = document.getElementById("avatarInput");
      const btnAvatar = document.getElementById("btnAvatar");

      // ====== Helpers ======
      const pct = (s) => {
        let n = 0;
        if (s.name && s.name !== "Your Name") n += 20;
        if (s.about) n += 20;
        if (s.skills.length) n += 20;
        if (s.education.length || s.experience.length) n += 20;
        if (s.location) n += 20;
        return n;
      };

      const hintIfEmpty = (el, arr) => {
        if (!arr.length) el.classList.add("show-hint");
        else el.classList.remove("show-hint");
      };

      function pill(text, i) {
        const span = document.createElement("span");
        span.className = "pill";
        span.innerHTML = `${text} <button type="button" class="pill-x" aria-label="Remove">×</button>`;
        span.querySelector("button").onclick = () => {
          state.skills.splice(i, 1);
          syncFormDataToState(); // ADDED: Sync before re-render
          save(state);
          renderAll();
        };
        return span;
      }

      function listItem(text, sub, idx, onRemove) {
        const div = document.createElement("div");
        div.className = "li";
        div.innerHTML = `<span class="t">${text}</span><span class="s">${
          sub || ""
        }</span>`;
        const x = document.createElement("button");
        x.type = "button";
        x.className = "x";
        x.textContent = "×";
        x.onclick = () => {
          onRemove(idx);
          syncFormDataToState(); // ADDED: Sync before re-render
          save(state);
          renderAll();
        };
        div.appendChild(x);
        return div;
      }

      // ** NEW FUNCTION: Syncs form fields to state **
      const syncFormDataToState = () => {
        state.name = fName.value.trim() || "Your Name";
        state.location = fLocation.value.trim();
        state.work = fWork.value.trim();
        state.eduLine = fEdu.value.trim();
        state.quote = fQuote.value.trim();
        state.about = fAbout.value.trim();
      };

      // ====== Renders ======
      function renderProfile() {
        fullName.textContent = state.name;
        quote.textContent = state.quote || "“Life is a full of journey”";
        phAvatar.src = state.avatar;

        locLine.textContent = state.location || "—";
        workLine.textContent = state.work || "—";
        eduLine.textContent = state.eduLine || "—";
        skillsLine.textContent = state.skills.length
          ? state.skills.join(", ")
          : "—";
        resumeLine.textContent = state.generated
          ? "Resume — Ready"
          : "Resume — Not generated";
        complete.textContent = pct(state) + "%";

        // enable download if at least 40% completion
        btnDownload.disabled = pct(state) < 40;
      }

      function renderEditor() {
        // These lines are why the data was clearing: they overwrite
        // the current input with the value from state *if you hadn't saved yet*
        fName.value = state.name;
        fLocation.value = state.location;
        fWork.value = state.work;
        fEdu.value = state.eduLine;
        fQuote.value = state.quote;
        fAbout.value = state.about;

        skillList.innerHTML = "";
        state.skills.forEach((s, i) => skillList.appendChild(pill(s, i)));
        hintIfEmpty(skillList, state.skills);

        eduList.innerHTML = "";
        state.education.forEach((e, i) => {
          eduList.appendChild(
            listItem(e.title, e.meta, i, (idx) => {
              state.education.splice(idx, 1);
              // Removal calls syncFormDataToState within listItem's onclick
            })
          );
        });
        hintIfEmpty(eduList, state.education);

        expList.innerHTML = "";
        state.experience.forEach((e, i) => {
          expList.appendChild(
            listItem(e.title, e.meta, i, (idx) => {
              state.experience.splice(idx, 1);
              // Removal calls syncFormDataToState within listItem's onclick
            })
          );
        });
        hintIfEmpty(expList, state.experience);

        linkList.innerHTML = "";
        state.links.forEach((l, i) => {
          const div = document.createElement("div");
          div.className = "li";
          div.innerHTML = `<a class="t" href="${l.url}" target="_blank" rel="noopener">${l.text}</a><span class="s">${l.url}</span>`;
          const x = document.createElement("button");
          x.type = "button";
          x.className = "x";
          x.textContent = "×";
          x.onclick = () => {
            state.links.splice(i, 1);
            syncFormDataToState(); // ADDED: Sync before re-render
            save(state);
            renderAll();
          };
          div.appendChild(x);
          linkList.appendChild(div);
        });
        hintIfEmpty(linkList, state.links);
      }

      function renderResume() {
        rAvatar.src = state.avatar;
        rName.textContent = state.name;
        rTitle.textContent = state.work || "—";
        rAbout.textContent = state.about || "—";

        rSkills.innerHTML =
          state.skills.map((s) => `<li>${s}</li>`).join("") || "<li>—</li>";
        rEdu.innerHTML =
          state.education
            .map(
              (e) =>
                `<li><strong>${e.title}</strong> <span>${e.meta}</span></li>`
            )
            .join("") || "<li>—</li>";
        rExp.innerHTML =
          state.experience
            .map(
              (e) =>
                `<li><strong>${e.title}</strong> <span>${e.meta}</span></li>`
            )
            .join("") || "<li>—</li>";

        // Address block under "Experience" heading (as requested)
        rAddress.textContent = state.location || "—";

        // Links block shows only if we have any
        if (state.links.length) {
          rLinksBlock.style.display = "";
          rLinks.innerHTML = state.links
            .map(
              (l) => `<li><a href="${l.url}" target="_blank">${l.text}</a></li>`
            )
            .join("");
        } else {
          rLinksBlock.style.display = "none";
          rLinks.innerHTML = "";
        }
      }

      function renderAll() {
        renderProfile();
        renderEditor();
        renderResume();
      }

      // ====== Events ======

      // ** UPDATED: saveBtn click now just calls the shared sync function and saves **
      document.getElementById("saveBtn").onclick = () => {
        syncFormDataToState();
        state.generated = false;
        save(state);
        renderAll();
      };

      document.getElementById("clearBtn").onclick = () => {
        if (!confirm("Reset everything? This clears your saved profile."))
          return;
        clear();
        state = {
          name: "Your Name",
          location: "",
          work: "",
          eduLine: "",
          quote: "",
          about: "",
          avatar: "./avatar_placeholder.jpg",
          skills: [],
          education: [],
          experience: [],
          links: [],
          generated: false,
        };
        renderAll();
      };

      document.getElementById("btnEdit").onclick = () => {
        document
          .querySelector(".form")
          .scrollIntoView({ behavior: "smooth", block: "start" });
        fName.focus();
      };

      document.getElementById("btnGenerate").onclick = () => {
        // ** ADDED: Sync form data before checking completeness and generating **
        syncFormDataToState();
        if (pct(state) < 50) {
          alert(
            "Please fill more fields (Name, Address, About, some Skills, and at least one Education/Experience)."
          );
          return;
        }
        state.generated = true;
        save(state);
        renderAll();
        document
          .getElementById("resumeCard")
          .scrollIntoView({ behavior: "smooth" });
      };

      // ** UPDATED: Add rows now calls syncFormDataToState() before saving **
      document.getElementById("addEdu").onclick = () => {
        const t = document.getElementById("eduTitle").value.trim();
        const m = document.getElementById("eduMeta").value.trim();
        if (!t) return;
        state.education.push({ title: t, meta: m });
        document.getElementById("eduTitle").value = "";
        document.getElementById("eduMeta").value = "";
        state.generated = false;
        syncFormDataToState(); // ADDED: Sync data before saving and rendering
        save(state);
        renderAll();
      };
      document.getElementById("addExp").onclick = () => {
        const t = document.getElementById("expTitle").value.trim();
        const m = document.getElementById("expMeta").value.trim();
        if (!t) return;
        state.experience.push({ title: t, meta: m });
        document.getElementById("expTitle").value = "";
        document.getElementById("expMeta").value = "";
        state.generated = false;
        syncFormDataToState(); // ADDED: Sync data before saving and rendering
        save(state);
        renderAll();
      };
      document.getElementById("addLink").onclick = () => {
        const text = document.getElementById("linkText").value.trim();
        const url = document.getElementById("linkUrl").value.trim();
        if (!text || !url) return;
        const safeUrl = /^https?:\/\//i.test(url) ? url : "https://" + url;
        state.links.push({ text, url: safeUrl });
        document.getElementById("linkText").value = "";
        document.getElementById("linkUrl").value = "";
        state.generated = false;
        syncFormDataToState(); // ADDED: Sync data before saving and rendering
        save(state);
        renderAll();
      };

      // ** UPDATED: Skills add on Enter now calls syncFormDataToState() before saving **
      fSkillInput.addEventListener("keydown", (e) => {
        if (e.key === "Enter") {
          e.preventDefault();
          const v = fSkillInput.value.trim();
          if (!v) return;
          state.skills.push(v);
          fSkillInput.value = "";
          state.generated = false;
          syncFormDataToState(); // ADDED: Sync data before saving and rendering
          save(state);
          renderAll();
        }
      });

      // Avatar: local file -> Base64
      btnAvatar.onclick = () => avatarInput.click();
      avatarInput.addEventListener("change", (e) => {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = (ev) => {
          state.avatar = ev.target.result; // dataURL
          state.generated = false;
          syncFormDataToState(); // ADDED: Sync data before saving and rendering
          save(state);
          renderAll();
        };
        reader.readAsDataURL(file);
      });

      // ====== PDF Download (jsPDF) ======
      document.getElementById("btnDownload").onclick = () => {
        // ** ADDED: Sync form data before checking completeness and downloading **
        syncFormDataToState();
        if (pct(state) < 40) {
          alert("Please complete more fields before downloading.");
          return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ unit: "pt", format: "a4" });
        const margin = 56; // 0.78in
        const lineGap = 18;
        let y = margin;

        // Header
        doc.setFont("helvetica", "bold");
        doc.setFontSize(20);
        doc.text(state.name || "Your Name", margin, y);
        y += 24;

        doc.setFont("helvetica", "normal");
        doc.setFontSize(12);
        doc.text(state.work || "—", margin, y);
        y += 10;

        // Divider
        y += 8;
        doc.setDrawColor(220);
        doc.line(margin, y, 595 - margin, y);
        y += 20;

        // About
        doc.setFont("helvetica", "bold");
        doc.setFontSize(13);
        doc.text("About Me", margin, y);
        y += 16;
        doc.setFont("helvetica", "normal");
        doc.setFontSize(11);
        const aboutTxt = doc.splitTextToSize(
          state.about || "—",
          595 - margin * 2
        );
        doc.text(aboutTxt, margin, y);
        y += aboutTxt.length * 14 + 10;

        // Skills
        doc.setFont("helvetica", "bold");
        doc.setFontSize(13);
        doc.text("Skills", margin, y);
        y += 16;
        doc.setFont("helvetica", "normal");
        doc.setFontSize(11);
        const skillsTxt = state.skills.length ? state.skills.join(", ") : "—";
        const skillsWrapped = doc.splitTextToSize(skillsTxt, 595 - margin * 2);
        doc.text(skillsWrapped, margin, y);
        y += skillsWrapped.length * 14 + 10;

        // Education
        doc.setFont("helvetica", "bold");
        doc.setFontSize(13);
        doc.text("Education", margin, y);
        y += 16;
        doc.setFont("helvetica", "normal");
        doc.setFontSize(11);
        if (state.education.length) {
          state.education.forEach((ed) => {
            doc.text(`• ${ed.title} — ${ed.meta || ""}`, margin, y);
            y += lineGap;
          });
        } else {
          doc.text("—", margin, y);
          y += lineGap;
        }

        // Experience
        y += 4;
        doc.setFont("helvetica", "bold");
        doc.setFontSize(13);
        doc.text("Experience", margin, y);
        y += 16;

        // Address under "Experience"
        doc.setFont("helvetica", "bold");
        doc.setFontSize(12);
        doc.text("Address", margin, y);
        y += 14;
        doc.setFont("helvetica", "normal");
        doc.setFontSize(11);
        const addr = state.location || "—";
        const addrLines = doc.splitTextToSize(addr, 595 - margin * 2);
        doc.text(addrLines, margin, y);
        y += addrLines.length * 14 + 6;

        doc.setFont("helvetica", "normal");
        doc.setFontSize(11);
        if (state.experience.length) {
          state.experience.forEach((ex) => {
            const line = `• ${ex.title} — ${ex.meta || ""}`;
            const wrap = doc.splitTextToSize(line, 595 - margin * 2);
            doc.text(wrap, margin, y);
            y += wrap.length * 14 + 4;
          });
        } else {
          doc.text("—", margin, y);
          y += lineGap;
        }

        // Links (if any)
        if (state.links.length) {
          y += 8;
          doc.setFont("helvetica", "bold");
          doc.setFontSize(13);
          doc.text("Links", margin, y);
          y += 16;
          doc.setFont("helvetica", "normal");
          doc.setFontSize(11);
          state.links.forEach((l) => {
            const txt = `${l.text}: ${l.url}`;
            const wrap = doc.splitTextToSize(txt, 595 - margin * 2);
            doc.text(wrap, margin, y);
            y += wrap.length * 14 + 2;
          });
        }

        // Optional avatar (if dataURL and not huge)
        try {
          if (state.avatar && state.avatar.startsWith("data:image/")) {
            // place small avatar top-right
            const imgW = 64,
              imgH = 64;
            doc.addImage(
              state.avatar,
              "PNG",
              595 - margin - imgW,
              margin - 8,
              imgW,
              imgH
            );
          }
        } catch (e) {
          /* ignore image errors */
        }

        const filename = `${(state.name || "resume").replace(
          /[^a-z0-9]+/gi,
          "_"
        )}_Resume.pdf`;
        doc.save(filename);
      };

      // Mount
      renderAll();
    </script>
  </body>
</html>
