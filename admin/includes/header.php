<?php
require_once __DIR__ . '/../../config/functions.php';

// Check admin access
if (!isAdmin()) {
    redirect('../login.php');
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Get admin user info
$admin_name = $_SESSION['user_name'] ?? 'Admin';
$admin_role = $_SESSION['user_role'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Admin Panel</title>
    
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/7.1.0/mdb.min.css" rel="stylesheet" />
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    
    <style>
        :root {
            --sidebar-width: 260px;
            --primary-color: #1266f1;
        }
        
        body {
            background-color: #f5f6fa;
        }
        
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .admin-sidebar .logo {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .admin-sidebar .logo h4 {
            font-weight: 700;
            margin: 0;
        }
        
        .admin-sidebar .nav-menu {
            padding: 20px 0;
        }
        
        .admin-sidebar .nav-item {
            margin: 5px 0;
        }
        
        .admin-sidebar .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .admin-sidebar .nav-link:hover,
        .admin-sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left: 4px solid var(--primary-color);
        }
        
        .admin-sidebar .nav-link i {
            width: 25px;
            margin-right: 10px;
        }
        
        .admin-main {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        
        .admin-header {
            background: white;
            padding: 15px 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .admin-content {
            padding: 25px;
        }
        
        .stat-card {
            border-radius: 15px;
            border: none;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        @media (max-width: 992px) {
            .admin-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .admin-sidebar.show {
                transform: translateX(0);
            }
            
            .admin-main {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <div class="logo">
            <h4><i class="fas fa-pharmacy me-2"></i>Admin</h4>
        </div>
        
        <nav class="nav-menu">
            <div class="nav-item">
                <a href="index.php" class="nav-link <?php echo $current_page == 'index' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="products.php" class="nav-link <?php echo $current_page == 'products' ? 'active' : ''; ?>">
                    <i class="fas fa-box"></i>Products
                </a>
            </div>
            <div class="nav-item">
                <a href="categories.php" class="nav-link <?php echo $current_page == 'categories' ? 'active' : ''; ?>">
                    <i class="fas fa-tags"></i>Categories
                </a>
            </div>
            <div class="nav-item">
                <a href="orders.php" class="nav-link <?php echo $current_page == 'orders' ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i>Orders
                </a>
            </div>
            <div class="nav-item">
                <a href="users.php" class="nav-link <?php echo $current_page == 'users' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>Users
                </a>
            </div>
            <div class="nav-item">
                <a href="coupons.php" class="nav-link <?php echo $current_page == 'coupons' ? 'active' : ''; ?>">
                    <i class="fas fa-ticket-alt"></i>Coupons
                </a>
            </div>
            <div class="nav-item">
                <a href="reviews.php" class="nav-link <?php echo $current_page == 'reviews' ? 'active' : ''; ?>">
                    <i class="fas fa-star"></i>Reviews
                </a>
            </div>
            <div class="nav-item">
                <a href="tickets.php" class="nav-link <?php echo $current_page == 'tickets' ? 'active' : ''; ?>">
                    <i class="fas fa-headset"></i>Support Tickets
                </a>
            </div>
            <div class="nav-item">
                <a href="reports.php" class="nav-link <?php echo $current_page == 'reports' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>Reports
                </a>
            </div>
            <div class="nav-item">
                <a href="chatbot.php" class="nav-link <?php echo in_array($current_page, ['chatbot', 'chatbot-settings']) ? 'active' : ''; ?>">
                    <i class="fas fa-robot"></i>AI Chatbot
                    <?php 
                    // Check for active stock alerts
                    $db = getDB();
                    $alerts = $db->query("SELECT COUNT(*) FROM stock_alerts WHERE is_triggered = 1 AND resolved_at IS NULL")->fetchColumn();
                    if ($alerts > 0): 
                    ?>
                        <span class="badge badge-danger ms-2"><?php echo $alerts; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            <div class="nav-item">
                <a href="settings.php" class="nav-link <?php echo $current_page == 'settings' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>Settings
                </a>
            </div>
            
            <hr class="mx-3 my-4" style="border-color: rgba(255,255,255,0.1);">
            
            <div class="nav-item">
                <a href="../index.php" target="_blank" class="nav-link">
                    <i class="fas fa-external-link-alt"></i>View Site
                </a>
            </div>
            <div class="nav-item">
                <a href="../logout.php" class="nav-link text-danger">
                    <i class="fas fa-sign-out-alt"></i>Logout
                </a>
            </div>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="admin-main">
        <!-- Header -->
        <header class="admin-header">
            <button class="btn btn-link d-lg-none" id="sidebarToggle">
                <i class="fas fa-bars fa-lg"></i>
            </button>
            
            <div class="d-flex align-items-center">
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-mdb-dropdown-init>
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($admin_name); ?>&background=1266f1&color=fff&size=40" 
                             class="rounded-circle me-2" width="40" height="40" alt="Admin">
                        <span class="fw-medium"><?php echo $admin_name; ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <!-- Flash Messages -->
        <?php $flash = getFlashMessage(); if ($flash): ?>
            <div class="container-fluid pt-4">
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $flash['message']; ?>
                    <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Content -->
        <div class="admin-content">
