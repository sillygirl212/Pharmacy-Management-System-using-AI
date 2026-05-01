<?php
require_once '../config/functions.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$page_title = 'Coupons';
$db = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(sanitize($_POST['code'] ?? ''));
    $type = sanitize($_POST['type'] ?? 'percentage');
    $value = floatval($_POST['value'] ?? 0);
    $min_purchase = floatval($_POST['min_purchase'] ?? 0);
    $max_discount = !empty($_POST['max_discount']) ? floatval($_POST['max_discount']) : null;
    $usage_limit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $status = sanitize($_POST['status'] ?? 'active');
    $coupon_id = intval($_POST['coupon_id'] ?? 0);
    
    if ($coupon_id) {
        $stmt = $db->prepare("
            UPDATE coupons SET 
                code = ?, type = ?, value = ?, min_purchase = ?, max_discount = ?,
                usage_limit = ?, start_date = ?, end_date = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([$code, $type, $value, $min_purchase, $max_discount, $usage_limit, $start_date, $end_date, $status, $coupon_id]);
        logActivity('Coupon Updated', "Coupon '{$code}' updated");
        setFlashMessage('success', 'Coupon updated!');
    } else {
        $stmt = $db->prepare("
            INSERT INTO coupons (code, type, value, min_purchase, max_discount, usage_limit, start_date, end_date, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$code, $type, $value, $min_purchase, $max_discount, $usage_limit, $start_date, $end_date, $status]);
        logActivity('Coupon Created', "Coupon '{$code}' created");
        setFlashMessage('success', 'Coupon created!');
    }
    redirect('coupons.php');
}

// Delete coupon
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $coupon_id = intval($_GET['delete']);
    $db->prepare("DELETE FROM coupons WHERE id = ?")->execute([$coupon_id]);
    logActivity('Coupon Deleted', "Coupon ID {$coupon_id} deleted");
    setFlashMessage('success', 'Coupon deleted!');
    redirect('coupons.php');
}

// Get all coupons
$coupons = $db->query("
    SELECT c.*, 
        (SELECT COUNT(*) FROM orders WHERE coupon_code = c.code) as usage_count_actual
    FROM coupons c 
    ORDER BY c.created_at DESC
")->fetchAll();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Coupons</h2>
    <button class="btn btn-primary" data-mdb-modal-init data-mdb-target="#couponModal">
        <i class="fas fa-plus me-2"></i>Add Coupon
    </button>
</div>

<!-- Coupons Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Code</th>
                        <th>Discount</th>
                        <th>Usage</th>
                        <th>Validity</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coupons as $coupon): ?>
                        <tr>
                            <td>
                                <code class="h5"><?php echo $coupon['code']; ?></code>
                            </td>
                            <td>
                                <?php if ($coupon['type'] === 'percentage'): ?>
                                    <span class="text-success fw-bold"><?php echo $coupon['value']; ?>% OFF</span>
                                <?php else: ?>
                                    <span class="text-success fw-bold">$<?php echo $coupon['value']; ?> OFF</span>
                                <?php endif; ?>
                                <?php if ($coupon['min_purchase'] > 0): ?>
                                    <br><small class="text-muted">Min. $<?php echo $coupon['min_purchase']; ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $used = $coupon['usage_count'];
                                $limit = $coupon['usage_limit'] ?? '∞';
                                echo "{$used} / {$limit}";
                                ?>
                                <br><small class="text-muted">(<?php echo $coupon['usage_count_actual']; ?> orders)</small>
                            </td>
                            <td>
                                <?php if ($coupon['start_date'] || $coupon['end_date']): ?>
                                    <?php echo $coupon['start_date'] ? date('M d', strtotime($coupon['start_date'])) : 'Start'; ?>
                                    -
                                    <?php echo $coupon['end_date'] ? date('M d, Y', strtotime($coupon['end_date'])) : 'No End'; ?>
                                <?php else: ?>
                                    <span class="text-muted">No expiry</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $coupon['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($coupon['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="editCoupon(<?php echo htmlspecialchars(json_encode($coupon)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="coupons.php?delete=<?php echo $coupon['id']; ?>" class="btn btn-outline-danger" onclick="return confirmDelete()">
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

<!-- Coupon Modal -->
<div class="modal fade" id="couponModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalTitle">Add Coupon</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="coupon_id" id="coupon_id" value="0">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Coupon Code *</label>
                            <input type="text" name="code" id="coupon_code" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Discount Type</label>
                            <select name="type" id="coupon_type" class="form-select">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount ($)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Discount Value *</label>
                            <input type="number" name="value" id="coupon_value" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Minimum Purchase</label>
                            <input type="number" name="min_purchase" id="coupon_min" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Maximum Discount</label>
                            <input type="number" name="max_discount" id="coupon_max" class="form-control" step="0.01" min="0" placeholder="No limit">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Usage Limit</label>
                            <input type="number" name="usage_limit" id="coupon_limit" class="form-control" min="1" placeholder="Unlimited">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" id="coupon_start" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" id="coupon_end" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" id="coupon_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Coupon</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCoupon(coupon) {
    document.getElementById('modalTitle').textContent = 'Edit Coupon';
    document.getElementById('coupon_id').value = coupon.id;
    document.getElementById('coupon_code').value = coupon.code;
    document.getElementById('coupon_type').value = coupon.type;
    document.getElementById('coupon_value').value = coupon.value;
    document.getElementById('coupon_min').value = coupon.min_purchase;
    document.getElementById('coupon_max').value = coupon.max_discount || '';
    document.getElementById('coupon_limit').value = coupon.usage_limit || '';
    document.getElementById('coupon_start').value = coupon.start_date || '';
    document.getElementById('coupon_end').value = coupon.end_date || '';
    document.getElementById('coupon_status').value = coupon.status;
    
    const modal = new mdb.Modal(document.getElementById('couponModal'));
    modal.show();
}
</script>

<?php include 'includes/footer.php'; ?>
