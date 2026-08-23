<?php
// =============================================================================
// PROJECT   : GreenThumb - Rubber Plant Nursery Web Application
// FILE      : components/header.php
// PURPOSE   : Site-wide HTML header and navigation bar shell.
//             Included at the TOP of every protected PHP page via:
//               require_once __DIR__ . '/../components/header.php';
//
//             The INCLUDING PAGE must:
//               1. Call session_start() BEFORE including this file
//               2. Call require_once for db.php BEFORE this file
//               3. Optionally set $page_title and $active_nav variables
//
// ROLE-BASED NAV (Obj 3 & Obj 1):
//   • Farmer   → Dashboard, Manage Plants, All Orders, Reports, Logout
//   • Customer → Catalog, My Orders, Logout
//   • Guest    → Login, Register (should not reach protected pages, but safe)
//
// OBJECTIVE : Obj 4 – Consistent responsive UI shell across all pages
// =============================================================================

// ── Session Idle Timeout Guard (Obj 4 – Security) ────────────────────────────
// If a user leaves the browser open, expire their session after 2 hours of
// inactivity. This prevents someone else using an unattended computer.
if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active']) > 7200) {
    // Session has been idle for more than 2 hours — force logout
    session_destroy();
    header('Location: /Rudder_plant/login.html?error=' . urlencode('Your session expired. Please log in again.'));
    exit;
}
// Update last activity timestamp
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_active'] = time();
}

// ── Page Variables with Defaults ──────────────────────────────────────────────
// $page_title and $active_nav are set by the including page before this include.
// If not set, fall back to sensible defaults.
$page_title = $page_title ?? 'GreenThumb';
$active_nav = $active_nav ?? '';

// ── Build base URL dynamically ────────────────────────────────────────────────
// Works whether the project is at /Rudder_plant/ or any subdirectory.
// All asset/link URLs below use this constant.
define('BASE_URL', '/Rudder_plant');

// ── Determine current user role and state ─────────────────────────────────────
$is_logged_in  = isset($_SESSION['user_id']);
$user_role     = $_SESSION['role']      ?? 'guest';
$user_name     = $_SESSION['full_name'] ?? 'Guest';
$user_initials = '';
if ($is_logged_in) {
    // Build initials for avatar: "Ahmad Bin Ali" → "AB"
    $parts = explode(' ', $user_name);
    $user_initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}

// Helper: checks if a nav item is the active page (adds .active CSS class)
function nav_active(string $key): string {
    global $active_nav;
    return $active_nav === $key ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Dynamic page title set by each including page (Obj 4 – SEO) -->
    <title><?= htmlspecialchars($page_title) ?> – GreenThumb Rubber Plant Nursery</title>
    <meta name="description" content="GreenThumb – Sri Lanka's premier online rubber plant nursery. Browse rare cultivars, manage inventory, and track orders.">

    <!-- Global design system (css/style.css created in Step 2) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">

    <!-- Optional page-specific CSS injected by the including page via $extra_head -->
    <?= $extra_head ?? '' ?>
</head>
<body>

<!-- =============================================================================
     NAVBAR
     Role-based navigation (Obj 3 – Farmer links, Obj 1 – Customer links)
     Fixed at the top; pages use padding-top: var(--navbar-h) via .page-wrapper
     ============================================================================= -->
<nav class="navbar" role="navigation" aria-label="Main navigation">
    <div class="container">

        <!-- ── Brand Logo ─────────────────────────────────────── -->
        <a href="<?= BASE_URL ?>/<?= $user_role === 'farmer' ? 'farmer/dashboard.php' : ($is_logged_in ? 'customer/catalog.php' : 'login.html') ?>"
           class="navbar-brand" aria-label="GreenThumb Home">
            <div class="brand-icon" aria-hidden="true">🌿</div>
            GreenThumb
        </a>

        <!-- ── Navigation Links (role-based) ──────────────────── -->
        <ul class="navbar-nav" id="navLinks" role="list">

            <?php if ($is_logged_in && $user_role === 'farmer'): ?>
            <!-- ════════════════════════════════════════════════
                 FARMER NAVIGATION (Obj 3 – Farmer CRUD access)
                 ════════════════════════════════════════════════ -->

            <li>
                <a href="<?= BASE_URL ?>/farmer/dashboard.php"
                   class="nav-link <?= nav_active('farmer_dashboard') ?>"
                   aria-current="<?= $active_nav === 'farmer_dashboard' ? 'page' : 'false' ?>">
                    📊 Dashboard
                </a>
            </li>

            <li>
                <!-- Manage Plants → farmer/manage_plants.php (Obj 3 – CRUD) -->
                <a href="<?= BASE_URL ?>/farmer/manage_plants.php"
                   class="nav-link <?= nav_active('manage_plants') ?>"
                   aria-current="<?= $active_nav === 'manage_plants' ? 'page' : 'false' ?>">
                    🌱 Manage Plants
                </a>
            </li>

            <li>
                <!-- All Orders → farmer can view and update order statuses -->
                <a href="<?= BASE_URL ?>/farmer/orders.php"
                   class="nav-link <?= nav_active('farmer_orders') ?>"
                   aria-current="<?= $active_nav === 'farmer_orders' ? 'page' : 'false' ?>">
                    📦 All Orders
                </a>
            </li>

            <li>
                <!-- Income Report → income_report.php (Obj 5 – Reports) -->
                <a href="<?= BASE_URL ?>/farmer/income_report.php"
                   class="nav-link <?= nav_active('income_report') ?>"
                   aria-current="<?= $active_nav === 'income_report' ? 'page' : 'false' ?>">
                    📈 Reports
                </a>
            </li>

            <?php elseif ($is_logged_in && $user_role === 'customer'): ?>
            <!-- ════════════════════════════════════════════════
                 CUSTOMER NAVIGATION (Obj 1 – Catalog, Obj 2 – Orders)
                 ════════════════════════════════════════════════ -->

            <li>
                <!-- Plant Catalog → customer/catalog.php (Obj 1 – Customer Catalog) -->
                <a href="<?= BASE_URL ?>/customer/catalog.php"
                   class="nav-link <?= nav_active('catalog') ?>"
                   aria-current="<?= $active_nav === 'catalog' ? 'page' : 'false' ?>">
                    🌿 Plant Catalog
                </a>
            </li>

            <li>
                <!-- My Orders → customer/my_orders.php (Obj 2 – Order tracking) -->
                <a href="<?= BASE_URL ?>/customer/my_orders.php"
                   class="nav-link <?= nav_active('my_orders') ?>"
                   aria-current="<?= $active_nav === 'my_orders' ? 'page' : 'false' ?>">
                    📋 My Orders
                </a>
            </li>

            <?php else: ?>
            <!-- ════════════════════════════════════════════════
                 GUEST NAVIGATION (should not reach protected pages)
                 ════════════════════════════════════════════════ -->
            <li><a href="<?= BASE_URL ?>/login.html"    class="nav-link">Login</a></li>
            <li><a href="<?= BASE_URL ?>/register.html" class="nav-link">Register</a></li>
            <?php endif; ?>

        </ul><!-- end .navbar-nav -->

        <!-- ── Right Side: User Info + Logout ─────────────────── -->
        <?php if ($is_logged_in): ?>
        <div class="flex items-center gap-md">

            <!-- Role badge: visually shows Farmer or Customer role at a glance -->
            <span class="role-badge <?= htmlspecialchars($user_role) ?>"
                  aria-label="Your account role: <?= htmlspecialchars($user_role) ?>">
                <?= $user_role === 'farmer' ? '🌾' : '🛍️' ?>
                <?= ucfirst(htmlspecialchars($user_role)) ?>
            </span>

            <!-- User avatar with initials -->
            <div title="Logged in as <?= htmlspecialchars($user_name) ?>"
                 style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--clr-primary),var(--clr-primary-dark));display:grid;place-items:center;font-size:0.8rem;font-weight:700;color:#071407;flex-shrink:0;cursor:default;"
                 aria-label="User avatar for <?= htmlspecialchars($user_name) ?>">
                <?= htmlspecialchars($user_initials) ?>
            </div>

            <!-- Logout button — links to logout.php (destroys session) -->
            <a href="<?= BASE_URL ?>/logout.php"
               class="nav-btn nav-btn-ghost"
               id="logoutBtn"
               aria-label="Log out of GreenThumb"
               onclick="return confirm('Are you sure you want to log out?');">
                🚪 Logout
            </a>
        </div>
        <?php endif; ?>

        <!-- ── Mobile Hamburger Toggle ─────────────────────────── -->
        <!-- The #navLinks ul is hidden on mobile; JS below toggles .open class -->
        <button class="nav-toggle"
                id="navToggle"
                aria-label="Toggle navigation menu"
                aria-expanded="false"
                aria-controls="navLinks">
            <span></span>
            <span></span>
            <span></span>
        </button>

    </div>
</nav>
<!-- END NAVBAR -->


<!-- =============================================================================
     MAIN CONTENT WRAPPER
     Each page's content goes between header.php and footer.php includes.
     .page-wrapper adds top padding to clear the fixed navbar.
     ============================================================================= -->
<main class="page-wrapper" id="main-content" role="main">


<!-- =============================================================================
     INLINE SCRIPT: Mobile nav toggle
     Toggles .open on #navLinks when hamburger is clicked (Obj 4 – Responsive)
     ============================================================================= -->
<script>
(function () {
    const toggle   = document.getElementById('navToggle');
    const navLinks = document.getElementById('navLinks');
    if (!toggle || !navLinks) return;

    toggle.addEventListener('click', function () {
        const isOpen = navLinks.classList.toggle('open');
        this.setAttribute('aria-expanded', isOpen);
    });

    // Close nav if user clicks outside it
    document.addEventListener('click', function (e) {
        if (!navLinks.contains(e.target) && !toggle.contains(e.target)) {
            navLinks.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });
})();
</script>
