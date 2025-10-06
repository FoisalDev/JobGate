<?php
// Start session for user authentication and state management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection Details for XAMPP (Local Development)
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', ''); // XAMPP default password is often empty
define('DB_NAME', 'jobgate');

// Attempt to connect to MySQL database
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn === false) {
    die("ERROR: Could not connect to database. " . $conn->connect_error);
}

/**
 * Generates a standard UUID (Universally Unique Identifier) which is required for CHAR(36) IDs in the database.
 * This is a simple implementation, a more robust solution might use a library like Ramsey/Uuid.
 */
function generate_uuid() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Check if a user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Function to redirect the user
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Define the current user's role (defaults to 'guest' if not logged in)
$current_user_id = $_SESSION['user_id'] ?? null;
$current_user_type = $_SESSION['user_type'] ?? 'guest';

// Utility function to get user info by ID (useful for headers/sidebars)
function get_user_info($conn, $user_id) {
    $stmt = $conn->prepare("SELECT user_id, full_name, user_type FROM Users WHERE user_id = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user;
}

?>
