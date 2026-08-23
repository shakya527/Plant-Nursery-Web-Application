<?php
// =============================================================================
// PROJECT   : GreenThumb - Rubber Plant Nursery Web Application
// FILE      : farmer/view_invoice.php
// PURPOSE   : Displays a printable invoice for a specific order.
//             (Farmer version - identical presentation, different auth logic).
// OBJECTIVE : Obj 5 – Automated printable bills.
// =============================================================================

session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header('Location: /Rudder_plant/login.html');
    exit;
}

$order_id = (int) ($_GET['order_id'] ?? 0);

// Fetch order header
$order = db_query_one("SELECT * FROM orders WHERE order_id = ?", "i", [$order_id]);

if (!$order) {
    die("<div style='padding:40px;text-align:center;color:red;font-family:Arial;'>Order not found.</div>");
}

// Fetch order items
$items = db_query("SELECT * FROM order_items WHERE order_id = ?", "i", [$order_id]);

$page_title = 'Invoice #' . $order['order_id'];
$active_nav = 'farmer_orders';
require_once __DIR__ . '/../components/header.php';

// Include the exact same HTML template as the customer invoice
// We use a shared view structure to maintain DRY principles, or just output it directly here.
?>

<div class="container fade-in" style="padding-top: var(--space-xl);">
    <div class="flex justify-between items-center no-print" style="margin-bottom: var(--space-lg);">
        <a href="orders.php" class="btn btn-secondary">← Back to Orders</a>
        <button onclick="window.print()" class="btn btn-primary">🖨️ Print Invoice</button>
    </div>

    <!-- ── Printable Invoice Panel ────────────────────────────────────────── -->
    <div class="card" style="background: white; color: #333; padding: 40px; border-radius: 8px;">
        
        <!-- Header -->
        <div class="flex justify-between items-center" style="border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 30px;">
            <div>
                <h1 style="font-family:var(--font-heading);color:#22c55e;font-size:2.5rem;margin-bottom:0;">GreenThumb</h1>
                <p style="color:#666;font-size:0.9rem;">Rubber Plant Nursery</p>
                <p style="color:#666;font-size:0.9rem;">No 12, Colombo Road, Ratnapura</p>
            </div>
            <div style="text-align:right;">
                <h2 style="font-size:2rem;color:#333;margin-bottom:8px;font-family:var(--font-heading);">INVOICE</h2>
                <div style="font-weight:bold;font-size:1.1rem;color:#22c55e;">#INV-<?= str_pad($order['order_id'], 6, '0', STR_PAD_LEFT) ?></div>
                <div style="color:#666;font-size:0.9rem;">Date: <?= date('d M Y', strtotime($order['created_at'])) ?></div>
                <div style="color:#666;font-size:0.9rem;margin-top:8px;">
                    Status: <strong style="text-transform:uppercase;color:#333;"><?= htmlspecialchars($order['order_status']) ?></strong>
                </div>
            </div>
        </div>

        <!-- Addresses -->
        <div class="grid-2" style="margin-bottom: 40px;">
            <div>
                <h3 style="font-size:1rem;color:#888;text-transform:uppercase;margin-bottom:10px;">Billed To / Ship To:</h3>
                <div style="font-size:1rem;font-weight:bold;margin-bottom:4px;"><?= htmlspecialchars($order['shipping_name']) ?></div>
                <div style="color:#555;font-size:0.95rem;line-height:1.5;">
                    <?= nl2br(htmlspecialchars($order['shipping_address'])) ?><br>
                    <?= htmlspecialchars($order['shipping_city']) ?>, <?= htmlspecialchars($order['shipping_state']) ?> <?= htmlspecialchars($order['shipping_postcode']) ?><br>
                    Phone: <?= htmlspecialchars($order['shipping_phone']) ?>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <table style="width:100%;border-collapse:collapse;margin-bottom:30px;">
            <thead>
                <tr style="background:#f8f9fa;border-bottom:2px solid #ddd;">
                    <th style="padding:12px;text-align:left;color:#333;">Item Description</th>
                    <th style="padding:12px;text-align:center;color:#333;">Qty</th>
                    <th style="padding:12px;text-align:right;color:#333;">Unit Price</th>
                    <th style="padding:12px;text-align:right;color:#333;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px;color:#444;font-weight:500;"><?= htmlspecialchars($item['plant_name']) ?></td>
                    <td style="padding:12px;text-align:center;color:#444;"><?= $item['quantity'] ?></td>
                    <td style="padding:12px;text-align:right;color:#444;">Rs. <?= number_format($item['unit_price'], 2) ?></td>
                    <td style="padding:12px;text-align:right;color:#333;font-weight:bold;">Rs. <?= number_format($item['line_total'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div style="width:300px;margin-left:auto;">
            <div style="display:flex;justify-content:space-between;padding:8px 12px;color:#666;">
                <span>Subtotal:</span>
                <span>Rs. <?= number_format($order['total_amount'] - $order['shipping_cost'], 2) ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:8px 12px;color:#666;border-bottom:2px solid #eee;">
                <span>Shipping:</span>
                <span>Rs. <?= number_format($order['shipping_cost'], 2) ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:16px 12px;font-size:1.4rem;font-weight:bold;color:#22c55e;">
                <span>Total:</span>
                <span>Rs. <?= number_format($order['total_amount'], 2) ?></span>
            </div>
        </div>

        <?php if ($order['customer_notes']): ?>
            <div style="margin-top:40px;padding:15px;background:#f8f9fa;border-radius:4px;color:#555;font-size:0.9rem;">
                <strong>Order Notes:</strong><br>
                <?= nl2br(htmlspecialchars($order['customer_notes'])) ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
