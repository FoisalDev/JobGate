<?php
require_once 'db_connect.php';

$error = '';
$email = '';
$user_type = ''; // Will be used to store the user type upon successful login

// Check if user is already logged in, redirect to their main page if true
if (is_logged_in()) {
    // Redirect logged-in users to their respective main pages
    if ($_SESSION['user_type'] === 'admin') {
        redirect('admin_dashboard.php');
    } elseif ($_SESSION['user_type'] === 'recruiter') {
        redirect('recruiter_profile.php');
    } elseif ($_SESSION['user_type'] === 'applicant') {
        redirect('profile.php');
    } else {
        // Fallback for any unknown user type
        redirect('home.php');
    }
}

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // 1. Basic Validation
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    }

    if (empty($error)) {
        // 2. Hash the input password using SHA256 for comparison
        $input_password_hash = hash('sha256', $password);

        // 3. Prepare and execute the query to find the user
        // Note: Assumed $conn is the correct database connection object
        $stmt = $conn->prepare("SELECT user_id, password_hash, user_type, full_name FROM Users WHERE email = ?");
        
        // --- ADDED DEBUGGING CHECK ---
        if ($stmt === false) {
            $error = "Database preparation failed. Check your db_connect.php or table name.";
        }
        // -----------------------------
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // 4. Compare the hashed password
            if ($user['password_hash'] === $input_password_hash) {
                // Password matches, login successful
                
                // 5. Start session and set session variables
                // session_start() is assumed to be called in db_connect.php
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['full_name'] = $user['full_name'];
                
                // 6. Redirect to the appropriate profile/dashboard page based on user type
                if ($user['user_type'] === 'admin') {
                    redirect('admin_dashboard.php'); 
                } elseif ($user['user_type'] === 'recruiter') {
                    redirect('recruiter_profile.php'); 
                } elseif ($user['user_type'] === 'applicant') {
                    redirect('profile.php'); 
                } else {
                    // Fallback for any unknown user type
                    redirect('home.php'); 
                }
            } else {
                // Password incorrect
                $error = "Invalid email or password. (Password Mismatch)"; // Added Mismatch info for debugging
            }
        } else {
            // Email not found
            $error = "Invalid email or password. (Email Not Found)"; // Added Not Found info for debugging
        }

        $stmt->close();
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
        
        <!-- PHP error message -->
        <?php if ($error): ?>
          <div style="background-color: #fca5a5; color: #991b1b; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-weight: 700; text-align: center;">
            <?php echo htmlspecialchars($error); ?>
          </div>
        <?php endif; ?>

        <form class="form" action="login.php" method="post">
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

          <!-- Remember + Forgot -->
          <div class="row-between">
            <label class="remember">
              <input type="checkbox" name="remember" /> <span>Remember me</span>
            </label>
            <a href="#" class="muted">Forgot Password?</a>
          </div>

          <!-- Sign In button -->
          <button
            class="btn-primary"
            type="submit"
          >
            Sign In
          </button>

          <!-- Alternative -->
          <div class="alt">Or continue with</div>

          <!-- Social Buttons -->
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
