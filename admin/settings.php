<?php
require_once '../config/functions.php';

if (!isSuperAdmin()) {
    setFlashMessage('error', 'Access denied. Super Admin only.');
    redirect('index.php');
}

$page_title = 'Settings';
$db = getDB();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['general_settings'])) {
        updateSetting('site_name', sanitize($_POST['site_name'] ?? ''));
        updateSetting('site_email', sanitize($_POST['site_email'] ?? ''));
        updateSetting('site_phone', sanitize($_POST['site_phone'] ?? ''));
        updateSetting('site_address', sanitize($_POST['site_address'] ?? ''));
        
        logActivity('Settings Updated', 'General settings updated');
        setFlashMessage('success', 'General settings saved!');
    }
    
    if (isset($_POST['payment_settings'])) {
        updateSetting('currency', sanitize($_POST['currency'] ?? 'USD'));
        updateSetting('currency_symbol', sanitize($_POST['currency_symbol'] ?? '$'));
        updateSetting('tax_rate', floatval($_POST['tax_rate'] ?? 0));
        updateSetting('shipping_cost', floatval($_POST['shipping_cost'] ?? 0));
        
        // Payment method toggles
        updateSetting('enable_bkash', isset($_POST['enable_bkash']) ? '1' : '0');
        updateSetting('enable_nagad', isset($_POST['enable_nagad']) ? '1' : '0');
        updateSetting('enable_cod', isset($_POST['enable_cod']) ? '1' : '0');
        
        // bKash Configuration
        updateSetting('bkash_app_key', sanitize($_POST['bkash_app_key'] ?? ''));
        updateSetting('bkash_app_secret', sanitize($_POST['bkash_app_secret'] ?? ''));
        updateSetting('bkash_username', sanitize($_POST['bkash_username'] ?? ''));
        updateSetting('bkash_password', sanitize($_POST['bkash_password'] ?? ''));
        updateSetting('bkash_mode', sanitize($_POST['bkash_mode'] ?? 'sandbox'));
        
        // Nagad Configuration
        updateSetting('nagad_merchant_id', sanitize($_POST['nagad_merchant_id'] ?? ''));
        updateSetting('nagad_merchant_number', sanitize($_POST['nagad_merchant_number'] ?? ''));
        updateSetting('nagad_mode', sanitize($_POST['nagad_mode'] ?? 'sandbox'));
        
        logActivity('Settings Updated', 'Payment settings updated');
        setFlashMessage('success', 'Payment settings saved!');
    }
    
    if (isset($_POST['email_settings'])) {
        updateSetting('smtp_host', sanitize($_POST['smtp_host'] ?? ''));
        updateSetting('smtp_port', intval($_POST['smtp_port'] ?? 587));
        updateSetting('smtp_username', sanitize($_POST['smtp_username'] ?? ''));
        updateSetting('smtp_password', $_POST['smtp_password'] ?? '');
        updateSetting('smtp_encryption', sanitize($_POST['smtp_encryption'] ?? 'tls'));
        
        logActivity('Settings Updated', 'Email settings updated');
        setFlashMessage('success', 'Email settings saved!');
    }
    
    redirect('settings.php');
}

// Get current settings
$settings = [
    'site_name' => getSetting('site_name', 'Pharmacy Management System'),
    'site_email' => getSetting('site_email', ''),
    'site_phone' => getSetting('site_phone', ''),
    'site_address' => getSetting('site_address', ''),
    'currency' => getSetting('currency', 'BDT'),
    'currency_symbol' => getSetting('currency_symbol', '৳'),
    'tax_rate' => getSetting('tax_rate', 0),
    'shipping_cost' => getSetting('shipping_cost', 0),
    
    // Payment method toggles
    'enable_bkash' => getSetting('enable_bkash', '0'),
    'enable_nagad' => getSetting('enable_nagad', '0'),
    'enable_cod' => getSetting('enable_cod', '1'),
    
    // bKash Settings
    'bkash_app_key' => getSetting('bkash_app_key', ''),
    'bkash_app_secret' => getSetting('bkash_app_secret', ''),
    'bkash_username' => getSetting('bkash_username', ''),
    'bkash_password' => getSetting('bkash_password', ''),
    'bkash_mode' => getSetting('bkash_mode', 'sandbox'),
    
    // Nagad Settings
    'nagad_merchant_id' => getSetting('nagad_merchant_id', ''),
    'nagad_merchant_number' => getSetting('nagad_merchant_number', ''),
    'nagad_mode' => getSetting('nagad_mode', 'sandbox'),
    
    'smtp_host' => getSetting('smtp_host', ''),
    'smtp_port' => getSetting('smtp_port', 587),
    'smtp_username' => getSetting('smtp_username', ''),
    'smtp_password' => getSetting('smtp_password', ''),
    'smtp_encryption' => getSetting('smtp_encryption', 'tls'),
];

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<!-- Settings Tabs -->
<ul class="nav nav-pills mb-4" id="settingsTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="general-tab" data-mdb-tab-init data-mdb-target="#general" role="tab">
            <i class="fas fa-cog me-2"></i>General
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="payment-tab" data-mdb-tab-init data-mdb-target="#payment" role="tab">
            <i class="fas fa-credit-card me-2"></i>Payment
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="email-tab" data-mdb-tab-init data-mdb-target="#email" role="tab">
            <i class="fas fa-envelope me-2"></i>Email
        </button>
    </li>
</ul>

<div class="tab-content" id="settingsTabContent">
    <!-- General Settings -->
    <div class="tab-pane fade show active" id="general" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="">
                    <input type="hidden" name="general_settings" value="1">
                    
                    <h5 class="fw-bold mb-4">General Settings</h5>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Site Name</label>
                            <input type="text" name="site_name" class="form-control" value="<?php echo $settings['site_name']; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Site Email</label>
                            <input type="email" name="site_email" class="form-control" value="<?php echo $settings['site_email']; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Site Phone</label>
                            <input type="tel" name="site_phone" class="form-control" value="<?php echo $settings['site_phone']; ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Site Address</label>
                            <textarea name="site_address" class="form-control" rows="2"><?php echo $settings['site_address']; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save General Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Payment Settings -->
    <div class="tab-pane fade" id="payment" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="">
                    <input type="hidden" name="payment_settings" value="1">
                    
                    <h5 class="fw-bold mb-4">Payment Settings</h5>
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Currency</label>
                            <select name="currency" class="form-select">
                                <option value="BDT" <?php echo $settings['currency'] === 'BDT' ? 'selected' : ''; ?>>BDT - Bangladeshi Taka</option>
                                <option value="USD" <?php echo $settings['currency'] === 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                                <option value="EUR" <?php echo $settings['currency'] === 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                                <option value="GBP" <?php echo $settings['currency'] === 'GBP' ? 'selected' : ''; ?>>GBP - British Pound</option>
                                <option value="INR" <?php echo $settings['currency'] === 'INR' ? 'selected' : ''; ?>>INR - Indian Rupee</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Currency Symbol</label>
                            <input type="text" name="currency_symbol" class="form-control" value="<?php echo $settings['currency_symbol']; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tax Rate (%)</label>
                            <input type="number" name="tax_rate" class="form-control" step="0.01" min="0" value="<?php echo $settings['tax_rate']; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Shipping Cost</label>
                            <div class="input-group">
                                <span class="input-group-text"><?php echo $settings['currency_symbol']; ?></span>
                                <input type="number" name="shipping_cost" class="form-control" step="0.01" min="0" value="<?php echo $settings['shipping_cost']; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h5 class="fw-bold mb-4">Payment Methods</h5>
                    
                    <!-- bKash -->
                    <div class="card mb-4" style="border-left: 4px solid #e2136e;">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <span style="background: #e2136e; color: white; padding: 5px 15px; border-radius: 5px; font-weight: bold; font-size: 14px; margin-right: 10px;">bKash</span>
                                <h6 class="mb-0 fw-bold">bKash Payment</h6>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="enable_bkash" id="enable_bkash" <?php echo $settings['enable_bkash'] === '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_bkash">Enable</label>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">App Key</label>
                                    <input type="text" name="bkash_app_key" class="form-control" value="<?php echo $settings['bkash_app_key']; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">App Secret</label>
                                    <input type="password" name="bkash_app_secret" class="form-control" value="<?php echo $settings['bkash_app_secret']; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="bkash_username" class="form-control" value="<?php echo $settings['bkash_username']; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="bkash_password" class="form-control" value="<?php echo $settings['bkash_password']; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Mode</label>
                                    <select name="bkash_mode" class="form-select">
                                        <option value="sandbox" <?php echo $settings['bkash_mode'] === 'sandbox' ? 'selected' : ''; ?>>Sandbox</option>
                                        <option value="live" <?php echo $settings['bkash_mode'] === 'live' ? 'selected' : ''; ?>>Live</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Nagad -->
                    <div class="card mb-4" style="border-left: 4px solid #ec1c24;">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <span style="background: #ec1c24; color: white; padding: 5px 15px; border-radius: 5px; font-weight: bold; font-size: 14px; margin-right: 10px;">Nagad</span>
                                <h6 class="mb-0 fw-bold">Nagad Payment</h6>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="enable_nagad" id="enable_nagad" <?php echo $settings['enable_nagad'] === '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_nagad">Enable</label>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Merchant ID</label>
                                    <input type="text" name="nagad_merchant_id" class="form-control" value="<?php echo $settings['nagad_merchant_id']; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Merchant Number</label>
                                    <input type="text" name="nagad_merchant_number" class="form-control" value="<?php echo $settings['nagad_merchant_number']; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Mode</label>
                                    <select name="nagad_mode" class="form-select">
                                        <option value="sandbox" <?php echo $settings['nagad_mode'] === 'sandbox' ? 'selected' : ''; ?>>Sandbox</option>
                                        <option value="live" <?php echo $settings['nagad_mode'] === 'live' ? 'selected' : ''; ?>>Live</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cash on Delivery -->
                    <div class="card mb-4" style="border-left: 4px solid #00b74a;">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-money-bill-wave fa-lg text-success me-3"></i>
                                <h6 class="mb-0 fw-bold">Cash on Delivery</h6>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="enable_cod" id="enable_cod" <?php echo $settings['enable_cod'] === '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_cod">Enable</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Payment Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Email Settings -->
    <div class="tab-pane fade" id="email" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="">
                    <input type="hidden" name="email_settings" value="1">
                    
                    <h5 class="fw-bold mb-4">Email (SMTP) Settings</h5>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">SMTP Host</label>
                            <input type="text" name="smtp_host" class="form-control" value="<?php echo $settings['smtp_host']; ?>" placeholder="smtp.gmail.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">SMTP Port</label>
                            <input type="number" name="smtp_port" class="form-control" value="<?php echo $settings['smtp_port']; ?>" placeholder="587">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">SMTP Username</label>
                            <input type="text" name="smtp_username" class="form-control" value="<?php echo $settings['smtp_username']; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">SMTP Password</label>
                            <input type="password" name="smtp_password" class="form-control" value="<?php echo $settings['smtp_password']; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Encryption</label>
                            <select name="smtp_encryption" class="form-select">
                                <option value="tls" <?php echo $settings['smtp_encryption'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                <option value="ssl" <?php echo $settings['smtp_encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="none" <?php echo $settings['smtp_encryption'] === 'none' ? 'selected' : ''; ?>>None</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Email Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
