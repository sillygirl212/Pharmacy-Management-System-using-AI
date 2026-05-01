<?php
require_once 'config/functions.php';

$db = getDB();

// Get product slug
$slug = isset($_GET['slug']) ? sanitize($_GET['slug']) : '';

if (!$slug) {
    setFlashMessage('error', 'Product not found.');
    redirect('products.php');
}

// Get product details
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name, c.slug as category_slug 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.slug = ? AND p.status = 'active'
");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    setFlashMessage('error', 'Product not found.');
    redirect('products.php');
}

$page_title = $product['name'];

// Get product images
$stmt = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC");
$stmt->execute([$product['id']]);
$images = $stmt->fetchAll();

if (empty($images)) {
    $images = [['image_path' => 'assets/images/product-placeholder.jpg', 'is_primary' => 1]];
}

// Get related products
$related_products = getRelatedProducts($product['id'], $product['category_id'], 4);

// Get reviews
$stmt = $db->prepare("
    SELECT r.*, u.name as user_name 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.product_id = ? AND r.status = 'approved' 
    ORDER BY r.created_at DESC 
    LIMIT 5
");
$stmt->execute([$product['id']]);
$reviews = $stmt->fetchAll();

// Calculate average rating
$stmt = $db->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE product_id = ? AND status = 'approved'");
$stmt->execute([$product['id']]);
$rating_data = $stmt->fetch();
$avg_rating = round($rating_data['avg_rating'] ?? 0, 1);
$total_reviews = $rating_data['total_reviews'] ?? 0;

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $quantity = intval($_POST['quantity'] ?? 1);
    
    if ($product['prescription_required']) {
        setFlashMessage('error', 'This product requires a prescription. Please contact us.');
    } else {
        if (addToCart($product['id'], $quantity)) {
            setFlashMessage('success', 'Product added to cart successfully!');
        } else {
            setFlashMessage('error', 'Failed to add product to cart.');
        }
    }
    redirect('product.php?slug=' . $slug);
}

// Handle wishlist
if (isset($_GET['toggle_wishlist']) && isLoggedIn()) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->execute([getCurrentUserId(), $product['id']]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        $stmt = $db->prepare("DELETE FROM wishlist WHERE id = ?");
        $stmt->execute([$existing['id']]);
        setFlashMessage('info', 'Removed from wishlist.');
    } else {
        $stmt = $db->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
        $stmt->execute([getCurrentUserId(), $product['id']]);
        setFlashMessage('success', 'Added to wishlist!');
    }
    redirect('product.php?slug=' . $slug);
}

include 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="bg-light py-3">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="products.php">Products</a></li>
                <?php if ($product['category_slug']): ?>
                    <li class="breadcrumb-item"><a href="products.php?category=<?php echo $product['category_slug']; ?>">
                        <?php echo sanitize($product['category_name']); ?></a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active" aria-current="page"><?php echo sanitize($product['name']); ?></li>
            </ol>
        </nav>
    </div>
</div>

<!-- Product Details -->
<section class="py-5">
    <div class="container">
        <div class="row">
            <!-- Product Images -->
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="bg-image hover-zoom ripple rounded" data-mdb-ripple-color="light">
                        <img src="<?php echo $images[0]['image_path']; ?>" 
                             id="mainImage"
                             class="w-100" 
                             alt="<?php echo sanitize($product['name']); ?>"
                             style="max-height: 500px; object-fit: cover;">
                    </div>
                </div>
                
                <?php if (count($images) > 1): ?>
                    <div class="row g-2 mt-3">
                        <?php foreach ($images as $image): ?>
                            <div class="col-3">
                                <div class="card border-0 cursor-pointer" onclick="document.getElementById('mainImage').src='<?php echo $image['image_path']; ?>'">
                                    <img src="<?php echo $image['image_path']; ?>" 
                                         class="w-100 rounded" 
                                         alt="Product thumbnail"
                                         style="height: 80px; object-fit: cover;">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Product Info -->
            <div class="col-lg-6">
                <div class="ps-lg-4">
                    <!-- Category & Badges -->
                    <div class="d-flex gap-2 mb-2">
                        <span class="badge badge-secondary">
                            <?php echo sanitize($product['category_name'] ?? 'Uncategorized'); ?>
                        </span>
                        <?php if ($product['featured']): ?>
                            <span class="badge badge-primary">Featured</span>
                        <?php endif; ?>
                        <?php if ($product['prescription_required']): ?>
                            <span class="badge badge-danger"><i class="fas fa-prescription me-1"></i>Prescription Required</span>
                        <?php endif; ?>
                    </div>
                    
                    <h1 class="h2 fw-bold mb-3"><?php echo sanitize($product['name']); ?></h1>
                    
                    <!-- Rating -->
                    <div class="d-flex align-items-center mb-3">
                        <div class="text-warning me-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?php echo $i > $avg_rating ? '-half-alt' : ''; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="text-muted"><?php echo $avg_rating; ?> (<?php echo $total_reviews; ?> reviews)</span>
                    </div>
                    
                    <!-- Price -->
                    <div class="mb-4">
                        <?php if ($product['sale_price']): ?>
                            <span class="display-5 fw-bold text-primary"><?php echo formatPrice($product['sale_price']); ?></span>
                            <span class="h4 text-muted text-decoration-line-through ms-2"><?php echo formatPrice($product['price']); ?></span>
                            <span class="badge badge-success ms-2">
                                <?php echo round((($product['price'] - $product['sale_price']) / $product['price']) * 100); ?>% OFF
                            </span>
                        <?php else: ?>
                            <span class="display-5 fw-bold text-primary"><?php echo formatPrice($product['price']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Short Description -->
                    <p class="text-muted mb-4"><?php echo nl2br(sanitize($product['short_description'] ?? $product['description'])); ?></p>
                    
                    <!-- Stock Info -->
                    <div class="mb-4">
                        <?php if ($product['stock_quantity'] > 0): ?>
                            <span class="badge badge-success"><i class="fas fa-check-circle me-1"></i>In Stock (<?php echo $product['stock_quantity']; ?> available)</span>
                        <?php else: ?>
                            <span class="badge badge-danger"><i class="fas fa-times-circle me-1"></i>Out of Stock</span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Product Meta -->
                    <div class="mb-4">
                        <?php if ($product['sku']): ?>
                            <p class="mb-1"><strong>SKU:</strong> <?php echo $product['sku']; ?></p>
                        <?php endif; ?>
                        <?php if ($product['manufacturer']): ?>
                            <p class="mb-1"><strong>Manufacturer:</strong> <?php echo sanitize($product['manufacturer']); ?></p>
                        <?php endif; ?>
                        <?php if ($product['dosage']): ?>
                            <p class="mb-1"><strong>Dosage:</strong> <?php echo sanitize($product['dosage']); ?></p>
                        <?php endif; ?>
                        <?php if ($product['expiry_date']): ?>
                            <p class="mb-1"><strong>Expiry Date:</strong> <?php echo date('M Y', strtotime($product['expiry_date'])); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Add to Cart Form -->
                    <?php if ($product['stock_quantity'] > 0 && !$product['prescription_required']): ?>
                        <form method="POST" class="mb-4">
                            <div class="row g-3 align-items-center">
                                <div class="col-auto">
                                    <label class="form-label fw-bold">Quantity:</label>
                                </div>
                                <div class="col-auto">
                                    <div class="input-group" style="width: 130px;">
                                        <button class="btn btn-outline-secondary" type="button" onclick="this.parentNode.querySelector('input').stepDown()">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" name="quantity" class="form-control text-center" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>">
                                        <button class="btn btn-outline-secondary" type="button" onclick="this.parentNode.querySelector('input').stepUp()">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col">
                                    <button type="submit" name="add_to_cart" class="btn btn-primary btn-lg">
                                        <i class="fas fa-shopping-cart me-2"></i>Add to Cart
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php elseif ($product['prescription_required']): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-prescription me-2"></i>This product requires a valid prescription. 
                            Please <a href="contact.php">contact us</a> to upload your prescription.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle me-2"></i>This product is currently out of stock.
                        </div>
                    <?php endif; ?>
                    
                    <!-- Wishlist -->
                    <?php if (isLoggedIn()): ?>
                        <div class="mb-4">
                            <a href="product.php?slug=<?php echo $slug; ?>&toggle_wishlist=1" class="btn btn-outline-danger">
                                <i class="fas fa-heart<?php echo isInWishlist($product['id']) ? '' : '-o'; ?> me-2"></i>
                                <?php echo isInWishlist($product['id']) ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Share -->
                    <div class="d-flex gap-2">
                        <span class="fw-bold">Share:</span>
                        <a href="#" class="text-primary"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-info"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-danger"><i class="fab fa-pinterest fa-lg"></i></a>
                        <a href="#" class="text-success"><i class="fab fa-whatsapp fa-lg"></i></a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Product Description & Reviews Tabs -->
        <div class="row mt-5">
            <div class="col-12">
                <ul class="nav nav-pills mb-4" id="productTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="description-tab" data-mdb-tab-init data-mdb-target="#description" role="tab">
                            Description
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="reviews-tab" data-mdb-tab-init data-mdb-target="#reviews" role="tab">
                            Reviews (<?php echo $total_reviews; ?>)
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="productTabContent">
                    <div class="tab-pane fade show active" id="description" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4">
                                <h4 class="fw-bold mb-3">Product Description</h4>
                                <div class="text-muted">
                                    <?php echo nl2br(sanitize($product['description'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="reviews" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4">
                                <h4 class="fw-bold mb-4">Customer Reviews</h4>
                                
                                <?php if (empty($reviews)): ?>
                                    <p class="text-muted">No reviews yet. Be the first to review this product!</p>
                                <?php else: ?>
                                    <?php foreach ($reviews as $review): ?>
                                        <div class="border-bottom pb-3 mb-3">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="d-flex align-items-center">
                                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($review['user_name']); ?>&background=1266f1&color=fff" 
                                                         class="rounded-circle me-3" width="50" height="50">
                                                    <div>
                                                        <h6 class="mb-0 fw-bold"><?php echo sanitize($review['user_name']); ?></h6>
                                                        <div class="text-warning small">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="fas fa-star<?php echo $i > $review['rating'] ? '-o' : ''; ?>"></i>
                                                            <?php endfor; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <small class="text-muted"><?php echo timeAgo($review['created_at']); ?></small>
                                            </div>
                                            <p class="mt-2 mb-0 text-muted"><?php echo nl2br(sanitize($review['review'])); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Related Products -->
        <?php if (!empty($related_products)): ?>
            <div class="row mt-5">
                <div class="col-12">
                    <h3 class="fw-bold mb-4">Related Products</h3>
                    <div class="row g-4">
                        <?php foreach ($related_products as $related): ?>
                            <div class="col-md-6 col-lg-3">
                                <div class="card product-card h-100">
                                    <div class="bg-image hover-zoom ripple" data-mdb-ripple-color="light">
                                        <img src="<?php echo getProductImage($related['id']); ?>" 
                                             class="product-image w-100" 
                                             alt="<?php echo sanitize($related['name']); ?>">
                                        <a href="product.php?slug=<?php echo $related['slug']; ?>">
                                            <div class="mask">
                                                <div class="d-flex justify-content-start align-items-end h-100 p-3">
                                                    <span class="btn btn-primary btn-sm">View Details</span>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <a href="product.php?slug=<?php echo $related['slug']; ?>" class="text-dark text-decoration-none">
                                                <?php echo truncateText(sanitize($related['name']), 40); ?>
                                            </a>
                                        </h6>
                                        <p class="fw-bold text-primary mb-0">
                                            <?php echo formatPrice($related['sale_price'] ?? $related['price']); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
