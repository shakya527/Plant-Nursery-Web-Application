<?php
session_start();
require_once '../config/db.php';

$msg = "";

// Supplier කෙනෙක් ඇඩ් කිරීම
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_supplier'])) {
    $name = mysqli_real_escape_string($conn, $_POST['supplier_name']);
    $contact = mysqli_real_escape_string($conn, $_POST['contact_person']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);

    $sql = "INSERT INTO suppliers (supplier_name, contact_person, phone, email, address) 
            VALUES ('$name', '$contact', '$phone', '$email', '$address')";
    if (mysqli_query($conn, $sql)) {
        $msg = "<p style='color:green;'>Supplier added successfully!</p>";
    }
}

// Suppliers ලාගේ List එක ගැනීම
$suppliers = mysqli_query($conn, "SELECT * FROM suppliers ORDER BY supplier_id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Suppliers - GreenThumb</title>
</head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2>Supplier Management</h2>
    <?php echo $msg; ?>

    <form action="manage_suppliers.php" method="POST" style="margin-bottom: 30px; background: #f4f4f4; padding: 15px; width: 400px;">
        <h3>Add New Supplier</h3>
        <label>Company/Supplier Name:</label><br>
        <input type="text" name="supplier_name" required style="width: 100%;"><br><br>
        
        <label>Contact Person:</label><br>
        <input type="text" name="contact_person" style="width: 100%;"><br><br>
        
        <label>Phone:</label><br>
        <input type="text" name="phone" style="width: 100%;"><br><br>

        <label>Email:</label><br>
        <input type="email" name="email" style="width: 100%;"><br><br>

        <label>Address:</label><br>
        <textarea name="address" rows="2" style="width: 100%;"></textarea><br><br>
        
        <button type="submit" name="add_supplier" style="background: #28a745; color: white; border: none; padding: 8px 15px;">Add Supplier</button>
    </form>

    <h3>Current Suppliers List</h3>
    <table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <tr style="background: #ddd;">
            <th>ID</th>
            <th>Name</th>
            <th>Contact Person</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Address</th>
        </tr>
        <?php while($s = mysqli_fetch_assoc($suppliers)): ?>
        <tr>
            <td><?php echo $s['supplier_id']; ?></td>
            <td><?php echo $s['supplier_name']; ?></td>
            <td><?php echo $s['contact_person']; ?></td>
            <td><?php echo $s['phone']; ?></td>
            <td><?php echo $s['email']; ?></td>
            <td><?php echo $s['address']; ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>