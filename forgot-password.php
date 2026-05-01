<?php
require_once 'config/functions.php';
$page_title = 'Forgot Password';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate reset token
            $token = generateRandomString(32);
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $db->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
            $stmt->execute([$token, $expiry, $user['id']]);
            
            // Send reset email (in production, use proper email service)
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $token;
            
            $subject = "Password Reset Request - " . getSetting('site_name');
            $body = "
                <h2>Password Reset Request</h2>
                <p>Hello {$user['name']},</p>
                <p>You have requested to reset your password. Click the link below to reset it:</p>
                <p><a href='{$reset_link}' style='padding: 10px 20px; background: #1266f1; color: white; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
                <p>Or copy and paste this link: {$reset_link}</p>
                <p>This link will expire in 1 hour.</p>
                <p>If you didn't request this, please ignore this email.</p>
            ";
            
            // For demo purposes, just show the message
            // sendEmail($email, $subject, $body);
            
            logActivity('Password Reset Request', "Reset requested for: {$email}");
            
            $success = 'Password reset instructions have been sent to your email address.';
        } else {
            // Don't reveal if email exists
            $success = 'If an account exists with this email, password reset instructions will be sent.';
        }
    }
}

include 'includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card auth-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-key fa-4x text-primary mb-3"></i>
                            <h2 class="fw-bold">Forgot Password?</h2>
                            <p class="text-muted">Enter your email to reset your password</p>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="login.php" class="btn btn-primary">Back to Login</a>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="">
                                <div class="form-floating mb-4">
                                    <input type="email" id="email" name="email" class="form-control" placeholder="Email address" required>
                                    <label for="email">Email address</label>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-lg w-100 mb-4">
                                    <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                                </button>
                                
                                <div class="text-center">
                                    <p class="mb-0">Remember your password? <a href="login.php" class="text-primary fw-bold">Sign In</a></p>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
