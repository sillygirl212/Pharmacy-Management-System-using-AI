<?php
require_once 'config/functions.php';
$page_title = 'Payment';

if (!isLoggedIn()) {
    redirect('login.php');
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if (!$order_id) {
    setFlashMessage('error', 'Invalid order.');
    redirect('cart.php');
}

$db = getDB();

// Get order details
$stmt = $db->prepare("
    SELECT * FROM orders 
    WHERE id = ? AND user_id = ?
");
$stmt->execute([$order_id, getCurrentUserId()]);
$order = $stmt->fetch();

if (!$order) {
    setFlashMessage('error', 'Order not found.');
    redirect('cart.php');
}

if ($order['payment_status'] === 'paid') {
    setFlashMessage('success', 'Payment already completed for this order.');
    redirect('order-confirmation.php?order_id=' . $order_id);
}

// COD orders don't come here - they go directly to confirmation
if ($order['payment_method'] === 'cod') {
    redirect('order-confirmation.php?order_id=' . $order_id);
}

$payment_method = $order['payment_method'];
$error = '';

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bkash_number = sanitize($_POST['bkash_number'] ?? '');
    $transaction_id = sanitize($_POST['transaction_id'] ?? '');
    $nagad_number = sanitize($_POST['nagad_number'] ?? '');
    
    // Validate based on payment method
    if ($payment_method === 'bkash') {
        if (empty($bkash_number) || empty($transaction_id)) {
            $error = 'Please enter your bKash number and Transaction ID.';
        } elseif (!preg_match('/^01\d{9}$/', $bkash_number)) {
            $error = 'Please enter a valid bKash number (e.g., 01712345678).';
        }
    } elseif ($payment_method === 'nagad') {
        if (empty($nagad_number) || empty($transaction_id)) {
            $error = 'Please enter your Nagad number and Transaction ID.';
        } elseif (!preg_match('/^01\d{9}$/', $nagad_number)) {
            $error = 'Please enter a valid Nagad number (e.g., 01712345678).';
        }
    }
    
    if (empty($error)) {
        // In production, verify the transaction with bKash/Nagad API
        // For demo, we simulate successful verification
        
        $db_transaction_id = strtoupper($payment_method) . '-' . $transaction_id;
        
        $stmt = $db->prepare("
            UPDATE orders 
            SET payment_status = 'paid', 
                transaction_id = ?,
                status = 'processing',
                notes = ?
            WHERE id = ?
        ");
        
        $notes = $payment_method === 'bkash' 
            ? "bKash Number: {$bkash_number}"
            : "Nagad Number: {$nagad_number}";
        
        $stmt->execute([$db_transaction_id, $notes, $order_id]);
        
        logActivity('Payment Completed', ucfirst($payment_method) . " payment completed for order #{$order['order_number']}");
        
        // Send confirmation email
        $subject = "Order Confirmation - {$order['order_number']}";
        $body = "<h2>Thank you for your order!</h2>
                <p>Your order #{$order['order_number']} has been confirmed.</p>
                <p>Payment Method: " . ucfirst($payment_method) . "<br>
                Transaction ID: {$transaction_id}</p>";
        sendEmail($order['billing_email'], $subject, $body);
        
        setFlashMessage('success', 'Payment successful! Thank you for your order.');
        redirect('order-confirmation.php?order_id=' . $order_id);
    }
}

include 'includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <?php if ($payment_method === 'bkash'): ?>
                                <span style="background: #e2136e; color: white; padding: 10px 25px; border-radius: 8px; font-weight: bold; font-size: 24px; display: inline-block;">bKash</span>
                            <?php elseif ($payment_method === 'nagad'): ?>
                                <span style="background: #ec1c24; color: white; padding: 10px 25px; border-radius: 8px; font-weight: bold; font-size: 24px; display: inline-block;">Nagad</span>
                            <?php else: ?>
                                <i class="fas fa-credit-card fa-3x text-primary mb-3"></i>
                            <?php endif; ?>
                            <h2 class="fw-bold mb-2 mt-3">Complete Payment</h2>
                            <p class="text-muted">
                                Order: <strong>#<?php echo $order['order_number']; ?></strong><br>
                                Amount: <strong class="text-primary h4"><?php echo formatPrice($order['total']); ?></strong>
                            </p>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger mb-4">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Payment Instructions -->
                        <div class="alert alert-info mb-4">
                            <h6 class="fw-bold mb-2"><i class="fas fa-info-circle me-2"></i>Payment Instructions:</h6>
                            <?php if ($payment_method === 'bkash'): ?>
                                <ol class="mb-0 ps-3">
                                    <li>Dial *247# or open bKash app</li>
                                    <li>Choose "Send Money"</li>
                                    <li>Enter number: <strong><?php echo getSetting('bkash_username', '01XXXXXXXXX'); ?></strong></li>
                                    <li>Enter amount: <strong><?php echo formatPrice($order['total']); ?></strong></li>
                                    <li>Enter your PIN to confirm</li>
                                    <li>Enter the Transaction ID below</li>
                                </ol>
                            <?php elseif ($payment_method === 'nagad'): ?>
                                <ol class="mb-0 ps-3">
                                    <li>Dial *167# or open Nagad app</li>
                                    <li>Choose "Send Money"</li>
                                    <li>Enter number: <strong><?php echo getSetting('nagad_merchant_number', '01XXXXXXXXX'); ?></strong></li>
                                    <li>Enter amount: <strong><?php echo formatPrice($order['total']); ?></strong></li>
                                    <li>Enter your PIN to confirm</li>
                                    <li>Enter the Transaction ID below</li>
                                </ol>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Payment Form -->
                        <form method="POST" action="">
                            <?php if ($payment_method === 'bkash'): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Your bKash Number *</label>
                                    <input type="tel" name="bkash_number" class="form-control form-control-lg" placeholder="01XXXXXXXXX" pattern="01[0-9]{9}" required>
                                    <small class="text-muted">Enter the bKash number you used to send money</small>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Transaction ID *</label>
                                    <input type="text" name="transaction_id" class="form-control form-control-lg" placeholder="e.g., A1B2C3D4E5" required>
                                    <small class="text-muted">You received this in the SMS confirmation</small>
                                </div>
                                <button type="submit" class="btn btn-lg w-100" style="background: #e2136e; color: white;">
                                    <i class="fas fa-check-circle me-2"></i>Verify bKash Payment
                                </button>
                                
                            <?php elseif ($payment_method === 'nagad'): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Your Nagad Number *</label>
                                    <input type="tel" name="nagad_number" class="form-control form-control-lg" placeholder="01XXXXXXXXX" pattern="01[0-9]{9}" required>
                                    <small class="text-muted">Enter the Nagad number you used to send money</small>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Transaction ID *</label>
                                    <input type="text" name="transaction_id" class="form-control form-control-lg" placeholder="e.g., 1234567890" required>
                                    <small class="text-muted">You received this in the SMS confirmation</small>
                                </div>
                                <button type="submit" class="btn btn-lg w-100" style="background: #ec1c24; color: white;">
                                    <i class="fas fa-check-circle me-2"></i>Verify Nagad Payment
                                </button>
                            <?php endif; ?>
                        </form>
                        
                        <div class="mt-4 text-center">
                            <a href="checkout.php" class="text-muted">
                                <i class="fas fa-arrow-left me-1"></i>Cancel and return to checkout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
