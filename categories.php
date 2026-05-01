<?php
require_once 'config/functions.php';
$page_title = 'Categories';

$db = getDB();
$categories = $db->query("
    SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
    WHERE c.status = 'active'
    GROUP BY c.id 
    ORDER BY c.name
")->fetchAll();

include 'includes/header.php';
?>

<!-- Page Banner -->
<section class="page-banner" style="padding: 60px 0;">
    <div class="container">
        <h1 class="fw-bold mb-2">Categories</h1>
        <p class="mb-0">Browse products by category</p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <?php if (empty($categories)): ?>
            <div class="text-center py-5">
                <i class="fas fa-folder-open fa-4x text-muted mb-4"></i>
                <h3 class="fw-bold">No Categories Found</h3>
                <p class="text-muted">Check back later for updates.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($categories as $category): ?>
                    <div class="col-md-6 col-lg-4">
                        <a href="products.php?category=<?php echo $category['slug']; ?>" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm overflow-hidden">
                                <div class="position-relative" style="height: 200px;">
                                    <img src="<?php echo $category['image'] ? $category['image'] : 'https://via.placeholder.com/400x200?text=' . urlencode($category['name']); ?>" 
                                         class="w-100 h-100" style="object-fit: cover;"
                                         alt="<?php echo sanitize($category['name']); ?>">
                                    <div class="position-absolute bottom-0 start-0 end-0 p-3" 
                                         style="background: linear-gradient(transparent, rgba(0,0,0,0.8));">
                                        <h4 class="text-white mb-1"><?php echo sanitize($category['name']); ?></h4>
                                        <p class="text-white-50 mb-0 small"><?php echo $category['product_count']; ?> products</p>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-0">
                                        <?php echo truncateText(sanitize($category['description'] ?? ''), 100); ?>
                                    </p>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
