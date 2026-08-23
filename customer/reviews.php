<?php
session_start();
require_once '../config/db.php';

$msg = "";

// Save Review
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_review'])) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Default to 1 if session not set
    $plant_id = intval($_POST['plant_id']);
    $rating = intval($_POST['rating']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);

    $sql = "INSERT INTO reviews (user_id, plant_id, rating, comment) VALUES ('$user_id', '$plant_id', '$rating', '$comment')";
    if (mysqli_query($conn, $sql)) {
        $msg = "<p style='color:green;'>Review added successfully!</p>";
    }
}

// Fetch Reviews
$reviews_query = "SELECT r.*, p.name as plant_name FROM reviews r LEFT JOIN plants p ON r.plant_id = p.plant_id ORDER BY r.created_at DESC";
$reviews_result = mysqli_query($conn, $reviews_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Plant Reviews - GreenThumb</title>
</head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2>Customer Reviews & Ratings</h2>
    <?php echo $msg; ?>

    <form action="reviews.php" method="POST" style="margin-bottom: 30px; background: #f4f4f4; padding: 15px; width: 400px;">
        <h3>Leave a Review</h3>
        <label>Plant ID:</label><br>
        <input type="number" name="plant_id" required style="width: 100%;"><br><br>
        
        <label>Rating (1 to 5 Stars):</label><br>
        <select name="rating" style="width: 100%;">
            <option value="5">5 Stars</option>
            <option value="4">4 Stars</option>
            <option value="3">3 Stars</option>
            <option value="2">2 Stars</option>
            <option value="1">1 Star</option>
        </select><br><br>
        
        <label>Comment:</label><br>
        <textarea name="comment" rows="3" style="width: 100%;"></textarea><br><br>
        
        <button type="submit" name="add_review" style="background: #28a745; color: white; border: none; padding: 8px 15px;">Submit Review</button>
    </form>

    <h3>All Reviews</h3>
    <table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <tr style="background: #ddd;">
            <th>ID</th>
            <th>Plant</th>
            <th>Rating</th>
            <th>Comment</th>
            <th>Date</th>
        </tr>
        <?php while($row = mysqli_fetch_assoc($reviews_result)): ?>
        <tr>
            <td><?php echo $row['review_id']; ?></td>
            <td><?php echo $row['plant_name'] ? $row['plant_name'] : 'Plant #'.$row['plant_id']; ?></td>
            <td><?php echo str_repeat("⭐", $row['rating']); ?></td>
            <td><?php echo $row['comment']; ?></td>
            <td><?php echo $row['created_at']; ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>