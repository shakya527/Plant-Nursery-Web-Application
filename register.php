<?php
// =============================================================================
// PROJECT   : GreenThumb - Rubber Plant Nursery Web Application
// FILE      : register.php
// PURPOSE   : Server-side registration handler.
//             Validates all POST data from register.html, checks email
//             uniqueness with a PREPARED STATEMENT, hashes the password
//             using bcrypt, inserts the new user, and redirects to login.
//
// SECURITY  :
//   • All DB operations use $conn->prepare() — zero raw SQL (Obj 4)
//   • password_hash(PASSWORD_BCRYPT) — bcrypt with cost factor 12
//   • Role whitelist validation — only 'farmer'/'customer' accepted
//   • filter_var(FILTER_VALIDATE_EMAIL) — server-side email format check
//   • htmlspecialchars() on all output to prevent XSS
//
// FLOW      :
//   1. Validate all POST inputs (name, email, password, role)
//   2. Check email is not already registered (prepared SELECT)
//   3. Hash password with bcrypt
//   4. Insert new user record (prepared INSERT)
//   5. Redirect to login.html with a success message
// =============================================================================

session_start();
require_once __DIR__ . '/config/db.php';

// ── Redirect if Already Logged In ─────────────────────────────────────────────
if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'farmer' ? 'farmer/dashboard.php' : 'customer/catalog.php'));
    exit;
}

// ── Only Process POST Requests ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.html');
    exit;
}

// =============================================================================
// HELPER: Redirect back to register.html with an error and the user's email
// so the form can pre-fill it (better UX — user doesn't retype everything).
// =============================================================================
function reg_error(string $msg, string $email = ''): never
{
    $url = 'register.html?error=' . urlencode($msg);
    if ($email) $url .= '&email=' . urlencode($email);
    header('Location: ' . $url);
    exit;
}


// =============================================================================
// STEP A: Collect & Sanitise POST Inputs
// =============================================================================
$full_name        = trim($_POST['full_name']        ?? '');
$email            = strtolower(trim($_POST['email'] ?? ''));
$password         = $_POST['password']              ?? '';
$confirm_password = $_POST['confirm_password']      ?? '';
$phone            = trim($_POST['phone']            ?? '');
$address          = trim($_POST['address']          ?? '');
$role             = trim($_POST['role']             ?? 'customer');
$terms            = $_POST['terms']                 ?? '';


// =============================================================================
// STEP B: Server-Side Validation
// (Defense in depth — JS does client-side; PHP is the authoritative check)
// =============================================================================

// Full name: required, 2–120 chars, letters/spaces/hyphens only
if (empty($full_name)) {
    reg_error('Full name is required.', $email);
}
if (strlen($full_name) < 2 || strlen($full_name) > 120) {
    reg_error('Full name must be between 2 and 120 characters.', $email);
}
// Allow letters (including Unicode for Malay names), spaces, hyphens, apostrophes
if (!preg_match('/^[\p{L}\s\'\-\.]+$/u', $full_name)) {
    reg_error('Full name contains invalid characters.', $email);
}

// Email: required, valid format, max 180 chars
if (empty($email)) {
    reg_error('Email address is required.', $email);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    reg_error('Please enter a valid email address.', $email);
}
if (strlen($email) > 180) {
    reg_error('Email address is too long.', $email);
}

// Password: required, minimum 8 chars, max 255 (bcrypt truncates at 72 bytes)
if (empty($password)) {
    reg_error('Password is required.', $email);
}
if (strlen($password) < 8) {
    reg_error('Password must be at least 8 characters long.', $email);
}
if (strlen($password) > 255) {
    reg_error('Password is too long.', $email);
}

// Password confirmation must match
if ($password !== $confirm_password) {
    reg_error('Passwords do not match. Please re-enter your password.', $email);
}

// Role: must be exactly 'farmer' or 'customer' — whitelist validation
// NEVER trust the role value from a form — it could be tampered with
$allowed_roles = ['farmer', 'customer'];
if (!in_array($role, $allowed_roles, true)) {
    reg_error('Invalid account type selected.', $email);
}

// Terms: must be agreed (value = "1")
if ($terms !== '1') {
    reg_error('You must agree to the Terms of Service to create an account.', $email);
}

// Phone: optional but validate format if provided
if (!empty($phone)) {
    // Allow +, digits, spaces, hyphens, parentheses
    if (!preg_match('/^[\+\d\s\-\(\)]{7,20}$/', $phone)) {
        reg_error('Phone number format is invalid.', $email);
    }
}

// Address: optional, max 500 chars
if (strlen($address) > 500) {
    reg_error('Address is too long (max 500 characters).', $email);
}


// =============================================================================
// STEP C: Check if Email is Already Registered (Prepared Statement — Obj 4)
// =============================================================================
// We look up by email to prevent duplicate accounts.
// Using db_query_one() which internally uses $stmt->prepare() and bind_param().
// =============================================================================
$existing_user = db_query_one(
    "SELECT user_id FROM users WHERE email = ? LIMIT 1",
    "s",
    [$email]
);

if ($existing_user) {
    // Email is already in use — tell the user but don't confirm which accounts exist
    reg_error(
        'This email address is already registered. Please log in or use a different email.',
        $email
    );
}


// =============================================================================
// STEP D: Hash the Password with bcrypt (Obj 4 – Security)
// =============================================================================
// password_hash() uses bcrypt by default (PASSWORD_BCRYPT).
// Cost factor 12 provides strong protection (roughly 250ms per hash on modern hardware).
// A higher cost increases security but slows registration — 12 is the recommended balance.
// The resulting string is ~60 chars and is self-contained (includes algorithm, cost, salt).
// =============================================================================
$password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

if ($password_hash === false) {
    // This should never happen with valid input, but guard anyway
    reg_error('An internal error occurred. Please try again.', $email);
}


// =============================================================================
// STEP E: Insert New User Record (Prepared Statement — Obj 4)
// =============================================================================
// All six bound parameters are passed as data, never interpreted as SQL.
// Type string "ssssss": s = string for all 6 params.
// =============================================================================
$rows_affected = db_query(
    "INSERT INTO users (full_name, email, password_hash, role, phone, address)
     VALUES (?, ?, ?, ?, ?, ?)",
    "ssssss",
    [
        $full_name,
        $email,
        $password_hash,
        $role,
        $phone   ?: null,   // Store NULL if phone not provided
        $address ?: null    // Store NULL if address not provided
    ]
);

if ($rows_affected < 1) {
    // Insert failed — log for admin and show generic error
    error_log('[GreenThumb] Registration insert failed for email: ' . $email);
    reg_error('Registration failed due to an internal error. Please try again.', $email);
}


// =============================================================================
// STEP F: Redirect to Login with Success Message
// =============================================================================
// Registration complete. Do NOT auto-login here — force an explicit login so
// the user's credentials are verified through the same path as all other logins.
// This also avoids session complexity immediately after account creation.
// =============================================================================
$success_msg = ($role === 'farmer')
    ? 'Farmer account created successfully! Please log in.'
    : 'Welcome to GreenThumb! Your account is ready. Please log in.';

header('Location: login.html?success=' . urlencode($success_msg));
exit;
?>
