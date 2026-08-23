<?php
// =============================================================================
// PROJECT   : GreenThumb - Rubber Plant Nursery Web Application
// FILE      : customer/checkout.php
// PURPOSE   : Captures shipping details and processes the final order with Payment Slip.
// =============================================================================

session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: /Rudder_plant/login.html');
    exit;
}

if (empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

$customer_id = (int) $_SESSION['user_id'];
$user = db_query_one("SELECT full_name, phone, address FROM users WHERE user_id = ?", "i", [$customer_id]);

// Calculate Cart Totals
$cart_total = 0.00;
$shipping_cost = 15.00; // Flat rate
$cart_items = [];

$placeholders = implode(',', array_fill(0, count($_SESSION['cart']), '?'));
$types = str_repeat('i', count($_SESSION['cart']));
$ids = array_keys($_SESSION['cart']);

$plants = db_query("SELECT plant_id, plant_name, price_per_unit, stock_quantity FROM plants WHERE plant_id IN ($placeholders) FOR UPDATE", $types, $ids);

foreach ($plants as $p) {
    $qty = $_SESSION['cart'][$p['plant_id']];
    if ($qty > $p['stock_quantity']) {
        header('Location: cart.php?error=' . urlencode("Stock changed for {$p['plant_name']}. Please review your cart."));
        exit;
    }
    $line_total = $qty * $p['price_per_unit'];
    $cart_total += $line_total;
    $p['cart_qty'] = $qty;
    $p['line_total'] = $line_total;
    $cart_items[] = $p;
}
$grand_total = $cart_total + $shipping_cost;

// ── Process Checkout POST ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $s_name     = trim($_POST['shipping_name']);
    $s_phone    = trim($_POST['shipping_phone']);
    $s_address  = trim($_POST['shipping_address']);
    $s_city     = trim($_POST['shipping_city']);
    $s_state    = trim($_POST['shipping_state']);
    $s_postcode = trim($_POST['shipping_postcode']);
    $notes      = trim($_POST['customer_notes'] ?? '');

    if (!$s_name || !$s_phone || !$s_address || !$s_city || !$s_state || !$s_postcode) {
        $error = "Please fill in all required shipping fields.";
    } elseif (!isset($_FILES['payment_slip']) || $_FILES['payment_slip']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please upload the payment slip receipt.";
    } else {
        try {
            global $conn;
            
            // ── 📄 Payment Slip Upload ──
            $file_tmp = $_FILES['payment_slip']['tmp_name'];
            $original_name = basename($_FILES['payment_slip']['name']);
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            
            $slip_name = "slip_" . time() . "_" . uniqid() . "." . $ext;
            $target_dir = __DIR__ . "/../uploads/slips/";
            
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            if (!move_uploaded_file($file_tmp, $target_dir . $slip_name)) {
                throw new Exception("Failed to save uploaded slip file.");
            }

            // ── 🔒 Database Transaction Start ──
            $conn->begin_transaction();

            // [FIXED] Bind types, SQL Columns සහ Passed parameters 12ම හරියටම ගලපා නිවැරදි කර ඇත.
            db_query(
                "INSERT INTO orders (customer_id, shipping_name, shipping_phone, shipping_address, shipping_city, shipping_state, shipping_postcode, total_amount, shipping_cost, customer_notes, payment_slip, order_status) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                "issssssddsss",
                [$customer_id, $s_name, $s_phone, $s_address, $s_city, $s_state, $s_postcode, $grand_total, $shipping_cost, $notes, $slip_name, 'pending']
            );
            $order_id = db_last_id();

            // Create Order Items & Decrement Stock
            foreach ($cart_items as $item) {
                db_query(
                    "INSERT INTO order_items (order_id, plant_id, plant_name, quantity, unit_price, line_total) 
                     VALUES (?, ?, ?, ?, ?, ?)",
                    "iisidd",
                    [$order_id, $item['plant_id'], $item['plant_name'], $item['cart_qty'], $item['price_per_unit'], $item['line_total']]
                );

               // Stock quantity is successfully deducted here
                db_query(
                    "UPDATE plants SET stock_quantity = stock_quantity - ? WHERE plant_id = ?",
                    "ii",
                    [$item['cart_qty'], $item['plant_id']]
                );
            }

            $conn->commit();
            
            // Clear cart
            $_SESSION['cart'] = [];
            
            header('Location: view_invoice.php?order_id=' . $order_id . '&success=1');
            exit;

        } catch (Exception $e) {
            if (isset($conn)) {
                $conn->rollback();
            }
            $error = "Checkout failed: " . $e->getMessage();
        }
    }
}

$page_title = 'Checkout';
$active_nav = 'catalog';
require_once __DIR__ . '/../components/header.php';
?>

<div class="page-hero">
    <div class="container">
        <h1>💳 Secure Checkout</h1>
        <p>Complete your shipping details to finalise your order.</p>
    </div>
</div>

<div class="container fade-in">
    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form action="checkout.php" method="POST" id="checkoutForm" enctype="multipart/form-data">
        <div style="display:grid;grid-template-columns:1fr 380px;gap:var(--space-xl);align-items:start;">
            
            <div class="card">
                <div class="card-body">
                    <h2 style="font-size:1.4rem;margin-bottom:var(--space-lg);">📍 Shipping Address</h2>
                        
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Recipient Name *</label>
                            <input type="text" name="shipping_name" class="form-control" value="<?= htmlspecialchars($_POST['shipping_name'] ?? $user['full_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Phone *</label>
                            <input type="text" name="shipping_phone" class="form-control" value="<?= htmlspecialchars($_POST['shipping_phone'] ?? $user['phone'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Street Address *</label>
                        <textarea name="shipping_address" class="form-control" rows="2" required><?= htmlspecialchars($_POST['shipping_address'] ?? $user['address'] ?? '') ?></textarea>
                    </div>

                    <div class="grid-3">
                        <div class="form-group">
                            <label class="form-label">City *</label>
                            <input type="text" name="shipping_city" class="form-control" value="<?= htmlspecialchars($_POST['shipping_city'] ?? '') ?>" placeholder="e.g. Kegalle, Monaragala, Bibila" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">State *</label>
                            <input type="text" name="shipping_state" class="form-control" value="<?= htmlspecialchars($_POST['shipping_state'] ?? '') ?>" placeholder="e.g. Sabaragamuwa" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Postcode *</label>
                            <input type="text" name="shipping_postcode" class="form-control" value="<?= htmlspecialchars($_POST['shipping_postcode'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Order Notes (Optional)</label>
                        <textarea name="customer_notes" class="form-control" rows="2" placeholder="Special delivery instructions..."><?= htmlspecialchars($_POST['customer_notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card" style="position: sticky; top: calc(var(--navbar-h) + 20px);">
                <div class="card-body">
                    <h3 style="font-size:1.2rem;margin-bottom:var(--space-md);">🧾 Order Summary</h3>
                    
                    <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:var(--space-lg);">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="flex justify-between items-center" style="font-size:0.9rem;">
                                <div>
                                    <span style="color:var(--clr-primary);font-weight:700;"><?= $item['cart_qty'] ?>x</span>
                                    <?= htmlspecialchars($item['plant_name']) ?>
                                </div>
                                <div style="font-weight:600;">Rs. <?= number_format($item['line_total'], 2) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="divider"></div>

                    <div class="flex justify-between" style="font-size:0.9rem;margin-bottom:8px;">
                        <span style="color:var(--clr-text-muted);">Subtotal</span>
                        <span>Rs. <?= number_format($cart_total, 2) ?></span>
                    </div>
                    <div class="flex justify-between" style="font-size:0.9rem;margin-bottom:16px;">
                        <span style="color:var(--clr-text-muted);">Shipping (Flat Rate)</span>
                        <span>Rs. <?= number_format($shipping_cost, 2) ?></span>
                    </div>

                    <div class="flex justify-between items-center" style="margin-bottom:var(--space-lg);">
                        <span style="font-size:1.1rem;font-weight:700;">Grand Total</span>
                        <span style="font-family:var(--font-heading);font-size:1.8rem;font-weight:800;color:var(--clr-primary);">Rs. <?= number_format($grand_total, 2) ?></span>
                    </div>

                    <div style="margin-top: 25px; border-top: 1px dashed rgba(255,255,255,0.15); padding-top: 20px;">
                        <label style="font-weight: 600; color: #a5d6a7; display: block; margin-bottom: 10px; font-size: 0.95rem; letter-spacing: 0.5px;">
                            📄 Upload Payment Slip (Bank Receipt / PDF / Image) *
                        </label>
                        
                        <div class="custom-file-upload" style="position: relative; margin-bottom: 20px;">
                            <input type="file" name="payment_slip" id="payment_slip" accept="image/*,application/pdf" required 
                                   style="position: absolute; left: 0; top: 0; opacity: 0; width: 100%; height: 100%; cursor: pointer; z-index: 2;"
                                   onchange="updateFileName(this)">
                            
                            <div id="upload-zone" style="background: rgba(76, 175, 80, 0.04); border: 2px dashed #4caf50; padding: 22px 15px; border-radius: 10px; text-align: center; transition: all 0.3s ease; box-shadow: inset 0 1px 3px rgba(0,0,0,0.2);">
                                <div id="upload-icon" style="font-size: 1.8rem; margin-bottom: 8px; color: #4caf50;">📤</div>
                                <span id="file-inside-text" style="color: #e0e0e0; font-size: 0.9rem; font-weight: 500; display: block;">
                                    Click to Choose or Drag File Here
                                </span>
                                <small style="display: block; color: #888888; margin-top: 6px; font-size: 0.78rem;">
                                    Accepted formats: JPG, PNG, PDF
                                </small>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg btn-full" style="font-size: 1.05rem; background: #00e676; color: #111111; font-weight: 700; padding: 14px; border: none; border-radius: 8px; width: 100%; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 15px rgba(0, 230, 118, 0.25); transition: all 0.2s ease;">
                            🔒 Confirm & Pay
                        </button>
                    </div>

                    <script>
                    function updateFileName(input) {
                        const zone = document.getElementById('upload-zone');
                        const text = document.getElementById('file-inside-text');
                        const icon = document.getElementById('upload-icon');
                        
                        if (input.files && input.files.length > 0) {
                            const fileName = input.files[0].name;
                            text.innerText = fileName;
                            text.style.color = '#00e676';
                            icon.innerText = '✅';
                            zone.style.borderColor = '#00e676';
                            zone.style.background = 'rgba(0, 230, 118, 0.04)';
                        } else {
                            text.innerText = 'Click to Choose or Drag File Here';
                            text.style.color = '#e0e0e0';
                            icon.innerText = '📤';
                            zone.style.borderColor = '#4caf50';
                            zone.style.background = 'rgba(76, 175, 80, 0.04)';
                        }
                    }
                    </script>

                </div>
            </div>

        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
