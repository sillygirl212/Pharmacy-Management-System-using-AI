<?php
require_once '../config/functions.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$page_title = 'Products';
$db = getDB();

// Delete product
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $product_id = intval($_GET['delete']);
    
    // Check if product has orders
    $stmt = $db->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
    $stmt->execute([$product_id]);
    if ($stmt->fetchColumn() > 0) {
        setFlashMessage('error', 'Cannot delete product with existing orders. Deactivate it instead.');
    } else {
        $db->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$product_id]);
        $db->prepare("DELETE FROM cart WHERE product_id = ?")->execute([$product_id]);
        $db->prepare("DELETE FROM products WHERE id = ?")->execute([$product_id]);
        
        logActivity('Product Deleted', "Product ID {$product_id} deleted");
        setFlashMessage('success', 'Product deleted successfully!');
    }
    redirect('products.php');
}

// Toggle status
if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) {
    $product_id = intval($_GET['toggle_status']);
    $stmt = $db->prepare("UPDATE products SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?");
    $stmt->execute([$product_id]);
    
    logActivity('Product Status Toggled', "Product ID {$product_id} status toggled");
    setFlashMessage('success', 'Product status updated!');
    redirect('products.php');
}

// Get filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$low_stock = isset($_GET['low_stock']) ? true : false;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;

// Build query
$where_clauses = [];
$params = [];

if ($search) {
    $where_clauses[] = "(p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category) {
    $where_clauses[] = "p.category_id = ?";
    $params[] = $category;
}

if ($status) {
    $where_clauses[] = "p.status = ?";
    $params[] = $status;
}

if ($low_stock) {
    $where_clauses[] = "p.stock_quantity < 10";
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Count total
$count_sql = "SELECT COUNT(*) FROM products p $where_sql";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_products = $stmt->fetchColumn();

// Pagination
$pagination = paginate($total_products, $per_page, $page);

// Get products
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        $where_sql 
        ORDER BY p.created_at DESC 
        LIMIT {$pagination['offset']}, {$pagination['items_per_page']}";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for filter
$categories = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Products</h2>
    <a href="product-form.php" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>Add Product
    </a>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?php echo $search; ?>">
            </div>
            <div class="col-md-2">
                <select name="category" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="low_stock" id="low_stock" <?php echo $low_stock ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="low_stock">Low Stock</label>
                </div>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary me-2">Filter</button>
                <a href="products.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Products Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo getProductImage($product['id']); ?>" 
                                         alt="<?php echo sanitize($product['name']); ?>" 
                                         class="rounded me-3" width="50" height="50" style="object-fit: cover;">
                                    <div>
                                        <h6 class="mb-0 fw-medium"><?php echo sanitize($product['name']); ?></h6>
                                        <small class="text-muted"><?php echo $product['sku'] ?? 'No SKU'; ?></small>
                                        <?php if ($product['prescription_required']): ?>
                                            <span class="badge badge-danger small ms-2">Rx</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo sanitize($product['category_name'] ?? 'Uncategorized'); ?></td>
                            <td>
                                <?php if ($product['sale_price']): ?>
                                    <span class="text-success fw-bold"><?php echo formatPrice($product['sale_price']); ?></span>
                                    <br><small class="text-muted text-decoration-line-through"><?php echo formatPrice($product['price']); ?></small>
                                <?php else: ?>
                                    <?php echo formatPrice($product['price']); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $product['stock_quantity'] < 10 ? 'badge-danger' : 'badge-success'; ?>">
                                    <?php echo $product['stock_quantity']; ?> in stock
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $product['status'] === 'active' ? 'badge-success' : 'badge-secondary'; ?>">
                                    <?php echo ucfirst($product['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($product['created_at'])); ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="product-form.php?id=<?php echo $product['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="products.php?toggle_status=<?php echo $product['id']; ?>" class="btn btn-outline-warning" title="Toggle Status">
                                        <i class="fas fa-toggle-<?php echo $product['status'] === 'active' ? 'on' : 'off'; ?>"></i>
                                    </a>
                                    <a href="products.php?delete=<?php echo $product['id']; ?>" class="btn btn-outline-danger" title="Delete" onclick="return confirmDelete()">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No products found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
<?php if ($pagination['total_pages'] > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php if ($pagination['has_previous']): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['previous_page']])); ?>">Previous</a>
                </li>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                <li class="page-item <?php echo $i === $pagination['current_page'] ? 'active' : ''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            
            <?php if ($pagination['has_next']): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['next_page']])); ?>">Next</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
