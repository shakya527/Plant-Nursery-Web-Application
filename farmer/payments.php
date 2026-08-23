<?php
session_start();
require_once '../config/db.php';

// Payment record එකක් manually දාන්න හෝ Status මාරු කරන්න
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_payment'])) {
    $order_id = intval($_POST['order_id']);
    $method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $ref = mysqli_real_escape_string($conn, $_POST['transaction_reference']);
    $amount = floatval($_POST['amount']);
    $status = mysqli_real_escape_string($conn, $_POST['payment_status']);

    $sql = "INSERT INTO payments (order_id, payment_method, transaction_reference, amount, payment_status) 
            VALUES ('$order_id', '$method', '$ref', '$amount', '$status')";
    mysqli_query($conn, $sql);
}

$payments = mysqli_query($conn, "SELECT * FROM payments ORDER BY payment_date DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Payments - GreenThumb</title>
</head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2>Payment Transactions</h2>

    <form action="payments.php" method="POST" style="margin-bottom: 30px; background: #f4f4f4; padding: 15px; width: 400px;">
        <h3>Record New Payment</h3>
        <label>Order ID:</label><br>
        <input type="number" name="order_id" required style="width: 100%;"><br><br>
        
        <label>Method:</label><br>
        <select name="payment_method" style="width: 100%;">
            <option value="Bank Deposit">Bank Deposit</option>
            <option value="Cash on Delivery">Cash on Delivery</option>
            <option value="Online Card">Online Card</option>
        </select><br><br>

        <label>Transaction Reference/Slip No:</label><br>
        <input type="text" name="transaction_reference" style="width: 100%;"><br><br>
        
        <label>Amount (Rs.):</label><br>
        <input type="number" step="0.01" name="amount" required style="width: 100%;"><br><br>

        <label>Status:</label><br>
        <select name="payment_status" style="width: 100%;">
            <option value="APPROVED">APPROVED</option>
            <option value="PENDING">PENDING</option>
            <option value="REJECTED">REJECTED</option>
        </select><br><br>
        
        <button type="submit" name="add_payment" style="background: #28a745; color: white; border: none; padding: 8px 15px;">Save Payment Record</button>
    </form>

    <h3>All Payments List</h3>
    <table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <tr style="background: #ddd;">
            <th>ID</th>
            <th>Order ID</th>
            <th>Method</th>
            <th>Reference</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Date</th>
        </tr>
        <?php while($p = mysqli_fetch_assoc($payments)): ?>
        <tr>
            <td><?php echo $p['payment_id']; ?></td>
            <td>#<?php echo $p['order_id']; ?></td>
            <td><?php echo $p['payment_method']; ?></td>
            <td><?php echo $p['transaction_reference']; ?></td>
            <td>Rs. <?php echo number_format($p['amount'], 2); ?></td>
            <td><b><?php echo $p['payment_status']; ?></b></td>
            <td><?php echo $p['payment_date']; ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>