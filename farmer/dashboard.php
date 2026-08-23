<?php
// =============================================================================
// PROJECT   : GreenThumb - Rubber Plant Nursery Web Application
// FILE      : farmer/dashboard.php
// PURPOSE   : The Farmer's main command center after login.
//             Displays key business metrics and recent orders at a glance.
//
// SECTIONS  :
//   1. Auth Guard         – Only farmers can access this page (Obj 3)
//   2. Stats Queries      – Aggregate data using prepared statements (Obj 4)
//   3. Dashboard Cards    – Plant count, orders, revenue, low-stock alerts
//   4. Recent Orders      – Last 10 orders with status badges (Obj 5)
//   5. Quick Actions      – One-click links to Manage Plants, Reports, etc.
//
// OBJECTIVE : Obj 3 – Farmer central hub; Obj 5 – Revenue and order overview
// =============================================================================

session_start();
require_once __DIR__ . '/../config/db.php';

// ── 1. Auth Guard: Farmer Only ────────────────────────────────────────────────
// If no session or wrong role, redirect immediately.
// A customer trying to access /farmer/dashboard.php is sent to their own area.
if (!isset($_SESSION['user_id'])) {
    header('Location: /Rudder_plant/login.html?error=' . urlencode('Please log in to access the farmer dashboard.'));
    exit;
}
if ($_SESSION['role'] !== 'farmer') {
    header('Location: /Rudder_plant/customer/catalog.php');
    exit;
}

$farmer_id   = (int) $_SESSION['user_id'];
$farmer_name = htmlspecialchars($_SESSION['full_name']);


// =============================================================================
// 2. DASHBOARD STATISTICS (All use Prepared Statements — Obj 4)
// =============================================================================

// ── Stat A: Total active plants in the catalog ────────────────────────────────
$stat_plants = db_query_one(
    "SELECT COUNT(*) AS total FROM plants WHERE is_available = 1",
    '', []
);
$total_plants = (int) ($stat_plants['total'] ?? 0);

// ── Stat B: Total plant records this farmer owns (including unavailable) ──────
$stat_my_plants = db_query_one(
    "SELECT COUNT(*) AS total FROM plants WHERE created_by = ?",
    "i", [$farmer_id]
);
$my_plants_count = (int) ($stat_my_plants['total'] ?? 0);

// ── Stat C: Total orders placed (excluding cancelled) ─────────────────────────
$stat_orders = db_query_one(
    "SELECT COUNT(*) AS total FROM orders WHERE order_status != 'cancelled'",
    '', []
);
$total_orders = (int) ($stat_orders['total'] ?? 0);

// ── Stat D: Total confirmed revenue (Obj 5 – income overview) ────────────────
// Only counts orders that are confirmed, shipped, or delivered (not pending/cancelled)
$stat_revenue = db_query_one(
    "SELECT COALESCE(SUM(total_amount), 0) AS revenue
     FROM orders
     WHERE order_status IN ('confirmed', 'shipped', 'delivered')",
    '', []
);
$total_revenue = number_format((float)($stat_revenue['revenue'] ?? 0), 2);

// ── Stat E: Low stock alert — plants with 10 or fewer units remaining ─────────
// This maps to Obj 1 (accurate stock levels) and Obj 3 (farmer management)
$stat_low_stock = db_query_one(
    "SELECT COUNT(*) AS total
     FROM plants
     WHERE stock_quantity <= 10
       AND is_available = 1
       AND created_by = ?",
    "i", [$farmer_id]
);
$low_stock_count = (int) ($stat_low_stock['total'] ?? 0);

// ── Stat F: Pending orders awaiting farmer action ─────────────────────────────
$stat_pending = db_query_one(
    "SELECT COUNT(*) AS total FROM orders WHERE order_status = 'pending'",
    '', []
);
$pending_orders = (int) ($stat_pending['total'] ?? 0);


// =============================================================================
// 3. RECENT ORDERS (Last 10 — for the dashboard table)
// =============================================================================
// JOINs orders with users to get the customer's name.
// COUNT(oi.item_id) gives the number of plant types in each order.
// Prepared statement with no user input — safe and efficient.
// =============================================================================
$recent_orders = db_query(
    "SELECT
         o.order_id,
         o.shipping_name,
         o.shipping_city,
         o.order_status,
         o.total_amount,
         o.created_at,
         COUNT(oi.item_id) AS item_types
     FROM orders o
     LEFT JOIN order_items oi ON o.order_id = oi.order_id
     GROUP BY o.order_id
     ORDER BY o.created_at DESC
     LIMIT 10",
    '', []
);

// =============================================================================
// 4. LOW STOCK PLANTS (for the alert panel)
// =============================================================================
$low_stock_plants = db_query(
    "SELECT plant_id, plant_name, stock_quantity
     FROM plants
     WHERE stock_quantity <= 10
       AND is_available = 1
       AND created_by = ?
     ORDER BY stock_quantity ASC
     LIMIT 5",
    "i", [$farmer_id]
);


// =============================================================================
// PAGE RENDER
// =============================================================================
$page_title = 'Farmer Dashboard';
$active_nav = 'farmer_dashboard';
require_once __DIR__ . '/../components/header.php';
?>

<!-- ── Page Hero ──────────────────────────────────────────────────────────────── -->
<div class="page-hero">
    <div class="container">
        <div class="flex items-center justify-between" style="flex-wrap:wrap;gap:16px;">
            <div>
                <h1>👋 Welcome back, <?= $farmer_name ?>!</h1>
                <p>Here's your nursery overview for <?= date('l, d F Y') ?>.</p>
            </div>
            <!-- Quick action CTA -->
            <div class="flex gap-md" style="flex-wrap:wrap;">
                <a href="/Rudder_plant/farmer/manage_plants.php?action=add"
                   class="btn btn-primary">
                    + Add New Plant
                </a>
                <a href="/Rudder_plant/farmer/orders.php"
                   class="btn btn-secondary">
                    📦 View All Orders
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container">

    <!-- ══════════════════════════════════════════════════════════════════════
         SECTION A: STAT CARDS (Obj 3 & Obj 5)
         Four cards showing the most important business KPIs at a glance.
         ══════════════════════════════════════════════════════════════════════ -->
    <div class="grid-4" style="margin-bottom:var(--space-xl);">

        <!-- Total Revenue card (Obj 5 – income overview) -->
        <div class="stat-card">
            <div class="stat-icon green" aria-hidden="true">💰</div>
            <div>
                <div class="stat-value" title="Total confirmed revenue in LKR">Rs. <?= $total_revenue ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>

        <!-- Total Orders card -->
        <div class="stat-card">
            <div class="stat-icon blue" aria-hidden="true">📦</div>
            <div>
                <div class="stat-value"><?= $total_orders ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
        </div>

        <!-- My Plants card (Obj 3 – farmer plant management) -->
        <div class="stat-card">
            <div class="stat-icon green" aria-hidden="true">🌱</div>
            <div>
                <div class="stat-value"><?= $total_plants ?></div>
                <div class="stat-label">Active Plant Listings</div>
            </div>
        </div>

        <!-- Pending Orders card — highlight if > 0 -->
        <div class="stat-card <?= $pending_orders > 0 ? 'pending-alert' : '' ?>">
            <div class="stat-icon yellow" aria-hidden="true">⏳</div>
            <div>
                <div class="stat-value" style="<?= $pending_orders > 0 ? 'color:var(--clr-warning)' : '' ?>">
                    <?= $pending_orders ?>
                </div>
                <div class="stat-label">Pending Orders</div>
            </div>
        </div>

    </div><!-- end .grid-4 -->


    <!-- ══════════════════════════════════════════════════════════════════════
         SECTION B: MAIN CONTENT (2-column layout)
         Left  → Recent Orders table
         Right → Low Stock Alerts + Quick Actions
         ══════════════════════════════════════════════════════════════════════ -->
    <div style="display:grid;grid-template-columns:1fr 340px;gap:var(--space-xl);align-items:start;">

        <!-- ── Recent Orders Table ─────────────────────────────────── -->
        <div>
            <div class="flex items-center justify-between" style="margin-bottom:var(--space-md);">
                <h2 style="font-size:1.2rem;">📋 Recent Orders</h2>
                <a href="/Rudder_plant/farmer/orders.php" style="font-size:0.85rem;color:var(--clr-primary);">
                    View all →
                </a>
            </div>

            <?php if (empty($recent_orders)): ?>
            <!-- Empty state -->
            <div class="card" style="text-align:center;padding:var(--space-xl);">
                <div style="font-size:3rem;margin-bottom:var(--space-md);">📭</div>
                <h3 style="font-size:1rem;margin-bottom:8px;">No orders yet</h3>
                <p style="font-size:0.9rem;">Orders from customers will appear here once placed.</p>
            </div>
            <?php else: ?>

            <!-- Orders table — Obj 5 (powers invoice and report links) -->
            <div class="table-wrapper">
                <table class="table" aria-label="Recent orders">
                    <thead>
                        <tr>
                            <th scope="col">#ID</th>
                            <th scope="col">Customer</th>
                            <th scope="col">City</th>
                            <th scope="col">Items</th>
                            <th scope="col">Amount</th>
                            <th scope="col">Status</th>
                            <th scope="col">Date</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent_orders as $order): ?>
                        <?php
                        // Determine badge class for order status
                        $status_badge = [
                            'pending'   => 'badge-pending',
                            'confirmed' => 'badge-confirmed',
                            'shipped'   => 'badge-shipped',
                            'delivered' => 'badge-delivered',
                            'cancelled' => 'badge-cancelled',
                        ][$order['order_status']] ?? 'badge-muted';
                        ?>
                        <tr>
                            <td><strong>#<?= $order['order_id'] ?></strong></td>
                            <td><?= htmlspecialchars($order['shipping_name']) ?></td>
                            <td><?= htmlspecialchars($order['shipping_city']) ?></td>
                            <td><?= (int)$order['item_types'] ?> type(s)</td>
                            <td>Rs. <?= number_format((float)$order['total_amount'], 2) ?></td>
                            <td>
                                <span class="badge <?= $status_badge ?>">
                                    <?= ucfirst(htmlspecialchars($order['order_status'])) ?>
                                </span>
                            </td>
                            <td style="font-size:0.82rem;color:var(--clr-text-muted);">
                                <?= date('d M Y', strtotime($order['created_at'])) ?>
                            </td>
                            <td>
                                <div class="flex gap-sm">
                                    <!-- View Invoice — Obj 5 (printable invoice) -->
                                    <a href="/Rudder_plant/farmer/view_invoice.php?order_id=<?= $order['order_id'] ?>"
                                       class="btn btn-secondary btn-sm"
                                       title="View invoice for order #<?= $order['order_id'] ?>">
                                        🧾
                                    </a>
                                    <!-- Update order status -->
                                    <a href="/Rudder_plant/farmer/orders.php?edit=<?= $order['order_id'] ?>"
                                       class="btn btn-secondary btn-sm"
                                       title="Update order #<?= $order['order_id'] ?>">
                                        ✏️
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php endif; // end empty check ?>
        </div><!-- end recent orders column -->


        <!-- ── Right Sidebar ──────────────────────────────────────── -->
        <div style="display:flex;flex-direction:column;gap:var(--space-lg);">

            <!-- Low Stock Alert Panel (Obj 1 – accurate stock levels) -->
            <?php if ($low_stock_count > 0): ?>
            <div class="card">
                <div class="card-body">
                    <h3 style="font-size:1rem;margin-bottom:var(--space-md);color:var(--clr-warning);">
                        ⚠️ Low Stock Alert
                    </h3>
                    <?php foreach ($low_stock_plants as $lsp): ?>
                    <div class="flex items-center justify-between" style="padding:10px 0;border-bottom:1px solid var(--clr-border);">
                        <div>
                            <div style="font-size:0.875rem;font-weight:600;">
                                <?= htmlspecialchars($lsp['plant_name']) ?>
                            </div>
                        </div>
                        <div>
                            <span class="badge <?= (int)$lsp['stock_quantity'] === 0 ? 'badge-danger' : 'badge-warning' ?>">
                                <?= (int)$lsp['stock_quantity'] ?> left
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <a href="/Rudder_plant/farmer/manage_plants.php"
                       class="btn btn-secondary btn-full"
                       style="margin-top:var(--space-md);">
                        Manage Stock →
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Actions Panel -->
            <div class="card">
                <div class="card-body">
                    <h3 style="font-size:1rem;margin-bottom:var(--space-md);">⚡ Quick Actions</h3>
                    <div style="display:flex;flex-direction:column;gap:var(--space-sm);">

                        <a href="/Rudder_plant/farmer/manage_plants.php?action=add"
                           class="btn btn-primary btn-full">
                            🌱 Add New Plant
                        </a>

                        <a href="/Rudder_plant/farmer/manage_plants.php"
                           class="btn btn-secondary btn-full">
                            📋 View All Plants
                        </a>

                        <a href="/Rudder_plant/farmer/orders.php"
                           class="btn btn-secondary btn-full">
                            📦 Manage Orders
                        </a>

                        <!-- Income report — Obj 5 (income logs) -->
                        <a href="/Rudder_plant/farmer/income_report.php"
                           class="btn btn-secondary btn-full">
                            📈 Income Report
                        </a>
                    </div>
                </div>
            </div>

            <!-- Business Summary Card -->
            <div class="card" style="background:linear-gradient(135deg,rgba(74,222,128,0.06),rgba(34,197,94,0.02));">
                <div class="card-body">
                    <h3 style="font-size:0.85rem;text-transform:uppercase;letter-spacing:1px;color:var(--clr-text-muted);margin-bottom:var(--space-md);">Summary</h3>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        <div class="flex justify-between" style="font-size:0.875rem;">
                            <span style="color:var(--clr-text-muted);">My plant listings</span>
                            <strong><?= $my_plants_count ?></strong>
                        </div>
                        <div class="flex justify-between" style="font-size:0.875rem;">
                            <span style="color:var(--clr-text-muted);">Low stock items</span>
                            <strong style="color:<?= $low_stock_count > 0 ? 'var(--clr-warning)' : 'var(--clr-primary)' ?>">
                                <?= $low_stock_count ?>
                            </strong>
                        </div>
                        <div class="flex justify-between" style="font-size:0.875rem;">
                            <span style="color:var(--clr-text-muted);">Pending orders</span>
                            <strong style="color:<?= $pending_orders > 0 ? 'var(--clr-warning)' : 'var(--clr-primary)' ?>">
                                <?= $pending_orders ?>
                            </strong>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- end sidebar column -->

    </div><!-- end 2-column grid -->

</div><!-- end .container -->

<?php require_once __DIR__ . '/../components/footer.php'; ?>
