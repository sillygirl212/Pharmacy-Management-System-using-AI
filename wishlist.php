<?php
require_once 'config/functions.php';
$page_title = 'My Wishlist';

if (!isLoggedIn()) {
    setFlashMessage('info', 'Please login to view your wishlist.');
    redirect('login.php');
}

$db = getDB();
$user_id = getCurrentUserId();

// Remove from wishlist
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $product_id = intval($_GET['remove']);
    $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    setFlashMessage('success', 'Removed from wishlist.');
    redirect('wishlist.php');
}

// Add to cart from wishlist
if (isset($_GET['add_to_cart']) && is_numeric($_GET['add_to_cart'])) {
    $product_id = intval($_GET['add_to_cart']);
    if (addToCart($product_id, 1)) {
        setFlashMessage('success', 'Product added to cart!');
    } else {
        setFlashMessage('error', 'Could not add to cart.');
    }
    redirect('wishlist.php');
}

// Get wishlist items
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name, c.slug as category_slug
    FROM wishlist w
    JOIN products p ON w.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE w.user_id = ? AND p.status = 'active'
    ORDER BY w.created_at DESC
");
$stmt->execute([$user_id]);
$wishlist_items = $stmt->fetchAll();

include 'includes/header.php';
?>

<!-- Page Banner -->
<section class="page-banner" style="padding: 60px 0;">
    <div class="container">
        <h1 class="fw-bold mb-2">My Wishlist</h1>
        <p class="mb-0">Products you've saved for later</p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <?php if (empty($wishlist_items)): ?>
            <div class="text-center py-5">
                <i class="fas fa-heart fa-4x text-muted mb-4"></i>
                <h3 class="fw-bold">Your Wishlist is Empty</h3>
                <p class="text-muted mb-4">Save products you like to your wishlist and buy them later.</p>
                <a href="products.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-shopping-bag me-2"></i>Browse Products
                </a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($wishlist_items as $product): ?>
                    <div class="col-md-6 col-lg-4 col-xl-3">
                        <div class="card product-card h-100">
                            <?php if ($product['prescription_required']): ?>
                                <span class="badge badge-danger badge-prescription"><i class="fas fa-prescription me-1"></i>Rx</span>
                            <?php endif; ?>
                            
                            <div class="bg-image hover-zoom ripple" data-mdb-ripple-color="light">
                                <img src="<?php echo getProductImage($product['id']); ?>" 
                                     class="product-image w-100" 
                                     alt="<?php echo sanitize($product['name']); ?>">
                                <a href="product.php?slug=<?php echo $product['slug']; ?>">
                                    <div class="mask">
                                        <div class="d-flex justify-content-start align-items-end h-100 p-3">
                                            <span class="btn btn-primary btn-sm">View Details</span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            
                            <div class="card-body d-flex flex-column">
                                <h6 class="text-muted mb-1">
                                    <a href="products.php?category=<?php echo $product['category_slug']; ?>" class="text-decoration-none">
                                        <?php echo sanitize($product['category_name'] ?? 'Uncategorized'); ?>
                                    </a>
                                </h6>
                                <h5 class="card-title mb-2">
                                    <a href="product.php?slug=<?php echo $product['slug']; ?>" class="text-dark text-decoration-none">
                                        <?php echo sanitize($product['name']); ?>
                                    </a>
                                </h5>
                                
                                <div class="mt-auto">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <?php if ($product['sale_price']): ?>
                                                <span class="price-tag"><?php echo formatPrice($product['sale_price']); ?></span>
                                                <span class="old-price ms-2"><?php echo formatPrice($product['price']); ?></span>
                                            <?php else: ?>
                                                <span class="price-tag"><?php echo formatPrice($product['price']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <a href="wishlist.php?add_to_cart=<?php echo $product['id']; ?>" 
                                           class="btn btn-primary flex-grow-1 <?php echo $product['prescription_required'] ? 'disabled' : ''; ?>">
                                            <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                        </a>
                                        <a href="wishlist.php?remove=<?php echo $product['id']; ?>" 
                                           class="btn btn-outline-danger"
                                           onclick="return confirm('Remove this item from wishlist?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
