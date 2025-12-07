<?php
session_start();

// 1. Clear all session variables
$_SESSION = array();

// 2. Destroy the session cookie (if one exists)
// This is best practice for a full logout.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Finally, destroy the session on the server
session_destroy();

// 4. Redirect the user back to the access page for role selection
header("Location: access.php");
exit();
?>