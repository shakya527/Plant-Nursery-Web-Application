<?php
// =============================================================================
// PROJECT   : GreenThumb - Rubber Plant Nursery Web Application
// FILE      : customer/catalog.php
// PURPOSE   : The Customer Storefront (Catalog).
//             Allows customers to browse all available rubber plants, filter
//             by category, and search by name. Features a premium UI grid.
//
// OBJECTIVE : Obj 1 – Customer Catalog (accurate info and stock levels).
//             Obj 4 – Responsive UI and secure queries (Prepared Statements).
// =============================================================================

session_start();
require_once __DIR__ . '/../config/db.php';

// ── Auth Guard: Customer Only ────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: /Rudder_plant/login.html?error=' . urlencode('Please log in to browse the catalog.'));
    exit;
}

$customer_name = htmlspecialchars($_SESSION['full_name']);

// ── Filter & Search Params ───────────────────────────────────────────────────
$category_id = isset($_GET['cat']) ? (int) $_GET['cat'] : 0;
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';

// ── Build Query Dynamically using Prepared Statements ────────────────────────
$sql = "SELECT p.*, c.category_name 
        FROM plants p 
        LEFT JOIN plant_categories c ON p.category_id = c.category_id 
        WHERE p.is_available = 1";
$types = "";
$params = [];

if ($category_id > 0) {
    $sql .= " AND p.category_id = ?";
    $types .= "i";
    $params[] = $category_id;
}

if ($search_query !== '') {
    $sql .= " AND (p.plant_name LIKE ? OR p.description LIKE ?)";
    $types .= "ss";
    $search_param = "%" . $search_query . "%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY p.created_at DESC";

$plants = db_query($sql, $types, $params);

// Fetch categories for the filter sidebar
$categories = db_query("SELECT * FROM plant_categories ORDER BY category_name ASC");

// =============================================================================
// PAGE RENDER
// =============================================================================
$page_title = 'Plant Catalog';
$active_nav = 'catalog';
require_once __DIR__ . '/../components/header.php';
?>

<div class="page-hero">
    <div class="container">
        <h1>🌿 Rubber Plant Catalog</h1>
        <p>Welcome back, <?= $customer_name ?>! Discover premium rubber plant clones for your fields or cultivation centers.</p>
    </div>
</div>

<div class="container fade-in">
    
    <div style="display:grid;grid-template-columns:250px 1fr;gap:var(--space-xl);align-items:start;">
        
        <aside class="card" style="position: sticky; top: calc(var(--navbar-h) + 20px);">
            <div class="card-body">
                <h3 style="font-size:1rem;margin-bottom:var(--space-md);">🔍 Search</h3>
                <form action="catalog.php" method="GET" style="margin-bottom:var(--space-lg);">
                    <?php if ($category_id > 0): ?>
                        <input type="hidden" name="cat" value="<?= $category_id ?>">
                    <?php endif; ?>
                    <div class="form-group" style="margin-bottom:var(--space-sm);">
                        <input type="text" name="q" class="form-control" placeholder="Search plants..." value="<?= htmlspecialchars($search_query) ?>">
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm btn-full">Search</button>
                </form>

                <div class="divider"></div>

                <h3 style="font-size:1rem;margin-bottom:var(--space-md);">📁 Categories</h3>
                <ul style="display:flex;flex-direction:column;gap:8px;">
                    <li>
                        <a href="catalog.php<?= $search_query ? '?q='.urlencode($search_query) : '' ?>" 
                           style="color: <?= $category_id === 0 ? 'var(--clr-primary)' : 'var(--clr-text-primary)' ?>; font-weight: <?= $category_id === 0 ? '700' : '400' ?>;">
                            All Plants
                        </a>
                    </li>
                    <?php foreach ($categories as $cat): ?>
                    <li>
                        <a href="catalog.php?cat=<?= $cat['category_id'] ?><?= $search_query ? '&q='.urlencode($search_query) : '' ?>"
                           style="color: <?= $category_id === $cat['category_id'] ? 'var(--clr-primary)' : 'var(--clr-text-primary)' ?>; font-weight: <?= $category_id === $cat['category_id'] ? '700' : '400' ?>;">
                            <?= htmlspecialchars($cat['category_name']) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                
                <?php if ($category_id > 0 || $search_query !== ''): ?>
                    <div style="margin-top:var(--space-md);">
                        <a href="catalog.php" style="font-size:0.8rem;color:var(--clr-danger);">Clear all filters ✖</a>
                    </div>
                <?php endif; ?>
            </div>
        </aside>

        <section>
            
            <?php if (empty($plants)): ?>
                <div class="card" style="text-align:center;padding:var(--space-2xl);">
                    <div style="font-size:4rem;margin-bottom:var(--space-md);">🌵</div>
                    <h2>No plants found</h2>
                    <p>We couldn't find any rubber plants matching your criteria.</p>
                    <a href="catalog.php" class="btn btn-secondary" style="margin-top:var(--space-md);">View All Plants</a>
                </div>
            <?php else: ?>
                <div class="grid-3">
                    <?php foreach ($plants as $plant): ?>
                    <article class="card">
                        <?php
                        // Fetch gallery images securely using the system's db_query tool
                        $gallery_images = db_query("SELECT image_path FROM plant_images WHERE plant_id = ? ORDER BY id ASC", "i", [$plant['plant_id']]);
                        $has_primary = !empty($plant['image_filename']);
                        
                        if ($has_primary || !empty($gallery_images)):
                            $all_images = [];
                            if ($has_primary) {
                                // Add primary image
                                $all_images[] = 'plants/' . $plant['image_filename'];
                            }
                            foreach ($gallery_images as $g) {
                                // Add additional gallery images
                                $all_images[] = $g['image_path'];
                            }
                            $carousel_id = 'carousel-' . $plant['plant_id'];
                        ?>
                            <div class="carousel-container" id="<?= $carousel_id ?>">
                                <div class="carousel-track">
                                    <?php foreach ($all_images as $idx => $img): ?>
                                        <div class="carousel-slide">
                                            <img src="/Rudder_plant/uploads/<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($plant['plant_name']) ?> - View <?= $idx+1 ?>" class="card-img">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($all_images) > 1): ?>
                                    <button type="button" class="carousel-btn prev" onclick="moveCarousel('<?= $carousel_id ?>', -1)">❮</button>
                                    <button type="button" class="carousel-btn next" onclick="moveCarousel('<?= $carousel_id ?>', 1)">❯</button>
                                    <div class="carousel-indicators">
                                        <?php foreach ($all_images as $idx => $img): ?>
                                            <span class="indicator <?= $idx === 0 ? 'active' : '' ?>"></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="card-img-placeholder">🌿</div>
                        <?php endif; ?>

                        <div class="card-body" style="display:flex;flex-direction:column;height:calc(100% - 220px);">
                            
                            <div style="margin-bottom:8px;">
                                <span class="badge badge-muted"><?= htmlspecialchars($plant['category_name'] ?? 'Uncategorized') ?></span>
                            </div>

                            <h2 style="font-size:1.2rem;margin-bottom:4px;"><?= htmlspecialchars($plant['plant_name']) ?></h2>
                            
                            <div style="font-size:0.85rem; color:var(--clr-text-muted); margin-bottom:8px;">
                                📍 Region: <?= htmlspecialchars($plant['location'] ?? 'Monaragala') ?>
                            </div>

                            <div class="flex items-center justify-between" style="margin-bottom:16px;">
                                <div class="price-tag">Rs. <?= number_format((float)($plant['price_per_unit'] ?? $plant['price'] ?? 0), 2) ?></div>
                                <div class="stock-indicator <?= $plant['stock_quantity'] > 10 ? 'stock-in' : ($plant['stock_quantity'] > 0 ? 'stock-low' : 'stock-out') ?>">
                                    <?= $plant['stock_quantity'] > 0 ? (int)$plant['stock_quantity'] . ' available' : 'Out of Stock' ?>
                                </div>
                            </div>
                            
                            <p style="font-size:0.85rem;margin-bottom:var(--space-md);flex-grow:1;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;">
                                <?= htmlspecialchars($plant['description'] ?? 'No description available for this rubber plant clone.') ?>
                            </p>
                            
                            <form action="/Rudder_plant/customer/cart.php" method="POST" style="margin-top:auto;">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="plant_id" value="<?= $plant['plant_id'] ?>">
                                <?php if ($plant['stock_quantity'] > 0): ?>
                                    <button type="submit" class="btn btn-primary btn-full">
                                        🛒 Add to Cart
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-secondary btn-full" disabled style="opacity:0.6;cursor:not-allowed;">
                                        Out of Stock
                                    </button>
                                <?php endif; ?>
                            </form>

                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </section>

    </div>
</div>

<script>
function moveCarousel(carouselId, direction) {
    const container = document.getElementById(carouselId);
    const track = container.querySelector('.carousel-track');
    const slides = track.querySelectorAll('.carousel-slide');
    const indicators = container.querySelectorAll('.indicator');
    
    const slideWidth = slides[0].offsetWidth;
    let currentIndex = Math.round(track.scrollLeft / slideWidth);
    
    let newIndex = currentIndex + direction;
    if (newIndex < 0) newIndex = slides.length - 1;
    if (newIndex >= slides.length) newIndex = 0;
    
    track.scrollTo({
        left: slides[newIndex].offsetLeft,
        behavior: 'smooth'
    });
    
    indicators.forEach((ind, i) => {
        if (i === newIndex) ind.classList.add('active');
        else ind.classList.remove('active');
    });
}

document.querySelectorAll('.carousel-track').forEach(track => {
    track.addEventListener('scroll', function() {
        const container = this.closest('.carousel-container');
        const slides = this.querySelectorAll('.carousel-slide');
        const indicators = container.querySelectorAll('.indicator');
        if(!indicators.length) return;
        
        const slideWidth = slides[0].offsetWidth;
        let currentIndex = Math.round(this.scrollLeft / slideWidth);
        
        indicators.forEach((ind, i) => {
            if (i === currentIndex) ind.classList.add('active');
            else ind.classList.remove('active');
        });
    });
});
</script>

<?php require_once __DIR__ . '/../components/footer.php'; ?>