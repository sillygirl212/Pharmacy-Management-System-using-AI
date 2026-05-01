<?php
require_once '../config/functions.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$page_title = 'Add/Edit Product';
$db = getDB();

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$product = [];
$error = '';

// Get product data if editing
if ($product_id) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        setFlashMessage('error', 'Product not found.');
        redirect('products.php');
    }
    
    // Get product images
    $stmt = $db->prepare("SELECT * FROM product_images WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $product['images'] = $stmt->fetchAll();
}

// Get categories
$categories = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $slug = sanitize($_POST['slug'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $description = sanitize($_POST['description'] ?? '');
    $short_description = sanitize($_POST['short_description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $sale_price = !empty($_POST['sale_price']) ? floatval($_POST['sale_price']) : null;
    $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
    $sku = sanitize($_POST['sku'] ?? '');
    $manufacturer = sanitize($_POST['manufacturer'] ?? '');
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $dosage = sanitize($_POST['dosage'] ?? '');
    $prescription_required = isset($_POST['prescription_required']) ? 1 : 0;
    $featured = isset($_POST['featured']) ? 1 : 0;
    $status = sanitize($_POST['status'] ?? 'active');
    
    // Generate slug if empty
    if (empty($slug)) {
        $slug = generateSlug($name, 'products', 'slug', $product_id);
    }
    
    // Validation
    if (empty($name) || $price <= 0) {
        $error = 'Product name and price are required.';
    } else {
        try {
            if ($product_id) {
                // Update product
                $stmt = $db->prepare("
                    UPDATE products SET 
                        name = ?, slug = ?, category_id = ?, description = ?, short_description = ?,
                        price = ?, sale_price = ?, stock_quantity = ?, sku = ?, manufacturer = ?,
                        expiry_date = ?, dosage = ?, prescription_required = ?, featured = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name, $slug, $category_id, $description, $short_description,
                    $price, $sale_price, $stock_quantity, $sku, $manufacturer,
                    $expiry_date, $dosage, $prescription_required, $featured, $status, $product_id
                ]);
                
                logActivity('Product Updated', "Product '{$name}' updated");
                $success_msg = 'Product updated successfully!';
            } else {
                // Create product
                $stmt = $db->prepare("
                    INSERT INTO products (
                        name, slug, category_id, description, short_description,
                        price, sale_price, stock_quantity, sku, manufacturer,
                        expiry_date, dosage, prescription_required, featured, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $name, $slug, $category_id, $description, $short_description,
                    $price, $sale_price, $stock_quantity, $sku, $manufacturer,
                    $expiry_date, $dosage, $prescription_required, $featured, $status
                ]);
                
                $product_id = $db->lastInsertId();
                logActivity('Product Created', "Product '{$name}' created");
                $success_msg = 'Product created successfully!';
            }
            
            // Handle image uploads
            if (!empty($_FILES['images']['name'][0])) {
                $upload_dir = __DIR__ . '/../uploads/products/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                        $filename = time() . '_' . generateRandomString(8) . '_' . $_FILES['images']['name'][$key];
                        $filepath = $upload_dir . $filename;
                        
                        if (move_uploaded_file($tmp_name, $filepath)) {
                            $db->prepare("INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, ?)")
                               ->execute([$product_id, 'uploads/products/' . $filename, $key === 0 ? 1 : 0]);
                        }
                    }
                }
            }
            
            setFlashMessage('success', $success_msg);
            redirect('products.php');
            
        } catch (Exception $e) {
            $error = 'Error saving product: ' . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><?php echo $product_id ? 'Edit Product' : 'Add Product'; ?></h2>
    <a href="products.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Products
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger mb-4">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="row g-4">
                <!-- Basic Info -->
                <div class="col-md-8">
                    <h5 class="fw-bold mb-3">Basic Information</h5>
                    
                    <div class="mb-3">
                        <label class="form-label">Product Name *</label>
                        <input type="text" name="name" class="form-control" value="<?php echo $product['name'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Slug</label>
                        <input type="text" name="slug" class="form-control" value="<?php echo $product['slug'] ?? ''; ?>" placeholder="auto-generated-if-empty">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo ($product['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Short Description</label>
                        <textarea name="short_description" class="form-control" rows="2"><?php echo $product['short_description'] ?? ''; ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Full Description</label>
                        <textarea name="description" class="form-control" rows="5"><?php echo $product['description'] ?? ''; ?></textarea>
                    </div>
                </div>
                
                <!-- Pricing & Stock -->
                <div class="col-md-4">
                    <h5 class="fw-bold mb-3">Pricing & Stock</h5>
                    
                    <div class="mb-3">
                        <label class="form-label">Regular Price *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="price" class="form-control" step="0.01" min="0" value="<?php echo $product['price'] ?? ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Sale Price</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="sale_price" class="form-control" step="0.01" min="0" value="<?php echo $product['sale_price'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Stock Quantity</label>
                        <input type="number" name="stock_quantity" class="form-control" min="0" value="<?php echo $product['stock_quantity'] ?? '0'; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">SKU</label>
                        <input type="text" name="sku" class="form-control" value="<?php echo $product['sku'] ?? ''; ?>">
                    </div>
                    
                    <h5 class="fw-bold mb-3 mt-4">Additional Info</h5>
                    
                    <div class="mb-3">
                        <label class="form-label">Manufacturer</label>
                        <input type="text" name="manufacturer" class="form-control" value="<?php echo $product['manufacturer'] ?? ''; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control" value="<?php echo $product['expiry_date'] ?? ''; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Dosage</label>
                        <input type="text" name="dosage" class="form-control" value="<?php echo $product['dosage'] ?? ''; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?php echo ($product['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($product['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="prescription_required" id="prescription" <?php echo ($product['prescription_required'] ?? 0) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="prescription">Prescription Required</label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="featured" id="featured" <?php echo ($product['featured'] ?? 0) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="featured">Featured Product</label>
                    </div>
                </div>
            </div>
            
            <!-- Images -->
            <div class="mt-4">
                <h5 class="fw-bold mb-3">Product Images</h5>
                
                <?php if (!empty($product['images'])): ?>
                    <div class="row g-2 mb-3">
                        <?php foreach ($product['images'] as $img): ?>
                            <div class="col-auto">
                                <div class="position-relative">
                                    <img src="../<?php echo $img['image_path']; ?>" class="rounded" width="100" height="100" style="object-fit: cover;">
                                    <?php if ($img['is_primary']): ?>
                                        <span class="badge badge-primary position-absolute top-0 start-0 m-1">Primary</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <label class="form-label">Upload Images</label>
                    <input type="file" name="images[]" class="form-control" multiple accept="image/*">
                    <small class="text-muted">First image will be set as primary. Supported: JPG, PNG, GIF</small>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-2"></i><?php echo $product_id ? 'Update Product' : 'Create Product'; ?>
                </button>
                <a href="products.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
