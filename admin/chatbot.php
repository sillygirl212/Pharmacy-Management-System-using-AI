<?php
require_once '../config/functions.php';
require_once '../config/ai-chatbot.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$page_title = 'AI Chatbot';
$db = getDB();
$chatbot = new PharmacyAIChatbot();

// Get analytics
$analytics = $chatbot->getAnalytics(7);

// Get recent conversations
$conversations = $db->query("
    SELECT cc.*, u.name as user_name, u.email as user_email
    FROM chatbot_conversations cc
    LEFT JOIN users u ON cc.user_id = u.id
    ORDER BY cc.created_at DESC
    LIMIT 50
")->fetchAll();

// Get stock alerts
$stock_alerts = $db->query("
    SELECT sa.*, p.name as product_name, p.stock_quantity, p.sku
    FROM stock_alerts sa
    JOIN products p ON sa.product_id = p.id
    WHERE sa.is_triggered = 1 AND sa.resolved_at IS NULL
    ORDER BY sa.created_at DESC
")->fetchAll();

// Get AI configuration
$ai_configs = $db->query("SELECT * FROM ai_config ORDER BY is_active DESC")->fetchAll();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">AI Chatbot</h2>
    <a href="chatbot-settings.php" class="btn btn-primary">
        <i class="fas fa-cog me-2"></i>AI Settings
    </a>
</div>

<!-- Analytics Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-primary text-white">
            <div class="card-body p-4">
                <h6 class="mb-1">Chat Sessions (7 days)</h6>
                <h3 class="fw-bold mb-0"><?php echo number_format($analytics['sessions']); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-success text-white">
            <div class="card-body p-4">
                <h6 class="mb-1">Total Messages</h6>
                <h3 class="fw-bold mb-0"><?php echo number_format($analytics['messages']); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-warning text-dark">
            <div class="card-body p-4">
                <h6 class="mb-1">Stock Alerts</h6>
                <h3 class="fw-bold mb-0"><?php echo count($stock_alerts); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-info text-white">
            <div class="card-body p-4">
                <h6 class="mb-1">Active AI Provider</h6>
                <h4 class="fw-bold mb-0 text-uppercase"><?php echo $ai_configs[0]['provider'] ?? 'None'; ?></h4>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Top Intents -->
    <div class="col-lg-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0">Top User Queries</h5>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($analytics['top_intents'] as $intent): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-capitalize"><?php echo str_replace('_', ' ', $intent['intent']); ?></span>
                            <span class="badge badge-primary rounded-pill"><?php echo $intent['count']; ?></span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($analytics['top_intents'])): ?>
                        <li class="list-group-item text-muted text-center">No data available</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        
        <!-- Stock Alerts -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0">Stock Alerts</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <tbody>
                            <?php foreach ($stock_alerts as $alert): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($alert['stock_quantity'] == 0): ?>
                                                <span class="badge badge-danger me-2">Out of Stock</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning me-2">Low Stock (<?php echo $alert['stock_quantity']; ?>)</span>
                                            <?php endif; ?>
                                            <span><?php echo sanitize($alert['product_name']); ?></span>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <a href="product-form.php?id=<?php echo $alert['product_id']; ?>" class="btn btn-sm btn-outline-primary">Restock</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($stock_alerts)): ?>
                                <tr>
                                    <td class="text-center text-muted py-3">No active stock alerts</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Conversations -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0">Recent Conversations</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Time</th>
                                <th>User</th>
                                <th>Intent</th>
                                <th>Confidence</th>
                                <th>Preview</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($conversations as $conv): ?>
                                <tr>
                                    <td><?php echo timeAgo($conv['created_at']); ?></td>
                                    <td>
                                        <?php if ($conv['user_name']): ?>
                                            <span class="fw-medium"><?php echo sanitize($conv['user_name']); ?></span>
                                            <br><small class="text-muted"><?php echo sanitize($conv['user_email']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Guest</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($conv['intent']): ?>
                                            <span class="badge badge-secondary text-capitalize"><?php echo str_replace('_', ' ', $conv['intent']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($conv['confidence']): ?>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1" style="height: 6px; width: 50px;">
                                                    <div class="progress-bar" role="progressbar" style="width: <?php echo $conv['confidence'] * 100; ?>%"></div>
                                                </div>
                                                <span class="ms-2 small"><?php echo round($conv['confidence'] * 100); ?>%</span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo truncateText(sanitize($conv['user_message']), 40); ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Knowledge Base Quick View -->
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">Knowledge Base</h5>
                <a href="chatbot-knowledge.php" class="btn btn-sm btn-outline-primary">Manage</a>
            </div>
            <div class="card-body">
                <p class="text-muted mb-0">The AI chatbot uses a combination of rule-based responses and AI API (Gemini/ChatGPT) for handling customer queries. Key capabilities:</p>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Medicine availability check</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Price inquiries</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Stock alerts</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Order status</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Sales reports (admin)</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>AI-powered responses</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
