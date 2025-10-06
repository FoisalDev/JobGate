<?php
// Start the session to access session variables
session_start();

// Destroy all data registered to the session
session_unset();

// Destroy the session itself
session_destroy();

// Redirect to the login page after successful logout
header("location: login.php");
exit;
?>
