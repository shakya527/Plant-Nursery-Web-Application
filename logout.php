<?php
// =============================================================================
// PROJECT   : GreenThumb - Rubber Plant Nursery Web Application
// FILE      : logout.php
// PURPOSE   : Destroys the active session and clears the session cookie,
//             then redirects the user to login.html with a success message.
//
// SECURITY  : Calling session_destroy() AND clearing the cookie ensures no
//             residual session data remains on the client or server.
//             This protects against session hijacking after the user logs out.
// =============================================================================

session_start();

// Unset all session variables stored during login (user_id, role, full_name, etc.)
$_SESSION = [];

// Delete the session cookie from the browser.
// Without this, the browser still holds the old session ID cookie
// even though the server-side session data is gone.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',                         // Empty value
        time() - 42000,             // Expiry in the past — instructs browser to delete
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy all session data on the server
session_destroy();

// Redirect to login with a confirmation message
header('Location: login.html?success=' . urlencode('You have been logged out successfully. See you next time! 🌿'));
exit;
?>
