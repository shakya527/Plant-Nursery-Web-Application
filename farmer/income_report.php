<?php
// =============================================================================
// PROJECT   : GreenThumb - Rubber Plant Nursery Web Application
// FILE      : farmer/income_report.php
// PURPOSE   : Displays aggregate income and sales data for the farmer based
//             on line items they created.
// OBJECTIVE : Obj 5 – Automated reports.
// =============================================================================

session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header('Location: /Rudder_plant/login.html');
    exit;
}

$farmer_id = (int) $_SESSION['user_id'];

// ── Aggregate Data (Obj 5) ───────────────────────────────────────────────────

// [FIXED] Avoided created_by NULL issue and included 'pending' orders to calculate total revenue.
$revenue_data = db_query_one(
    "SELECT COALESCE(SUM(oi.line_total), 0) as total_revenue, 
            COUNT(DISTINCT oi.plant_id) as plants_sold, 
            COALESCE(SUM(oi.quantity), 0) as items_sold
     FROM order_items oi
     JOIN orders o ON oi.order_id = o.order_id
     JOIN plants p ON oi.plant_id = p.plant_id
     WHERE o.order_status IN ('pending', 'confirmed', 'shipped', 'delivered')"
);

// [FIXED] Corrected SQL query for the top selling plants list.
$top_plants = db_query(
    "SELECT p.plant_name, SUM(oi.quantity) as qty_sold, SUM(oi.line_total) as revenue 
     FROM order_items oi
     JOIN orders o ON oi.order_id = o.order_id
     JOIN plants p ON oi.plant_id = p.plant_id
     WHERE o.order_status IN ('pending', 'confirmed', 'shipped', 'delivered')
     GROUP BY p.plant_id, p.plant_name
     ORDER BY qty_sold DESC
     LIMIT 5"
);

$page_title = 'Income Report';
$active_nav = 'income_report';
require_once __DIR__ . '/../components/header.php';
?>

<div class="page-hero">
    <div class="container">
        <h1>📈 Income & Sales Report</h1>
        <p>Overview of your sales performance and revenue generation.</p>
    </div>
</div>

<div class="container fade-in">
    
    <div class="grid-3" style="margin-bottom:var(--space-xl);">
        <div class="stat-card" style="background:var(--clr-primary);color:#071407;">
            <div class="stat-icon" style="background:rgba(255,255,255,0.2);color:#071407;">💰</div>
            <div>
                <div class="stat-value" style="color:#071407;">Rs. <?= number_format((float)$revenue_data['total_revenue'], 2) ?></div>
                <div class="stat-label" style="color:rgba(0,0,0,0.7);">Total Revenue</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon green">📦</div>
            <div>
                <div class="stat-value"><?= (int)$revenue_data['items_sold'] ?></div>
                <div class="stat-label">Total Units Sold</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon blue">🌱</div>
            <div>
                <div class="stat-value"><?= (int)$revenue_data['plants_sold'] ?></div>
                <div class="stat-label">Unique Varieties Sold</div>
            </div>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-body">
                <h3 style="margin-bottom:var(--space-md);">🏆 Top Performing Plants</h3>
                <?php if (empty($top_plants)): ?>
                    <p style="color:var(--clr-text-muted);">No sales data available yet.</p>
                <?php else: ?>
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr style="border-bottom:1px solid var(--clr-border);">
                                <th style="text-align:left;padding:8px 0;color:var(--clr-text-muted);">Plant</th>
                                <th style="text-align:right;padding:8px 0;color:var(--clr-text-muted);">Qty Sold</th>
                                <th style="text-align:right;padding:8px 0;color:var(--clr-text-muted);">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_plants as $tp): ?>
                            <tr style="border-bottom:1px solid var(--clr-border);">
                                <td style="padding:12px 0;font-weight:500;"><?= htmlspecialchars($tp['plant_name']) ?></td>
                                <td style="padding:12px 0;text-align:right;"><?= $tp['qty_sold'] ?></td>
                                <td style="padding:12px 0;text-align:right;color:var(--clr-primary);font-weight:700;">Rs. <?= number_format($tp['revenue'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card" style="display:flex;align-items:center;justify-content:center;text-align:center;padding:40px;">
            <div>
                <div style="font-size:3rem;margin-bottom:10px;">📊</div>
                <h3 style="margin-bottom:10px;">Monthly Analytics Coming Soon</h3>
                <p style="color:var(--clr-text-muted);font-size:0.9rem;">We are gathering more data to show you beautiful charts of your monthly growth.</p>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
