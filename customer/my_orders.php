<?php
// =============================================================================
// PROJECT   : GreenThumb - Rubber Plant Nursery Web Application
// FILE      : customer/my_orders.php
// PURPOSE   : Lists the customer's historical orders and their statuses.
// OBJECTIVE : Obj 2 – Order tracking logic.
// =============================================================================

session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: /Rudder_plant/login.html');
    exit;
}

$customer_id = (int) $_SESSION['user_id'];

$orders = db_query(
    "SELECT o.order_id, o.order_status, o.total_amount, o.created_at, COUNT(oi.item_id) as items_count 
     FROM orders o 
     LEFT JOIN order_items oi ON o.order_id = oi.order_id 
     WHERE o.customer_id = ? 
     GROUP BY o.order_id 
     ORDER BY o.created_at DESC", 
     "i", [$customer_id]
);

$page_title = 'My Orders';
$active_nav = 'my_orders';
require_once __DIR__ . '/../components/header.php';
?>

<div class="page-hero">
    <div class="container">
        <h1>📋 My Orders</h1>
        <p>Track the status of your rubber plants and view past invoices.</p>
    </div>
</div>

<div class="container fade-in">
    <?php if (empty($orders)): ?>
        <div class="card" style="text-align:center;padding:var(--space-2xl);">
            <div style="font-size:4rem;margin-bottom:var(--space-md);">📦</div>
            <h2>No orders yet</h2>
            <p style="margin-bottom:var(--space-lg);">You haven't placed any orders with us yet.</p>
            <a href="catalog.php" class="btn btn-primary">Browse Catalog</a>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Total Amount</th>
                        <th>Status</th>
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
                        ?>
                        <tr>
                            <td><strong>#<?= $order['order_id'] ?></strong></td>
                            <td><?= date('d M Y', strtotime($order['created_at'])) ?></td>
                            <td><?= $order['items_count'] ?> item(s)</td>
                            <td>Rs. <?= number_format($order['total_amount'], 2) ?></td>
                            <td><span class="badge <?= $badge ?>"><?= ucfirst(htmlspecialchars($order['order_status'])) ?></span></td>
                            <td>
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    
                                    <a href="view_invoice.php?order_id=<?= $order['order_id'] ?>" class="btn btn-sm btn-secondary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; font-size: 0.85rem;">
                                        🧾 View Invoice
                                    </a>

                                    <?php if (strtolower($order['order_status']) === 'pending'): ?>
                                        <form action="cancel_order.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this order?');" style="margin: 0; display: inline-block;">
                                            <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                            <button type="submit" style="background: #ef5350; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 0.85rem; font-weight: bold; transition: background 0.2s;" onmouseover="this.style.background='#c62828'" onmouseout="this.style.background='#ef5350'">
                                                🗑️ Cancel
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
