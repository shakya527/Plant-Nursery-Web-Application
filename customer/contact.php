<?php
session_start();
require_once '../config/db.php';

$message_status = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);

    $sql = "INSERT INTO inquiries (name, email, subject, message) VALUES ('$name', '$email', '$subject', '$message')";
    
    if (mysqli_query($conn, $sql)) {
        $message_status = "<div style='color:green;'>Your message has been sent successfully!</div>";
    } else {
        $message_status = "<div style='color:red;'>Error: " . mysqli_error($conn) . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contact Us - GreenThumb</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php include '../components/header.php'; ?>

    <div style="width: 50%; margin: 30px auto; font-family: Arial, sans-serif;">
        <h2>Contact Us / Inquiries</h2>
        <?php echo $message_status; ?>
        <form action="contact.php" method="POST" style="display: flex; flex-direction: column; gap: 10px;">
            <label>Name:</label>
            <input type="text" name="name" required style="padding: 8px;">
            
            <label>Email:</label>
            <input type="email" name="email" required style="padding: 8px;">
            
            <label>Subject:</label>
            <input type="text" name="subject" required style="padding: 8px;">
            
            <label>Message:</label>
            <textarea name="message" rows="5" required style="padding: 8px;"></textarea>
            
            <button type="submit" style="padding: 10px; background-color: #2e7d32; color: white; border: none; cursor: pointer;">Send Message</button>
        </form>
    </div>

    <?php include '../components/footer.php'; ?>
</body>
</html>
