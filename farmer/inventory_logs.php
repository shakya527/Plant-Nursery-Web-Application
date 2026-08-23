<?php
session_start();
require_once '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_log'])) {
    $plant_id = intval($_POST['plant_id']);
    $change_type = mysqli_real_escape_string($conn, $_POST['change_type']);
    $quantity = intval($_POST['quantity']);
    $note = mysqli_real_escape_string($conn, $_POST['note']);

    $sql = "INSERT INTO inventory_logs (plant_id, change_type, quantity, note) 
            VALUES ('$plant_id', '$change_type', '$quantity', '$note')";
    mysqli_query($conn, $sql);
}

$logs = mysqli_query($conn, "SELECT l.*, p.name as plant_name FROM inventory_logs l LEFT JOIN plants p ON l.plant_id = p.plant_id ORDER BY l.created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Stock Logs - GreenThumb</title>
</head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2>Stock / Inventory History Logs</h2>

    <form action="inventory_logs.php" method="POST" style="margin-bottom: 30px; background: #f4f4f4; padding: 15px; width: 400px;">
        <h3>Log Stock Change</h3>
        <label>Plant ID:</label><br>
        <input type="number" name="plant_id" required style="width: 100%;"><br><br>
        
        <label>Change Type:</label><br>
        <select name="change_type" style="width: 100%;">
            <option value="STOCK_IN">Stock In (අලුතින් එකතු වූ)</option>
            <option value="STOCK_OUT">Stock Out (විකුණූ/අඩු වූ)</option>
            <option value="DAMAGED">Damaged (හානි වූ)</option>
        </select><br><br>

        <label>Quantity:</label><br>
        <input type="number" name="quantity" required style="width: 100%;"><br><br>

        <label>Note/Reason:</label><br>
        <textarea name="note" rows="2" style="width: 100%;"></textarea><br><br>
        
        <button type="submit" name="add_log" style="background: #28a745; color: white; border: none; padding: 8px 15px;">Log Change</button>
    </form>

    <h3>Stock Logs History</h3>
    <table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <tr style="background: #ddd;">
            <th>Log ID</th>
            <th>Plant</th>
            <th>Type</th>
            <th>Qty</th>
            <th>Note</th>
            <th>Date</th>
        </tr>
        <?php while($l = mysqli_fetch_assoc($logs)): ?>
        <tr>
            <td><?php echo $l['log_id']; ?></td>
            <td><?php echo $l['plant_name'] ? $l['plant_name'] : 'Plant #'.$l['plant_id']; ?></td>
            <td><b><?php echo $l['change_type']; ?></b></td>
            <td><?php echo $l['quantity']; ?></td>
            <td><?php echo $l['note']; ?></td>
            <td><?php echo $l['created_at']; ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>