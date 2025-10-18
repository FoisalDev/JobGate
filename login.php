<?php
// login.php (updated)
session_start();
require_once 'db_connect.php';

$error = '';
$email = '';

// যদি আগেই লগইন করা থাকে, রোল দেখে রিডাইরেক্ট
if (is_logged_in()) {
    if (!empty($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
        redirect('admin.php');
    } else {
        redirect('home.php');
    }
}

// POST সাবমিশন এলে প্রোসেস
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // 1) বেসিক ভ্যালিডেশন
    if ($email === '' || $password === '') {
        $error = "Please enter both email and password.";
    }

    if ($error === '') {
        // 2) ইউজার লোড
        // NOTE: এখানে ধরে নেওয়া হয়েছে যে Users টেবিলে column নাম: password_hash (bcrypt/argon),
        // লিগ্যাসি ক্ষেত্রে একই কলামে SHA-256 হ্যাশ থাকতে পারে— তাই fallback রাখা হয়েছে।
        $stmt = $conn->prepare("
            SELECT user_id, full_name, email, user_type, password_hash
            FROM Users
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();
        $stmt->close();

        if ($user) {
            $db_hash = $user['password_hash'] ?? '';

            $ok = false;

            // 3) প্রথমে আধুনিক হ্যাশ (password_hash) চেক
            if ($db_hash && strlen($db_hash) > 0 && password_get_info($db_hash)['algo']) {
                // bcrypt/argon হলে
                $ok = password_verify($password, $db_hash);
            } else {
                // 4) লিগ্যাসি fallback: SHA-256 তুলনা (তোমার পুরনো সিস্টেমের সাথে সামঞ্জস্য)
                $input_sha256 = hash('sha256', $password);
                $ok = hash_equals($db_hash, $input_sha256);
            }

            if ($ok) {
                // 5) সেশন সেট
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['full_name'] = $user['full_name'];

                // প্রোফাইল পেজ hint (ইচ্ছা করলে ব্যবহার করো)
                $_SESSION['profile_page'] = ($user['user_type'] === 'recruiter')
                    ? 'recruiter_profile.php'
                    : 'profile.php';

                // 6) রোল অনুযায়ী রিডাইরেক্ট
                if ($user['user_type'] === 'admin') {
                    redirect('admin.php');   // ✅ অ্যাডমিন হলে সরাসরি admin.php
                } else {
                    redirect('home.php');
                }
            } else {
                $error = "Invalid email or password.";
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>JobGate — Log In</title>
    <link rel="stylesheet" href="login.css" />
    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
  </head>
  <body>
    <!-- Top Logo -->
    <header class="topbar">
      <img src="./JobGate_logo.png" alt="JobGate Logo" class="logo-only" />
    </header>

    <main class="container hero">
      <!-- Left Section -->
      <section class="left">
        <img src="./Human_Figure.png" alt="Graduate" class="figure" />
        <h1 class="title">Good to see you again</h1>
        <p class="sub">
          Don’t have an account? <a href="signup.php">Create now</a>
        </p>
      </section>

      <!-- Right Section: Login Card -->
      <aside class="card">
        <h2 class="card-title">Log In</h2>
        <p class="card-sub">Welcome back to JobGate</p>

        <?php if ($error): ?>
          <div style="background-color: #fca5a5; color: #991b1b; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-weight: 700; text-align: center;">
            <?php echo htmlspecialchars($error); ?>
          </div>
        <?php endif; ?>

        <form class="form" action="login.php" method="post" novalidate>
          <!-- Email -->
          <label class="field">
            <div class="input-wrap">
              <input type="email" name="email" placeholder="Email" required value="<?php echo htmlspecialchars($email); ?>" />
            </div>
          </label>

          <!-- Password -->
          <label class="field">
            <div class="input-wrap">
              <input
                type="password"
                id="pwd"
                name="password"
                placeholder="Password"
                required
                autocomplete="current-password"
              />
              <button
                type="button"
                class="eye"
                onclick="togglePwd('pwd','eye1')"
                aria-label="Toggle password"
              >
                <iconify-icon
                  id="eye1"
                  icon="mdi:eye-off-outline"
                ></iconify-icon>
              </button>
            </div>
          </label>

          <div class="row-between">
            <label class="remember">
              <input type="checkbox" name="remember" /> <span>Remember me</span>
            </label>
            <a href="#" class="muted">Forgot Password?</a>
          </div>

          <button class="btn-primary" type="submit">Sign In</button>

          <div class="alt">Or continue with</div>

          <button type="button" class="btn-social">
            <iconify-icon icon="logos:google-icon"></iconify-icon>
            Continue with Google
          </button>
          <button type="button" class="btn-social">
            <iconify-icon icon="logos:facebook"></iconify-icon>
            Continue with Facebook
          </button>
        </form>
      </aside>
    </main>

    <script>
      function togglePwd(inputId, iconId) {
        const inp = document.getElementById(inputId);
        const ico = document.getElementById(iconId);
        if (inp.type === "password") {
          inp.type = "text";
          ico.setAttribute("icon", "mdi:eye-outline");
        } else {
          inp.type = "password";
          ico.setAttribute("icon", "mdi:eye-off-outline");
        }
      }
    </script>
  </body>
</html>

<!-- admin1@gmail.com - adminpass1 -->