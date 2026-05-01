<?php
require_once 'config/functions.php';
$page_title = 'Products';

$db = getDB();

// Get filter parameters
$category_slug = isset($_GET['category']) ? sanitize($_GET['category']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 0;
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';
$featured_only = isset($_GET['featured']) ? true : false;

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 12;

// Build query
$where_clauses = ["p.status = 'active'"];
$params = [];

if ($category_slug) {
    $where_clauses[] = "c.slug = ?";
    $params[] = $category_slug;
}

if ($search) {
    $where_clauses[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($min_price > 0) {
    $where_clauses[] = "COALESCE(p.sale_price, p.price) >= ?";
    $params[] = $min_price;
}

if ($max_price > 0) {
    $where_clauses[] = "COALESCE(p.sale_price, p.price) <= ?";
    $params[] = $max_price;
}

if ($featured_only) {
    $where_clauses[] = "p.featured = 1";
}

$where_sql = implode(' AND ', $where_clauses);

// Count total products
$count_sql = "SELECT COUNT(*) FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE $where_sql";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_products = $stmt->fetchColumn();

// Pagination
$pagination = paginate($total_products, $per_page, $page);

// Sort order
$order_sql = "p.created_at DESC";
switch ($sort) {
    case 'price_low':
        $order_sql = "COALESCE(p.sale_price, p.price) ASC";
        break;
    case 'price_high':
        $order_sql = "COALESCE(p.sale_price, p.price) DESC";
        break;
    case 'popularity':
        $order_sql = "p.featured DESC, p.created_at DESC";
        break;
}

// Get products
$sql = "SELECT p.*, c.name as category_name, c.slug as category_slug 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE $where_sql 
        ORDER BY $order_sql 
        LIMIT {$pagination['offset']}, {$pagination['items_per_page']}";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get all categories for filter
$categories = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();

// Get current category name
$current_category = null;
if ($category_slug) {
    foreach ($categories as $cat) {
        if ($cat['slug'] === $category_slug) {
            $current_category = $cat;
            break;
        }
    }
}

include 'includes/header.php';
?>

<!-- Page Banner -->
<section class="page-banner">
    <div class="container">
        <h1 class="display-4 fw-bold mb-3">
            <?php if ($current_category): ?>
                <?php echo sanitize($current_category['name']); ?>
            <?php elseif ($search): ?>
                Search Results
            <?php else: ?>
                All Products
            <?php endif; ?>
        </h1>
        <p class="lead mb-0">
            <?php if ($search): ?>
                Found <?php echo $total_products; ?> result(s) for "<?php echo $search; ?>"
            <?php else: ?>
                Browse our collection of <?php echo $total_products; ?> quality healthcare products
            <?php endif; ?>
        </p>
    </div>
</section>

<!-- Products Section -->
<section class="py-5">
    <div class="container">
        <div class="row">
            <!-- Sidebar Filters -->
            <div class="col-lg-3 mb-4">
                <div class="filter-sidebar">
                    <h5 class="fw-bold mb-4"><i class="fas fa-filter me-2"></i>Filters</h5>
                    
                    <form method="GET" action="products.php">
                        <?php if ($category_slug): ?>
                            <input type="hidden" name="category" value="<?php echo $category_slug; ?>">
                        <?php endif; ?>
                        
                        <!-- Search -->
                        <div class="mb-4">
                            <label class="form-label fw-medium">Search</label>
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" class="form-control" name="search" value="<?php echo $search; ?>" placeholder="Search products...">
                            </div>
                        </div>
                        
                        <!-- Categories -->
                        <div class="mb-4">
                            <label class="form-label fw-medium">Categories</label>
                            <div class="list-group list-group-light">
                                <?php foreach ($categories as $cat): ?>
                                    <a href="products.php?category=<?php echo $cat['slug']; ?>" 
                                       class="list-group-item list-group-item-action <?php echo $cat['slug'] === $category_slug ? 'active' : ''; ?>">
                                        <?php echo sanitize($cat['name']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Price Range -->
                        <div class="mb-4">
                            <label class="form-label fw-medium">Price Range</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" class="form-control" name="min_price" value="<?php echo $min_price; ?>" placeholder="Min">
                                </div>
                                <div class="col-6">
                                    <input type="number" class="form-control" name="max_price" value="<?php echo $max_price; ?>" placeholder="Max">
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                        <a href="products.php" class="btn btn-outline-secondary w-100 mt-2">Clear Filters</a>
                    </form>
                </div>
            </div>
            
            <!-- Products Grid -->
            <div class="col-lg-9">
                <!-- Sort and Results Info -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <p class="text-muted mb-0">
                        Showing <?php echo $pagination['offset'] + 1; ?> - 
                        <?php echo min($pagination['offset'] + $pagination['items_per_page'], $total_products); ?> 
                        of <?php echo $total_products; ?> products
                    </p>
                    
                    <form method="GET" class="d-flex align-items-center gap-2">
                        <?php foreach ($_GET as $key => $value): ?>
                            <?php if ($key !== 'sort' && $key !== 'page'): ?>
                                <input type="hidden" name="<?php echo $key; ?>" value="<?php echo $value; ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <select class="form-select form-select-sm" name="sort" onchange="this.form.submit()" style="width: auto;">
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="popularity" <?php echo $sort === 'popularity' ? 'selected' : ''; ?>>Most Popular</option>
                        </select>
                    </form>
                </div>
                
                <?php if (empty($products)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h4>No products found</h4>
                        <p class="text-muted">Try adjusting your filters or search query.</p>
                        <a href="products.php" class="btn btn-primary">View All Products</a>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($products as $product): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card product-card h-100">
                                    <?php if ($product['featured']): ?>
                                        <span class="badge badge-primary badge-featured">Featured</span>
                                    <?php endif; ?>
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
                                        <p class="text-muted small mb-3 flex-grow-1">
                                            <?php echo truncateText(sanitize($product['short_description'] ?? $product['description']), 60); ?>
                                        </p>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <?php if ($product['sale_price']): ?>
                                                    <span class="price-tag"><?php echo formatPrice($product['sale_price']); ?></span>
                                                    <span class="old-price ms-2"><?php echo formatPrice($product['price']); ?></span>
                                                <?php else: ?>
                                                    <span class="price-tag"><?php echo formatPrice($product['price']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <button class="btn btn-primary btn-sm add-to-cart" 
                                                    data-product-id="<?php echo $product['id']; ?>"
                                                    <?php echo $product['prescription_required'] ? 'disabled' : ''; ?>>
                                                <i class="fas fa-cart-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($pagination['total_pages'] > 1): ?>
                        <nav class="mt-5">
                            <ul class="pagination justify-content-center">
                                <?php if ($pagination['has_previous']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['previous_page']])); ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                                    <li class="page-item <?php echo $i === $pagination['current_page'] ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($pagination['has_next']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['next_page']])); ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
