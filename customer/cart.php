<?php
// =============================================================================
// PROJECT   : GreenThumb - Rubber Plant Nursery Web Application
// FILE      : customer/cart.php
// PURPOSE   : Session-based Shopping Cart.
//             Handles Add to Cart, Remove from Cart, and Update Quantity.
//             Displays cart contents and total before checkout.
// OBJECTIVE : Obj 2 – Secure Ordering (staging phase).
// =============================================================================

session_start();
require_once __DIR__ . '/../config/db.php';

// ── Auth Guard: Customer Only ────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: /Rudder_plant/login.html?error=' . urlencode('Please log in to use the cart.'));
    exit;
}

// Initialise cart if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = []; // Format: [plant_id => quantity]
}

// ── Handle POST Actions ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $plant_id = (int) ($_POST['plant_id'] ?? 0);
    
    if ($action === 'add' && $plant_id > 0) {
        $plant = db_query_one("SELECT stock_quantity, min_order_qty FROM plants WHERE plant_id = ? AND is_available = 1", "i", [$plant_id]);
        
        if ($plant && $plant['stock_quantity'] > 0) {
            $qty_to_add = isset($_SESSION['cart'][$plant_id]) ? 1 : max(1, (int)$plant['min_order_qty']);
            $new_qty = ($_SESSION['cart'][$plant_id] ?? 0) + $qty_to_add;
            
            if ($new_qty <= $plant['stock_quantity']) {
                $_SESSION['cart'][$plant_id] = $new_qty;
                header('Location: cart.php?success=' . urlencode('Plant added to cart.'));
            } else {
                header('Location: catalog.php?error=' . urlencode('Not enough stock available.'));
            }
        }
        exit;
    }
    
    if ($action === 'remove' && $plant_id > 0) {
        unset($_SESSION['cart'][$plant_id]);
        header('Location: cart.php');
        exit;
    }
    
    if ($action === 'update') {
        foreach ($_POST['qty'] as $id => $qty) {
            $id = (int) $id;
            $qty = (int) $qty;
            if ($qty > 0) {
                $_SESSION['cart'][$id] = $qty;
            } else {
                unset($_SESSION['cart'][$id]);
            }
        }
        header('Location: cart.php?success=' . urlencode('Cart updated.'));
        exit;
    }
}

// ── Fetch Cart Items from Database ───────────────────────────────────────────
$cart_items = [];
$cart_total = 0.00;

if (!empty($_SESSION['cart'])) {
    $placeholders = implode(',', array_fill(0, count($_SESSION['cart']), '?'));
    $types = str_repeat('i', count($_SESSION['cart']));
    $ids = array_keys($_SESSION['cart']);
    
    $plants = db_query("SELECT plant_id, plant_name, price_per_unit, stock_quantity, min_order_qty, image_filename FROM plants WHERE plant_id IN ($placeholders)", $types, $ids);
    
    foreach ($plants as $p) {
        $id = $p['plant_id'];
        $qty = $_SESSION['cart'][$id];
        
        // Auto-correct quantity if stock dropped since item was added
        if ($qty > $p['stock_quantity']) {
            $qty = $p['stock_quantity'];
            $_SESSION['cart'][$id] = $qty;
        }
        
        if ($qty > 0) {
            $line_total = $qty * $p['price_per_unit'];
            $cart_total += $line_total;
            $p['cart_qty'] = $qty;
            $p['line_total'] = $line_total;
            $cart_items[] = $p;
        } else {
            unset($_SESSION['cart'][$id]);
        }
    }
}


// =============================================================================
// PAGE RENDER
// =============================================================================
$page_title = 'Shopping Cart';
$active_nav = 'catalog';
require_once __DIR__ . '/../components/header.php';
?>

<div class="page-hero">
    <div class="container">
        <h1>🛒 Your Cart</h1>
        <p>Review your selected rubber plants before proceeding to checkout.</p>
    </div>
</div>

<div class="container fade-in">
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>
        <div class="card" style="text-align:center;padding:var(--space-2xl);">
            <div style="font-size:4rem;margin-bottom:var(--space-md);">🛍️</div>
            <h2>Your cart is empty</h2>
            <p style="margin-bottom:var(--space-lg);">Looks like you haven't added any rubber plants yet.</p>
            <a href="catalog.php" class="btn btn-primary">Browse Catalog</a>
        </div>
    <?php else: ?>
        <form action="cart.php" method="POST">
            <input type="hidden" name="action" value="update">
            
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Plant</th>
                            <th>Unit Price</th>
                            <th>Quantity</th>
                            <th>Total</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item): ?>
                        <tr>
                            <td>
                                <div class="flex items-center gap-md">
                                    <?php if ($item['image_filename']): ?>
                                        <img src="/Rudder_plant/uploads/plants/<?= htmlspecialchars($item['image_filename']) ?>" style="width:50px;height:50px;object-fit:cover;border-radius:4px;">
                                    <?php else: ?>
                                        <div style="width:50px;height:50px;background:var(--clr-bg-elevated);border-radius:4px;display:grid;place-items:center;">🌿</div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?= htmlspecialchars($item['plant_name']) ?></strong><br>
                                        <small style="color:var(--clr-text-muted);">Stock: <?= $item['stock_quantity'] ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>Rs. <?= number_format((float)$item['price_per_unit'], 2) ?></td>
                            <td style="width: 120px;">
                                <input type="number" name="qty[<?= $item['plant_id'] ?>]" value="<?= $item['cart_qty'] ?>" min="<?= $item['min_order_qty'] ?>" max="<?= $item['stock_quantity'] ?>" class="form-control" style="padding: 6px 12px;">
                            </td>
                            <td><strong>Rs. <?= number_format((float)$item['line_total'], 2) ?></strong></td>
                            <td>
                                <button type="submit" formaction="cart.php" name="action" value="remove" onclick="document.getElementById('remove_id').value=<?= $item['plant_id'] ?>;" class="btn btn-sm btn-danger">✖ Remove</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <input type="hidden" id="remove_id" name="plant_id" value="">

            <div class="flex items-center justify-between" style="margin-top:var(--space-xl);padding:var(--space-lg);background:var(--clr-bg-card);border-radius:var(--radius-lg);border:var(--glass-border);">
                <div>
                    <button type="submit" class="btn btn-secondary">🔄 Update Cart</button>
                    <a href="catalog.php" class="btn btn-ghost" style="margin-left:10px;">← Continue Shopping</a>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:0.9rem;color:var(--clr-text-muted);">Subtotal</div>
                    <div style="font-family:var(--font-heading);font-size:2rem;font-weight:800;color:var(--clr-primary);margin-bottom:var(--space-sm);">Rs. <?= number_format($cart_total, 2) ?></div>
                    <a href="checkout.php" class="btn btn-primary btn-lg">Proceed to Checkout →</a>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
