<?php
require_once 'config/functions.php';
$page_title = 'Contact Us';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Create support ticket
        $db = getDB();
        $ticket_number = 'TKT' . date('Ymd') . rand(1000, 9999);
        
        $stmt = $db->prepare("
            INSERT INTO support_tickets (user_id, ticket_number, subject, message, category, priority)
            VALUES (?, ?, ?, ?, 'general', 'medium')
        ");
        
        $user_id = isLoggedIn() ? getCurrentUserId() : null;
        $full_message = "From: $name ($email)\n\n$message";
        
        $stmt->execute([$user_id, $ticket_number, $subject, $full_message]);
        
        logActivity('Support Ticket Created', "Ticket {$ticket_number} created by {$email}");
        
        // Send notification email
        $admin_email = getSetting('site_email', 'support@pharmacy.com');
        $email_subject = "New Support Ticket: {$ticket_number}";
        $email_body = "<h2>New Support Ticket</h2><p><strong>Ticket #:</strong> {$ticket_number}</p><p><strong>From:</strong> {$name} ({$email})</p><p><strong>Subject:</strong> {$subject}</p><p><strong>Message:</strong></p><p>{$message}</p>";
        sendEmail($admin_email, $email_subject, $email_body);
        
        $success = 'Thank you for your message! We will get back to you soon. Your ticket number is: ' . $ticket_number;
    }
}

include 'includes/header.php';
?>

<!-- Page Banner -->
<section class="page-banner" style="padding: 60px 0;">
    <div class="container">
        <h1 class="fw-bold mb-2">Contact Us</h1>
        <p class="mb-0">We'd love to hear from you. Get in touch with our team.</p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <!-- Contact Info -->
            <div class="col-lg-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-4">Contact Information</h5>
                        
                        <div class="d-flex mb-4">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-primary bg-opacity-10 p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="fas fa-map-marker-alt text-primary"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="fw-bold mb-1">Address</h6>
                                <p class="text-muted mb-0"><?php echo getSetting('site_address', '123 Pharmacy Street, Medical City'); ?></p>
                            </div>
                        </div>
                        
                        <div class="d-flex mb-4">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-success bg-opacity-10 p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="fas fa-phone text-success"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="fw-bold mb-1">Phone</h6>
                                <p class="text-muted mb-0"><?php echo getSetting('site_phone', '+1 234 567 8900'); ?></p>
                            </div>
                        </div>
                        
                        <div class="d-flex mb-4">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-info bg-opacity-10 p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="fas fa-envelope text-info"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="fw-bold mb-1">Email</h6>
                                <p class="text-muted mb-0"><?php echo getSetting('site_email', 'support@pharmacy.com'); ?></p>
                            </div>
                        </div>
                        
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-warning bg-opacity-10 p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="fas fa-clock text-warning"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="fw-bold mb-1">Working Hours</h6>
                                <p class="text-muted mb-0">Mon - Fri: 9AM - 6PM<br>Sat: 9AM - 2PM</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contact Form -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-4">Send us a Message</h5>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Your Name *</label>
                                        <input type="text" name="name" class="form-control" required 
                                               value="<?php echo isLoggedIn() ? sanitize($_SESSION['user_name']) : ''; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email Address *</label>
                                        <input type="email" name="email" class="form-control" required
                                               value="<?php echo isLoggedIn() ? sanitize($_SESSION['user_email']) : ''; ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Subject *</label>
                                        <input type="text" name="subject" class="form-control" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Message *</label>
                                        <textarea name="message" class="form-control" rows="5" required placeholder="How can we help you?"></textarea>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-paper-plane me-2"></i>Send Message
                                        </button>
                                    </div>
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
