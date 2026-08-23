<?php
// =============================================================================
// FILE      : config/test_connection.php
// PURPOSE   : One-time connection test script.
//             Run this in the browser to confirm db.php is working correctly.
//             ⚠️  DELETE this file before going live (it reveals DB structure).
// HOW TO RUN: http://localhost/Rudder_plant/config/test_connection.php
// =============================================================================

require_once __DIR__ . '/../config/db.php'; // Load the connection + helpers

echo '<h2 style="font-family:Arial; color:green;">✅ DB Connection Successful!</h2>';
echo '<pre style="font-family:Arial; font-size:14px;">';

// Test 1: Fetch all plant categories
echo "── Plant Categories ──\n";
$categories = db_query("SELECT * FROM plant_categories", '', []);
foreach ($categories as $cat) {
    echo "  [{$cat['category_id']}] {$cat['category_name']}\n";
}

// Test 2: Fetch all users (roles only — never display passwords)
echo "\n── Users (roles only) ──\n";
$users = db_query("SELECT user_id, full_name, email, role FROM users", '', []);
foreach ($users as $u) {
    echo "  [{$u['user_id']}] {$u['full_name']} <{$u['email']}> — Role: {$u['role']}\n";
}

// Test 3: Fetch all plants with a prepared-statement parameter
echo "\n── Plants (is_available = 1) ──\n";
$plants = db_query(
    "SELECT plant_id, plant_name, price_per_unit, stock_quantity FROM plants WHERE is_available = ?",
    "i",   // type: integer
    [1]    // value: 1 (true)
);
foreach ($plants as $p) {
    echo "  [{$p['plant_id']}] {$p['plant_name']} — RM {$p['price_per_unit']} | Stock: {$p['stock_quantity']}\n";
}

echo '</pre>';
echo '<p style="font-family:Arial;color:#c0392b;"><strong>⚠️ Remember: Delete this test file before deploying to production!</strong></p>';
?>
