<?php
// =============================================================================
// PROJECT   : GreenThumb - Rubber Plant Nursery Web Application
// FILE      : login.php
// PURPOSE   : Server-side login handler.
//             Receives POST data from login.html, validates credentials using
//             a PREPARED STATEMENT (Obj 4), verifies the bcrypt password hash,
//             creates a session, and redirects the user to their role-specific
//             dashboard (Obj 3 → farmer, Obj 1/2 → customer).
//
// SECURITY  :
//   • Uses $conn->prepare() — ZERO raw SQL with user input (Obj 4)
//   • password_verify() — safe bcrypt comparison, timing-attack resistant
//   • session_regenerate_id(true) — prevents Session Fixation attacks
//   • Soft account check (is_active) — blocked accounts cannot log in
//
// FLOW      :
//   1. Validate POST inputs (non-empty, valid email format)
//   2. Fetch user record by email using a prepared statement
//   3. Verify bcrypt password hash
//   4. Check account is active
//   5. Regenerate session ID (security), set session variables
//   6. Redirect to role-appropriate dashboard
// =============================================================================


// ── 1. Bootstrap ──────────────────────────────────────────────────────────────
// session_start() MUST be called before any output or session variable access.
// It initialises or resumes the PHP session (used to track logged-in users).
session_start();

// Include the database connection and helper functions from Step 2.
// __DIR__ gives the absolute path of THIS file, making the include path
// reliable regardless of where PHP is called from.
require_once __DIR__ . '/config/db.php';

// ── 2. Redirect Already-Logged-In Users ──────────────────────────────────────
// If the user already has a valid session, send them to their dashboard
// immediately so they don't see the login page again unnecessarily.
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    $redirect = ($_SESSION['role'] === 'farmer')
        ? 'farmer/dashboard.php'
        : 'customer/catalog.php';
    header('Location: ' . $redirect);
    exit; // Always exit after header() to stop further PHP execution
}

// ── 3. Only Accept POST Requests ─────────────────────────────────────────────
// Direct browser navigation to login.php (GET request) should just redirect
// back to the login form. Only a form submission (POST) should be processed.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit;
}


// =============================================================================
// HELPER: Redirect back to login.html with an error message in the URL.
// The login.html JavaScript reads the ?error= param and shows it in the UI.
// We URL-encode the message so special characters survive the redirect.
// =============================================================================
function redirect_with_error(string $message): never
{
    header('Location: login.html?error=' . urlencode($message));
    exit;
}


// =============================================================================
// STEP A: Sanitise & Validate POST Inputs
// =============================================================================
// trim() strips leading/trailing whitespace (e.g. accidental spaces in email)
// strtolower() normalises email to lowercase so "User@Email.com" matches "user@email.com"
// htmlspecialchars() is NOT needed here because we use prepared statements —
// but it's used on output pages to prevent XSS when displaying user data.
// =============================================================================
$email    = strtolower(trim($_POST['email']    ?? ''));
$password = trim($_POST['password'] ?? '');

// Validate: email must not be empty
if (empty($email)) {
    redirect_with_error('Email address is required.');
}

// Validate: basic email format check (defense in depth — HTML5 does client-side too)
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_with_error('Please enter a valid email address.');
}

// Validate: password must not be empty
if (empty($password)) {
    redirect_with_error('Password is required.');
}

// Security: password length sanity check.
// bcrypt silently truncates at 72 bytes — very long passwords may indicate abuse.
if (strlen($password) > 255) {
    redirect_with_error('Invalid credentials. Please try again.');
}


// =============================================================================
// STEP B: Fetch User Record by Email (Prepared Statement — Obj 4)
// =============================================================================
// We use db_query_one() from config/db.php which internally calls:
//   $stmt = $conn->prepare("SELECT ... WHERE email = ?");
//   $stmt->bind_param("s", $email);
//   $stmt->execute();
//
// The ? placeholder means the $email value is bound as DATA — MySQL
// can never interpret it as SQL code, preventing SQL Injection entirely.
// =============================================================================
$user = db_query_one(
    "SELECT user_id, full_name, email, password_hash, role, is_active
     FROM users
     WHERE email = ?
     LIMIT 1",
    "s",        // type string: "s" = string
    [$email]    // bound parameters array
);

// If no user found with this email, show a deliberately vague error.
// Saying "email not found" would leak which emails are registered (user enumeration).
if (!$user) {
    redirect_with_error('Invalid email or password. Please try again.');
}


// =============================================================================
// STEP C: Verify Password Using bcrypt (password_verify)
// =============================================================================
// password_verify($password, $hash) performs a timing-safe comparison of the
// submitted password against the stored bcrypt hash.
// NEVER use === or md5() for password comparison — only password_verify().
// =============================================================================
if (!password_verify($password, $user['password_hash'])) {
    // Wrong password — same vague error to prevent user enumeration
    redirect_with_error('Invalid email or password. Please try again.');
}


// =============================================================================
// STEP D: Check Account is Active
// =============================================================================
// A farmer or customer account may be soft-disabled (is_active = 0) without
// deleting it. Disabled accounts cannot log in even with correct credentials.
// =============================================================================
if ((int) $user['is_active'] !== 1) {
    redirect_with_error('Your account has been disabled. Please contact the nursery.');
}


// =============================================================================
// STEP E: Create Session (Session Fixation Protection)
// =============================================================================
// session_regenerate_id(true) generates a NEW session ID and deletes the old
// session file. This prevents Session Fixation: an attack where a bad actor
// forces a known session ID on a victim before they log in.
// =============================================================================
session_regenerate_id(true);

// Store essential, non-sensitive user data in the session.
// NEVER store the password or password_hash in the session.
$_SESSION['user_id']   = (int) $user['user_id'];   // Integer cast for type safety
$_SESSION['full_name'] = $user['full_name'];        // Used in header.php greeting
$_SESSION['email']     = $user['email'];            // Useful for profile page
$_SESSION['role']      = $user['role'];             // Drives role-based UI (Obj 3)

// Set session lifetime to 2 hours of inactivity for security
$_SESSION['last_active'] = time();


// =============================================================================
// STEP F: Role-Based Redirect (Obj 3 – Farmer, Obj 1/2 – Customer)
// =============================================================================
// After successful login, direct the user to their appropriate dashboard:
//   • Farmer   → farmer/dashboard.php (plant CRUD, order management, reports)
//   • Customer → customer/catalog.php (browse plants, place orders, view invoices)
// =============================================================================
if ($user['role'] === 'farmer') {
    // Farmer goes to the management dashboard
    header('Location: farmer/dashboard.php');
} else {
    // Customer goes to the plant catalog (Obj 1 – customer catalog)
    header('Location: customer/catalog.php');
}

exit; // Always terminate script execution after a redirect
?>
