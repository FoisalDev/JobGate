<?php
require_once 'db_connect.php';

$error = '';
$success = '';

// Check if user is already logged in, redirect to home.php if true
if (is_logged_in()) {
    redirect('home.php');
}

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $user_type = $_POST['user_type'];
    $country = $_POST['country']; 
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // 1. Basic Validation (Translated to English)
    if (empty($full_name) || empty($email) || empty($user_type) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all required fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Password confirmation does not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    }

    // If no initial errors, proceed with database checks
    if (empty($error)) {
        // 2. Check if email already exists
        $stmt_check = $conn->prepare("SELECT user_id FROM Users WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $error = "This email is already registered.";
        } else {
            // 3. Password Encryption (SHA256)
            $password_hash = hash('sha256', $password); 
            $new_user_id = generate_uuid(); // Generate Unique ID

            // 4. Insert data into Users table
            $stmt_insert_user = $conn->prepare(
                "INSERT INTO Users (user_id, email, password_hash, full_name, user_type) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt_insert_user->bind_param("sssss", $new_user_id, $email, $password_hash, $full_name, $user_type);

            if ($stmt_insert_user->execute()) {
                // 5. Insert data into corresponding sub-table based on user type
                $success_sub_table = true;
                if ($user_type === 'applicant') {
                    $new_applicant_id = generate_uuid();
                    $stmt_applicant = $conn->prepare(
                        "INSERT INTO Applicants (applicant_id, user_id, address) VALUES (?, ?, ?)"
                    );
                    // Using 'country' as initial 'address'
                    $initial_address = $country; 
                    $stmt_applicant->bind_param("sss", $new_applicant_id, $new_user_id, $initial_address);
                    if (!$stmt_applicant->execute()) {
                        $success_sub_table = false;
                        $error = "Registration failed: Could not insert Applicant data.";
                    }
                    $stmt_applicant->close();
                } elseif ($user_type === 'recruiter') {
                    $new_recruiter_id = generate_uuid();
                    // Default Company Name
                    $default_company_name = $full_name . ' Inc.'; 
                    $stmt_recruiter = $conn->prepare(
                        "INSERT INTO Recruiters (recruiter_id, user_id, company_name) VALUES (?, ?, ?)"
                    );
                    $stmt_recruiter->bind_param("sss", $new_recruiter_id, $new_user_id, $default_company_name);
                    if (!$stmt_recruiter->execute()) {
                        $success_sub_table = false;
                        $error = "Registration failed: Could not insert Recruiter data.";
                    }
                    $stmt_recruiter->close();
                }

                if ($success_sub_table) {
                    // 6. Success: Redirect to login page
                    $success = "Registration successful! Please log in now.";
                    echo "<script>setTimeout(function(){ window.location.href = 'login.php'; }, 1000);</script>";
                } else {
                    // Rollback if sub-table insertion failed
                    $conn->query("DELETE FROM Users WHERE user_id = '{$new_user_id}'"); 
                }

            } else {
                $error = "Registration failed: Server error.";
            }
            $stmt_insert_user->close();
        }
        $stmt_check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>JobGate — Sign Up</title>
    <link rel="stylesheet" href="signup.css" />
    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
  </head>
  <body>
    <!-- Top-left logo (same as index) -->
    <header class="topbar">
      <img src="./JobGate_logo.png" alt="JobGate" class="logo-only" />
    </header>

    <main class="container hero">
      <!-- Left section (keep same look as index) -->
      <section class="left">
        <img src="./Human_Figure.png" alt="Graduate" class="figure" />

        <h1 class="title">Welcome To<br />JobGate</h1>
        <p class="sub">Get tested, get trained, and apply with confidence.</p>
        <p class="hint">
          <strong>Please log or register to get started.</strong>
        </p>
      </section>

      <!-- Right section: BIG signup card -->
      <aside class="card">
        <h2 class="card-title">Sign Up</h2>
        <p class="card-sub">Create your JobGate account</p>
        
        <!-- PHP error/success messages -->
        <?php if ($error): ?>
          <div style="background-color: #fca5a5; color: #991b1b; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-weight: 700; text-align: center;">
            <?php echo htmlspecialchars($error); ?>
          </div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div style="background-color: #a7f3d0; color: #065f46; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-weight: 700; text-align: center;">
            <?php echo htmlspecialchars($success); ?>
          </div>
        <?php endif; ?>

        <form class="form" action="signup.php" method="post" onsubmit="return true;">
          <!-- Name -->
          <label class="field">
            <div class="input-wrap">
              <input type="text" name="name" placeholder="Name" required value="<?php echo htmlspecialchars($full_name ?? ''); ?>" />
            </div>
          </label>

          <!-- Email -->
          <label class="field">
            <div class="input-wrap">
              <input type="email" name="email" placeholder="Email" required value="<?php echo htmlspecialchars($email ?? ''); ?>" />
            </div>
          </label>

          <!-- User Type -->
          <label class="field">
            <div class="input-wrap select">
              <select name="user_type" required>
                <option value="" hidden>Select User Type</option>
                <option value="applicant" <?php echo (isset($user_type) && $user_type == 'applicant') ? 'selected' : ''; ?>>Applicant</option>
                <option value="recruiter" <?php echo (isset($user_type) && $user_type == 'recruiter') ? 'selected' : ''; ?>>Recruiter</option>
              </select>
              <iconify-icon icon="mdi:chevron-down" class="chev"></iconify-icon>
            </div>
          </label>

          <!-- Country (used as initial address for applicants, ignored for recruiters) -->
          <label class="field">
            <div class="input-wrap select">
              <select name="country" required>
                <option value="" hidden>Country</option>
                <option value="Bangladesh" <?php echo (isset($country) && $country == 'Bangladesh') ? 'selected' : ''; ?>>Bangladesh</option>
                <option value="India" <?php echo (isset($country) && $country == 'India') ? 'selected' : ''; ?>>India</option>
                <option value="United States" <?php echo (isset($country) && $country == 'United States') ? 'selected' : ''; ?>>United States</option>
              </select>
              <iconify-icon icon="mdi:chevron-down" class="chev"></iconify-icon>
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
                aria-label="Toggle password"
                onclick="togglePwd('pwd','eye1')"
              >
                <iconify-icon
                  id="eye1"
                  icon="mdi:eye-off-outline"
                ></iconify-icon>
              </button>
            </div>
          </label>

          <!-- Confirm Password -->
          <label class="field">
            <div class="input-wrap">
              <input
                type="password"
                id="cpwd"
                name="confirm_password"
                placeholder="Confirm Password"
                required
              />
              <button
                type="button"
                class="eye"
                aria-label="Toggle confirm password"
                onclick="togglePwd('cpwd','eye2')"
              >
                <iconify-icon
                  id="eye2"
                  icon="mdi:eye-off-outline"
                ></iconify-icon>
              </button>
            </div>
          </label>

          <button
            class="btn-primary"
            type="submit"
            
          >
            Sign Up
          </button>
          <p class="alt">
            Already have an account? <a href="login.php">Login</a>
          </p>
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
