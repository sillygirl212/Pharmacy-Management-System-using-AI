<?php
require_once 'config/functions.php';
$page_title = 'Register';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();
        
        // Check if email exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email address is already registered.';
        } else {
            // Create user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'user', 'active')");
            
            try {
                $stmt->execute([$name, $email, $phone, $hashed_password]);
                $success = 'Registration successful! Please login with your credentials.';
                
                logActivity('User Registration', "New user registered: {$email}");
            } catch (PDOException $e) {
                $error = 'Registration failed. Please try again.';
            }
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
                            <i class="fas fa-user-plus fa-4x text-primary mb-3"></i>
                            <h2 class="fw-bold">Create Account</h2>
                            <p class="text-muted">Join our pharmacy community</p>
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
                                <a href="login.php" class="btn btn-primary">Go to Login</a>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="">
                                <!-- Name input -->
                                <div class="form-floating mb-3">
                                    <input type="text" id="name" name="name" class="form-control" placeholder="Full Name" required>
                                    <label for="name">Full Name</label>
                                </div>
                                
                                <!-- Email input -->
                                <div class="form-floating mb-3">
                                    <input type="email" id="email" name="email" class="form-control" placeholder="Email address" required>
                                    <label for="email">Email address</label>
                                </div>
                                
                                <!-- Phone input -->
                                <div class="form-floating mb-3">
                                    <input type="tel" id="phone" name="phone" class="form-control" placeholder="Phone Number">
                                    <label for="phone">Phone Number (Optional)</label>
                                </div>
                                
                                <!-- Password input -->
                                <div class="form-floating mb-3">
                                    <input type="password" id="password" name="password" class="form-control" placeholder="Password" required minlength="6">
                                    <label for="password">Password</label>
                                </div>
                                
                                <!-- Confirm Password input -->
                                <div class="form-floating mb-4">
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm Password" required>
                                    <label for="confirm_password">Confirm Password</label>
                                </div>
                                
                                <!-- Terms -->
                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" value="" id="terms" required>
                                    <label class="form-check-label" for="terms">
                                        I agree to the <a href="terms.php" target="_blank">Terms of Service</a> and 
                                        <a href="privacy.php" target="_blank">Privacy Policy</a>
                                    </label>
                                </div>
                                
                                <!-- Submit button -->
                                <button type="submit" class="btn btn-primary btn-lg w-100 mb-4">
                                    <i class="fas fa-user-plus me-2"></i>Create Account
                                </button>
                                
                                <!-- Login link -->
                                <div class="text-center">
                                    <p class="mb-0">Already have an account? <a href="login.php" class="text-primary fw-bold">Sign In</a></p>
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
