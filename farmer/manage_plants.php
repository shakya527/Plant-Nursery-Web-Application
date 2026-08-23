<?php
// =============================================================================
// PROJECT   : GreenThumb - Rubber Plant Nursery Web Application
// FILE      : farmer/manage_plants.php
// PURPOSE   : Farmer Dashboard - Add, View, Edit & Delete Plants with Dynamic Paths
// =============================================================================

session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header('Location: /Rudder_plant/login.html?error=' . urlencode('Please log in as a farmer.'));
    exit;
}

$farmer_name = htmlspecialchars($_SESSION['full_name']);
$action = $_GET['action'] ?? 'view';

// ── 1. DELETE LOGIC (පැළයක් සහ පින්තූර ඉවත් කිරීම) ─────────────────────────────
if ($action === 'delete' && isset($_GET['id'])) {
    $plant_id = (int)$_GET['id'];
    
    // ෆෝල්ඩර් එකෙන් පින්තූර file එක ඩිලීට් කරන්න
    $img_res = $conn->query("SELECT image_path FROM plant_images WHERE plant_id = $plant_id");
    while ($img = $img_res->fetch_assoc()) {
        // Database එකේ සේව් වෙලා තියෙන්නේ 'plants/filename.jpg' නිසා uploads/ එකට path එක හදනවා
        $full_path = __DIR__ . '/../uploads/' . $img['image_path'];
        if (file_exists($full_path)) {
            @unlink($full_path);
        }
    }
    $conn->query("DELETE FROM plant_images WHERE plant_id = $plant_id");
    $conn->query("DELETE FROM plants WHERE plant_id = $plant_id");
    
    echo "<script>alert('පැළය සාර්ථකව ඉවත් කරන ලදී!'); window.location='manage_plants.php?action=view';</script>";
    exit;
}

// ── 2. INSERT LOGIC (අලුත් පැළයක් සහ පින්තූර ඇතුළත් කිරීම) ───────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_plant'])) {
    $plant_name = $_POST['plant_name'];
    $scientific_name = $_POST['scientific_name'] ?? '';
    $category_id = (int)$_POST['category_id'];
    $price = (float)$_POST['price'];
    $stock_quantity = (int)$_POST['stock_quantity'];
    $description = $_POST['description'];
    
    $query = "INSERT INTO plants (plant_name, scientific_name, category_id, price_per_unit, stock_quantity, description, is_available) VALUES (?, ?, ?, ?, ?, ?, 1)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssidds", $plant_name, $scientific_name, $category_id, $price, $stock_quantity, $description);
    
    if ($stmt->execute()) {
        $plant_id = $conn->insert_id;
        
        if (!empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_name = time() . '_' . basename($_FILES['images']['name'][$key]);
                    
                    // ෆයිල් එක ඇත්තටම සේව් වෙන්නේ htdocs/Rudder_plant/uploads/plants/ ෆෝල්ඩර් එකටයි
                    $target_dir = __DIR__ . "/../uploads/plants/" . $file_name;
                    
                    if (!is_dir(__DIR__ . "/../uploads/plants/")) {
                        mkdir(__DIR__ . "/../uploads/plants/", 0777, true);
                    }
                    
                    if (move_uploaded_file($tmp_name, $target_dir)) {
                        $img_query = "INSERT INTO plant_images (plant_id, image_path) VALUES (?, ?)";
                        $img_stmt = $conn->prepare($img_query);
                        
                        // 🌟 Shop Catalog එකට ගැලපෙන්න ඩේටාබේස් එකට සේව් කරන්නේ 'plants/නම.jpg' විදිහටයි!
                        $img_path = "plants/" . $file_name;
                        
                        $img_stmt->bind_param("is", $plant_id, $img_path);
                        $img_stmt->execute();
                    }
                }
            }
        }
        echo "<script>alert('පැළය සාර්ථකව ඇතුළත් කරගන්නා ලදී!'); window.location='manage_plants.php?action=view';</script>";
        exit;
    }
}

$page_title = 'Farmer Dashboard - Manage Plants';
require_once __DIR__ . '/../components/header.php';
?>

<div class="container" style="margin-top: var(--space-xl); margin-bottom: var(--space-2xl); font-family: sans-serif;">
    
    <!-- ── VIEW ALL PLANTS TABLE ── -->
    <?php if ($action === 'view'): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-lg);">
            <h2 style="color: #1b5e20; margin: 0; font-weight: bold;">📋 My Rubber Plant Clones</h2>
            <a href="manage_plants.php?action=add" style="background: #2e7d32; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 0.9rem;">
                ➕ Add New Plant
            </a>
        </div>

        <div style="background: white; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead style="background: #e8f5e9; color: #1b5e20;">
                    <tr>
                        <th style="padding: 12px; border-bottom: 2px solid #c8e6c9; width: 80px; font-weight: bold;">Image</th>
                        <th style="padding: 12px; border-bottom: 2px solid #c8e6c9; font-weight: bold;">Clone Name</th>
                        <th style="padding: 12px; border-bottom: 2px solid #c8e6c9; font-weight: bold;">Price (Rs.)</th>
                        <th style="padding: 12px; border-bottom: 2px solid #c8e6c9; font-weight: bold;">Available Stock</th>
                        <th style="padding: 12px; border-bottom: 2px solid #c8e6c9; font-weight: bold;">Description</th>
                        <th style="padding: 12px; border-bottom: 2px solid #c8e6c9; width: 150px; text-align: center; font-weight: bold;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $plants_list = $conn->query("SELECT * FROM plants ORDER BY plant_id DESC");
                    if ($plants_list->num_rows > 0):
                        while($row = $plants_list->fetch_assoc()):
                            $pid = $row['plant_id'];
                            $img_check = $conn->query("SELECT image_path FROM plant_images WHERE plant_id = $pid LIMIT 1");
                            $img_row = $img_check->fetch_assoc();
                    ?>
                        <tr style="border-bottom: 1px solid #eee; vertical-align: middle;">
                            <!-- 📷 Image Display Fix -->
                            <td style="padding: 12px;">
                                <?php if ($img_row && !empty($img_row['image_path'])): ?>
                                    <!-- Database එකේ තියෙන plants/ කෑල්ලට ඉස්සරහින් ../uploads/ එකතු කරලා රූපය පෙන්වයි -->
                                    <img src="../uploads/<?= htmlspecialchars($img_row['image_path']) ?>" alt="Plant Image" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; border: 1px solid #ccc; display: block;">
                                <?php else: ?>
                                    <div style="width: 60px; height: 60px; background: #f5f5f5; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; border-radius: 4px; color: #bbb; border: 1px solid #eee;">🌿</div>
                                <?php endif; ?>
                            </td>
                            
                            <td style="padding: 12px; font-weight: bold; color: #333;"><?= htmlspecialchars($row['plant_name']) ?></td>
                            <td style="padding: 12px; color: #2e7d32; font-weight: bold;"><?= number_format($row['price_per_unit'], 2) ?></td>
                            
                            <!-- 📦 Stock Text Visibility Fix -->
                            <td style="padding: 12px; color: #212121; font-weight: bold; font-size: 0.95rem;">
                                <?= (int)$row['stock_quantity'] ?> <span style="color: #666; font-weight: normal;">items</span>
                            </td>
                            
                            <td style="padding: 12px; color: #555; font-size: 0.9rem; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($row['description']) ?></td>
                            
                            <!-- ⚙️ Action Buttons (Edit & Delete) -->
                            <td style="padding: 12px; text-align: center;">
                                <div style="display: flex; gap: 8px; justify-content: center;">
                                    <a href="edit_plant.php?id=<?= $pid ?>" style="background: #ffa000; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 0.85rem; font-weight: bold; display: inline-block;">
                                        ✏️ Edit
                                    </a>
                                    <a href="manage_plants.php?action=delete&id=<?= $pid ?>" onclick="return confirm('මෙම පැළය සම්පූර්ණයෙන්ම ඉවත් කිරීමට අවශ්‍යද?');" style="background: #d32f2f; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 0.85rem; font-weight: bold; display: inline-block;">
                                        🗑️ Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php 
                        endwhile; 
                    else:
                    ?>
                        <tr>
                            <td colspan="6" style="padding: 20px; text-align: center; color: #999;">තාම කිසිම පැළයක් ඇතුළත් කර නැත.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <!-- ── ADD NEW PLANT FORM ── -->
    <?php elseif ($action === 'add'): ?>
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
        </style>

        <div class="card" style="max-width: 700px; margin: 0 auto; border: 1px solid #2e7d32; border-radius: 10px; background: white; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
            <div class="card-body" style="padding: var(--space-xl);">
                <h2 style="color: #1b5e20; margin-bottom: var(--space-sm); font-weight: bold;">🧑‍🌾 Add New Rubber Plant Clone</h2>
                <p class="text-muted" style="margin-bottom: var(--space-md); color: #666;">Fill the details and choose 3-4 images using Ctrl key.</p>
                
                <form action="manage_plants.php?action=add" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 18px;">
                    
                    <div class="form-group">
                        <label style="font-weight: bold; display:block; margin-bottom:6px; color: #2e7d32;">Clone Name *</label>
                        <input type="text" name="plant_name" class="fixed-input" placeholder="e.g., RRIC 100" required>
                    </div>

                    <div class="form-group">
                        <label style="font-weight: bold; display:block; margin-bottom:6px; color: #2e7d32;">Scientific Name</label>
                        <input type="text" name="scientific_name" class="fixed-input" placeholder="Hevea brasiliensis">
                    </div>

                    <div class="form-group">
                        <label style="font-weight: bold; display:block; margin-bottom:6px; color: #2e7d32;">Category *</label>
                        <select name="category_id" class="fixed-input" required style="height: auto;">
                            <option value="" style="color: #666;">-- Select Category --</option>
                            <?php 
                            $categories = $conn->query("SELECT * FROM plant_categories ORDER BY category_name ASC");
                            while($cat = $categories->fetch_assoc()): 
                            ?>
                                <option value="<?= $cat['category_id'] ?>" style="color: #333;"><?= htmlspecialchars($cat['category_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <label style="font-weight: bold; display:block; margin-bottom:6px; color: #2e7d32;">Price per Unit (Rs.) *</label>
                            <input type="number" step="0.01" name="price" class="fixed-input" placeholder="45.00" required>
                        </div>
                        <div>
                            <label style="font-weight: bold; display:block; margin-bottom:6px; color: #2e7d32;">Available Stock *</label>
                            <input type="number" name="stock_quantity" class="fixed-input" placeholder="120" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-weight: bold; display:block; margin-bottom:6px; color: #2e7d32;">Description</label>
                        <textarea name="description" class="fixed-input" rows="4" placeholder="Describe leaf quality, root health..." style="resize: vertical;"></textarea>
                    </div>

                    <div style="background: #f1f8e9; padding: 20px; border-radius: 8px; border: 2px dashed #2e7d32; text-align: center;">
                        <label style="font-weight: bold; color: #1b5e20; display:block; cursor: pointer; margin-bottom: 5px;">📷 Upload Multiple Images</label>
                        <input type="file" name="images[]" multiple required style="color: #333; font-weight: bold; font-size: 0.9rem; margin: 10px auto 0 auto; display: block;">
                    </div>

                    <button type="submit" name="add_plant" style="background: #2e7d32; color: white; padding: 14px; font-size: 1.1rem; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; margin-top: 10px; transition: background 0.2s;">
                        🌱 Save Rubber Plant Clone
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>


















