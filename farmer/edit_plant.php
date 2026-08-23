<?php
// =============================================================================
// PROJECT   : GreenThumb - Rubber Plant Nursery Web Application
// FILE      : farmer/edit_plant.php
// PURPOSE   : Farmer Dashboard - Edit Details & Single Image Delete Feature
// =============================================================================

session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header('Location: /Rudder_plant/login.html?error=' . urlencode('Please log in as a farmer.'));
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: manage_plants.php');
    exit;
}

$plant_id = (int)$_GET['id'];

// ── 1. SINGLE IMAGE DELETE LOGIC (Remove a single image only) ──
if (isset($_GET['delete_img_id'])) {
    $img_id = (int)$_GET['delete_img_id'];
    
    // First find the image path
    $img_res = $conn->query("SELECT image_path FROM plant_images WHERE id = $img_id AND plant_id = $plant_id");
    if ($img_row = $img_res->fetch_assoc()) {
        $full_path = __DIR__ . '/../uploads/' . $img_row['image_path'];
        
        // Delete the file from the folder
        if (file_exists($full_path)) {
            @unlink($full_path);
        }
        
      // Delete only the record from the database
        $conn->query("DELETE FROM plant_images WHERE id = $img_id");
        
        echo "<script>alert('පින්තූරය සාර්ථකව ඉවත් කරන ලදී!'); window.location='edit_plant.php?id=$plant_id';</script>";
        exit;
    }
}

// ── 2. UPDATE PLANT DETAILS LOGIC ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_plant'])) {
    $plant_name = $_POST['plant_name'];
    $scientific_name = $_POST['scientific_name'] ?? '';
    $category_id = (int)$_POST['category_id'];
    $price = (float)$_POST['price'];
    $stock_quantity = (int)$_POST['stock_quantity'];
    $description = $_POST['description'];
    
    $query = "UPDATE plants SET plant_name = ?, scientific_name = ?, category_id = ?, price_per_unit = ?, stock_quantity = ?, description = ? WHERE plant_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssiddsi", $plant_name, $scientific_name, $category_id, $price, $stock_quantity, $description, $plant_id);
    
    if ($stmt->execute()) {
       // Saving newly added images if available
        if (!empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_name = time() . '_' . basename($_FILES['images']['name'][$key]);
                    $target_dir = __DIR__ . "/../uploads/plants/" . $file_name;
                    
                    if (move_uploaded_file($tmp_name, $target_dir)) {
                        $img_query = "INSERT INTO plant_images (plant_id, image_path) VALUES (?, ?)";
                        $img_stmt = $conn->prepare($img_query);
                        $img_path = "plants/" . $file_name;
                        $img_stmt->bind_param("is", $plant_id, $img_path);
                        $img_stmt->execute();
                    }
                }
            }
        }
        echo "<script>alert('තොරතුරු සාර්ථකව යාවත්කාලීන කරන ලදී!'); window.location='manage_plants.php?action=view';</script>";
        exit;
    }
}

// Fetching current plant data
$plant_res = $conn->query("SELECT * FROM plants WHERE plant_id = $plant_id");
$plant = $plant_res->fetch_assoc();

if (!$plant) {
    echo "<script>alert('පැළය සොයාගත නොහැක!'); window.location='manage_plants.php';</script>";
    exit;
}

$page_title = 'Farmer Dashboard - Edit Plant';
require_once __DIR__ . '/../components/header.php';
?>

<div class="container" style="margin-top: var(--space-xl); margin-bottom: var(--space-2xl); font-family: sans-serif;">
    <div style="margin-bottom: var(--space-md);">
        <a href="manage_plants.php?action=view" style="color: #2e7d32; text-decoration: none; font-weight: bold;">❮ Back to All Plants List</a>
    </div>

    <style>
        .fixed-input {
            width: 100% !important; 
            padding: 12px !important; 
            border-radius: 6px !important; 
            border: 1px solid #ccc !important; 
            box-sizing: border-box !important;
            background-color: #ffffff !important; 
            color: #222222 !important; 
            font-size: 1rem !important;
        }
        .fixed-input:focus {
            border-color: #2e7d32 !important;
            outline: none !important;
            background-color: #fff !important;
            color: #000 !important;
        }
        .img-container {
            position: relative;
            display: inline-block;
        }
        .delete-img-btn {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #d32f2f;
            color: white;
            border: none;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            text-decoration: none;
            font-weight: bold;
        }
        .delete-img-btn:hover {
            background: #b71c1c;
        }
    </style>

    <div class="card" style="max-width: 700px; margin: 0 auto; border: 1px solid #ffa000; border-radius: 10px; background: white; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
        <div class="card-body" style="padding: var(--space-xl);">
            <h2 style="color: #e65100; margin-bottom: var(--space-sm); font-weight: bold;">✏️ Edit Rubber Plant Clone Details</h2>
            <p class="text-muted" style="margin-bottom: var(--space-md); color: #666;">Update fields or remove specific images below.</p>
            
            <form action="edit_plant.php?id=<?= $plant_id ?>" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 18px;">
                
                <div class="form-group">
                    <label style="font-weight: bold; display:block; margin-bottom:6px; color: #2e7d32;">Clone Name *</label>
                    <input type="text" name="plant_name" class="fixed-input" value="<?= htmlspecialchars($plant['plant_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label style="font-weight: bold; display:block; margin-bottom:6px; color: #2e7d32;">Scientific Name</label>
                    <input type="text" name="scientific_name" class="fixed-input" value="<?= htmlspecialchars($plant['scientific_name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label style="font-weight: bold; display:block; margin-bottom:6px; color: #2e7d32;">Category *</label>
                    <select name="category_id" class="fixed-input" required style="height: auto;">
                        <?php 
                        $categories = $conn->query("SELECT * FROM plant_categories ORDER BY category_name ASC");
                        while($cat = $categories->fetch_assoc()): 
                            $selected = ($cat['category_id'] == $plant['category_id']) ? 'selected' : '';
                        ?>
                            <option value="<?= $cat['category_id'] ?>" <?= $selected ?> style="color: #333;"><?= htmlspecialchars($cat['category_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <label style="font-weight: bold; display:block; margin-bottom:6px; color: #2e7d32;">Price per Unit (Rs.) *</label>
                        <input type="number" step="0.01" name="price" class="fixed-input" value="<?= htmlspecialchars($plant['price_per_unit']) ?>" required>
                    </div>
                    <div>
                        <label style="font-weight: bold; display:block; margin-bottom:6px; color: #2e7d32;">Available Stock *</label>
                        <input type="number" name="stock_quantity" class="fixed-input" value="<?= htmlspecialchars($plant['stock_quantity']) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label style="font-weight: bold; display:block; margin-bottom:6px; color: #2e7d32;">Description</label>
                    <textarea name="description" class="fixed-input" rows="4" style="resize: vertical;"><?= htmlspecialchars($plant['description']) ?></textarea>
                </div>

                <div>
                    <label style="font-weight: bold; display:block; margin-bottom:8px; color: #333;">Current Images (Click ❌ to remove individual image):</label>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; background: #f9f9f9; padding: 15px; border-radius: 8px; border: 1px solid #eee;">
                        <?php
                        $imgs = $conn->query("SELECT * FROM plant_images WHERE plant_id = $plant_id");
                        if ($imgs->num_rows > 0):
                            while($im = $imgs->fetch_assoc()):
                        ?>
                            <div class="img-container">
                                <img src="../uploads/<?= htmlspecialchars($im['image_path']) ?>" style="width: 90px; height: 90px; object-fit: cover; border-radius: 6px; border: 1px solid #ccc; display: block;">
                                <a href="edit_plant.php?id=<?= $plant_id ?>&delete_img_id=<?= $im['id'] ?>" class="delete-img-btn" onclick="return confirm('මෙම පින්තූරය පමණක් ඉවත් කිරීමට අවශ්‍යද?');" title="Delete this image">✕</a>
                            </div>
                        <?php 
                            endwhile;
                        else:
                            echo "<span style='color: #999; font-size: 0.9rem;'>No images uploaded for this plant.</span>";
                        endif;
                        ?>
                    </div>
                </div>

                <div style="background: #fff8e1; padding: 20px; border-radius: 8px; border: 2px dashed #ffa000; text-align: center;">
                    <label style="font-weight: bold; color: #e65100; display:block; cursor: pointer; margin-bottom: 5px;">📷 Add More New Images (Optional)</label>
                    <input type="file" name="images[]" multiple style="color: #333; font-weight: bold; font-size: 0.9rem; margin: 10px auto 0 auto; display: block;">
                </div>

                <button type="submit" name="update_plant" style="background: #ffa000; color: white; padding: 14px; font-size: 1.1rem; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; margin-top: 10px; transition: background 0.2s;">
                    🔄 Update Plant Details
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
