<?php
// =============================================================================
// PROJECT   : GreenThumb - Rubber Plant Nursery Web Application
// FILE      : farmer/orders.php
// PURPOSE   : Lists orders containing plants that belong to this specific farmer.
//             Allows the farmer to update the status of these orders.
// OBJECTIVE : Obj 3 & 5 – Farmer Order Management.
// =============================================================================

session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header('Location: /Rudder_plant/login.html');
    exit;
}

$farmer_id = (int) $_SESSION['user_id'];

// ── Handle Status Update ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id = (int) $_POST['order_id'];
    $new_status = $_POST['order_status'];
    
    // Validate status
    $valid_statuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
    if (in_array($new_status, $valid_statuses)) {
        db_query("UPDATE orders SET order_status = ? WHERE order_id = ?", "si", [$new_status, $order_id]);
        header('Location: orders.php?success=' . urlencode('Order status updated.'));
        exit;
    }
}

// ── Fetch Orders ─────────────────────────────────────────────────────────────
$sql = "SELECT o.order_id, o.shipping_name, o.shipping_city, o.order_status, o.total_amount, o.created_at, COUNT(oi.item_id) as items_count 
        FROM orders o 
        JOIN order_items oi ON o.order_id = oi.order_id
        GROUP BY o.order_id, o.shipping_name, o.shipping_city, o.order_status, o.total_amount, o.created_at
        ORDER BY o.created_at DESC";

$orders = db_query($sql);
$page_title = 'Manage Orders';
$active_nav = 'farmer_orders';
require_once __DIR__ . '/../components/header.php';
?>

<div class="page-hero">
    <div class="container">
        <h1>📦 Manage Orders</h1>
        <p>View customer orders and update their delivery status.</p>
    </div>
</div>

<div class="container fade-in">
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-error" style="background:rgba(0, 230, 118, 0.15); border:1px solid #00e676; color:#00e676;">
            <?= htmlspecialchars($_GET['success']) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
        <div class="card" style="text-align:center;padding:var(--space-2xl);">
            <div style="font-size:4rem;margin-bottom:var(--space-md);">📭</div>
            <h2>No orders yet</h2>
            <p>Customer orders for your plants will appear here.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>City</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Update</th>
                        <th>Invoice</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <?php
                        $badge = match($order['order_status']) {
                            'pending'   => 'badge-pending',
                            'confirmed' => 'badge-confirmed',
                            'shipped'   => 'badge-shipped',
                            'delivered' => 'badge-delivered',
                            'cancelled' => 'badge-cancelled',
                            default     => 'badge-muted'
                        };
                        
                        $is_edit = isset($_GET['edit']) && $_GET['edit'] == $order['order_id'];
                        ?>
                        <tr>
                            <td><strong>#<?= $order['order_id'] ?></strong></td>
                            <td><?= date('d M Y', strtotime($order['created_at'])) ?></td>
                            <td><?= htmlspecialchars($order['shipping_name']) ?></td>
                            <td><?= htmlspecialchars($order['shipping_city']) ?></td>
                            <td>Rs. <?= number_format($order['total_amount'], 2) ?></td>
                            
                            <?php if ($is_edit): ?>
                                <td colspan="2">
                                    <form action="orders.php" method="POST" style="display:flex;gap:8px;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                        <select name="order_status" class="form-control" style="padding:4px 8px;font-size:0.85rem;">
                                            <option value="pending" <?= $order['order_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="confirmed" <?= $order['order_status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                            <option value="shipped" <?= $order['order_status'] === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                            <option value="delivered" <?= $order['order_status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                            <option value="cancelled" <?= $order['order_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                        <a href="orders.php" class="btn btn-sm btn-ghost">Cancel</a>
                                    </form>
                                </td>
                            <?php else: ?>
                                <td><span class="badge <?= $badge ?>"><?= ucfirst(htmlspecialchars($order['order_status'])) ?></span></td>
                                <td>
                                    <a href="orders.php?edit=<?= $order['order_id'] ?>" class="btn btn-sm btn-secondary">✏️ Status</a>
                                </td>
                            <?php endif; ?>
                            
                            <td>
                                <a href="view_invoice.php?order_id=<?= $order['order_id'] ?>" class="btn btn-sm btn-ghost">🧾 View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
