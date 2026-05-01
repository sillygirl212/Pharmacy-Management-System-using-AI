<?php
require_once '../config/functions.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$page_title = 'Categories';
$db = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $slug = sanitize($_POST['slug'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $status = sanitize($_POST['status'] ?? 'active');
    $category_id = intval($_POST['category_id'] ?? 0);
    
    // Generate slug if empty
    if (empty($slug)) {
        $slug = generateSlug($name, 'categories', 'slug', $category_id);
    }
    
    if ($category_id) {
        // Update
        $stmt = $db->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, status = ? WHERE id = ?");
        $stmt->execute([$name, $slug, $description, $status, $category_id]);
        logActivity('Category Updated', "Category '{$name}' updated");
        setFlashMessage('success', 'Category updated!');
    } else {
        // Create
        $stmt = $db->prepare("INSERT INTO categories (name, slug, description, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $slug, $description, $status]);
        logActivity('Category Created', "Category '{$name}' created");
        setFlashMessage('success', 'Category created!');
    }
    redirect('categories.php');
}

// Delete category
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $category_id = intval($_GET['delete']);
    
    // Check if category has products
    $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $stmt->execute([$category_id]);
    if ($stmt->fetchColumn() > 0) {
        setFlashMessage('error', 'Cannot delete category with products.');
    } else {
        $db->prepare("DELETE FROM categories WHERE id = ?")->execute([$category_id]);
        logActivity('Category Deleted', "Category ID {$category_id} deleted");
        setFlashMessage('success', 'Category deleted!');
    }
    redirect('categories.php');
}

// Get all categories
$categories = $db->query("
    SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id 
    GROUP BY c.id 
    ORDER BY c.name
")->fetchAll();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Categories</h2>
    <button class="btn btn-primary" data-mdb-modal-init data-mdb-target="#categoryModal">
        <i class="fas fa-plus me-2"></i>Add Category
    </button>
</div>

<!-- Categories Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Category</th>
                        <th>Slug</th>
                        <th>Products</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td>
                                <h6 class="mb-0 fw-medium"><?php echo sanitize($cat['name']); ?></h6>
                                <small class="text-muted"><?php echo truncateText(sanitize($cat['description']), 50); ?></small>
                            </td>
                            <td><code><?php echo $cat['slug']; ?></code></td>
                            <td>
                                <span class="badge badge-primary"><?php echo $cat['product_count']; ?></span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $cat['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($cat['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="editCategory(<?php echo htmlspecialchars(json_encode($cat)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="categories.php?delete=<?php echo $cat['id']; ?>" class="btn btn-outline-danger" onclick="return confirmDelete()">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalTitle">Add Category</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="category_id" id="category_id" value="0">
                    
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" id="cat_name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Slug</label>
                        <input type="text" name="slug" id="cat_slug" class="form-control" placeholder="auto-generated-if-empty">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="cat_description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="cat_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCategory(category) {
    document.getElementById('modalTitle').textContent = 'Edit Category';
    document.getElementById('category_id').value = category.id;
    document.getElementById('cat_name').value = category.name;
    document.getElementById('cat_slug').value = category.slug;
    document.getElementById('cat_description').value = category.description;
    document.getElementById('cat_status').value = category.status;
    
    const modal = new mdb.Modal(document.getElementById('categoryModal'));
    modal.show();
}
</script>

<?php include 'includes/footer.php'; ?>
