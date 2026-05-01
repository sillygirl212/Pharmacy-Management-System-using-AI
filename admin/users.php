<?php
require_once '../config/functions.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$page_title = 'Users';
$db = getDB();

// Toggle user status
if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) {
    $user_id = intval($_GET['toggle_status']);
    
    // Don't allow blocking yourself
    if ($user_id === getCurrentUserId()) {
        setFlashMessage('error', 'You cannot block yourself!');
    } else {
        $stmt = $db->prepare("UPDATE users SET status = IF(status = 'active', 'blocked', 'active') WHERE id = ? AND role != 'admin'");
        $stmt->execute([$user_id]);
        
        logActivity('User Status Toggled', "User ID {$user_id} status toggled");
        setFlashMessage('success', 'User status updated!');
    }
    redirect('users.php');
}

// Delete user
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = intval($_GET['delete']);
    
    if ($user_id === getCurrentUserId()) {
        setFlashMessage('error', 'You cannot delete yourself!');
    } else {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        $stmt->execute([$user_id]);
        
        logActivity('User Deleted', "User ID {$user_id} deleted");
        setFlashMessage('success', 'User deleted!');
    }
    redirect('users.php');
}

// Get filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;

// Build query
$where_clauses = ["role = 'user'"];
$params = [];

if ($search) {
    $where_clauses[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status) {
    $where_clauses[] = "status = ?";
    $params[] = $status;
}

$where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

// Count total
$count_sql = "SELECT COUNT(*) FROM users $where_sql";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_users = $stmt->fetchColumn();

// Pagination
$pagination = paginate($total_users, $per_page, $page);

// Get users
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count,
        (SELECT COALESCE(SUM(total), 0) FROM orders WHERE user_id = u.id AND payment_status = 'paid') as total_spent
        FROM users u 
        $where_sql 
        ORDER BY u.created_at DESC 
        LIMIT {$pagination['offset']}, {$pagination['items_per_page']}";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Users</h2>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search users..." value="<?php echo $search; ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="blocked" <?php echo $status === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                </select>
            </div>
            <div class="col-md-5">
                <button type="submit" class="btn btn-primary me-2">Filter</button>
                <a href="users.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>User</th>
                        <th>Contact</th>
                        <th>Orders</th>
                        <th>Total Spent</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=1266f1&color=fff&size=40" 
                                         class="rounded-circle me-3" width="40" height="40">
                                    <div>
                                        <h6 class="mb-0 fw-medium"><?php echo sanitize($user['name']); ?></h6>
                                        <small class="text-muted"><?php echo sanitize($user['email']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php echo $user['phone'] ? sanitize($user['phone']) : '<span class="text-muted">N/A</span>'; ?>
                            </td>
                            <td>
                                <span class="badge badge-primary"><?php echo $user['order_count']; ?></span>
                            </td>
                            <td><?php echo formatPrice($user['total_spent']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" data-mdb-modal-init data-mdb-target="#userModal<?php echo $user['id']; ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="users.php?toggle_status=<?php echo $user['id']; ?>" class="btn btn-outline-warning" title="Toggle Status">
                                        <i class="fas fa-ban"></i>
                                    </a>
                                    <a href="users.php?delete=<?php echo $user['id']; ?>" class="btn btn-outline-danger" onclick="return confirmDelete()">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- User Modal -->
                        <div class="modal fade" id="userModal<?php echo $user['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title fw-bold">User Details</h5>
                                        <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="text-center mb-4">
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=1266f1&color=fff&size=100" 
                                                 class="rounded-circle mb-3" width="100" height="100">
                                            <h5 class="fw-bold mb-0"><?php echo sanitize($user['name']); ?></h5>
                                            <p class="text-muted"><?php echo sanitize($user['email']); ?></p>
                                        </div>
                                        
                                        <div class="row g-3">
                                            <div class="col-6">
                                                <div class="text-center p-3 bg-light rounded">
                                                    <h4 class="text-primary mb-1"><?php echo $user['order_count']; ?></h4>
                                                    <small class="text-muted">Orders</small>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="text-center p-3 bg-light rounded">
                                                    <h4 class="text-success mb-1"><?php echo formatPrice($user['total_spent']); ?></h4>
                                                    <small class="text-muted">Total Spent</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <hr>
                                        
                                        <p class="mb-1"><strong>Phone:</strong> <?php echo $user['phone'] ? sanitize($user['phone']) : 'N/A'; ?></p>
                                        <p class="mb-1"><strong>Address:</strong> <?php echo $user['address'] ? nl2br(sanitize($user['address'])) : 'N/A'; ?></p>
                                        <p class="mb-1"><strong>Joined:</strong> <?php echo date('F d, Y', strtotime($user['created_at'])); ?></p>
                                        <p class="mb-0"><strong>Status:</strong> <span class="badge badge-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>"><?php echo ucfirst($user['status']); ?></span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
<?php if ($pagination['total_pages'] > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                <li class="page-item <?php echo $i === $pagination['current_page'] ? 'active' : ''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
