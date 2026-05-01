<?php
require_once '../config/functions.php';

if (!isSuperAdmin()) {
    setFlashMessage('error', 'Access denied. Super Admin only.');
    redirect('index.php');
}

$page_title = 'Chatbot Settings';
$db = getDB();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ai_config'])) {
        $provider = sanitize($_POST['provider'] ?? 'gemini');
        $api_key = sanitize($_POST['api_key'] ?? '');
        $api_endpoint = sanitize($_POST['api_endpoint'] ?? '');
        $model_name = sanitize($_POST['model_name'] ?? '');
        
        // Update active status
        $db->prepare("UPDATE ai_config SET is_active = 0")->execute();
        
        // Check if config exists
        $stmt = $db->prepare("SELECT id FROM ai_config WHERE provider = ?");
        $stmt->execute([$provider]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $stmt = $db->prepare("
                UPDATE ai_config 
                SET api_key = ?, api_endpoint = ?, model_name = ?, is_active = 1
                WHERE provider = ?
            ");
            $stmt->execute([$api_key, $api_endpoint, $model_name, $provider]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO ai_config (provider, api_key, api_endpoint, model_name, is_active)
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->execute([$provider, $api_key, $api_endpoint, $model_name]);
        }
        
        logActivity('AI Config Updated', "{$provider} configuration updated");
        setFlashMessage('success', 'AI configuration saved!');
    }
    
    if (isset($_POST['test_ai'])) {
        require_once '../config/ai-chatbot.php';
        $chatbot = new PharmacyAIChatbot();
        $test_response = $chatbot->processMessage("Hello, what can you do?");
    }
    
    redirect('chatbot-settings.php');
}

// Get AI configs
$ai_configs = $db->query("SELECT * FROM ai_config")->fetchAll();

// Get knowledge base
$knowledge = $db->query("SELECT * FROM chatbot_knowledge ORDER BY category, priority DESC")->fetchAll();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Chatbot Settings</h2>
    <a href="chatbot.php" class="btn btn-outline-primary">
        <i class="fas fa-arrow-left me-2"></i>Back to Chatbot
    </a>
</div>

<?php if (isset($test_response)): ?>
<div class="alert alert-info mb-4">
    <h6 class="fw-bold">Test Response:</h6>
    <p class="mb-0"><?php echo nl2br(sanitize($test_response['response'])); ?></p>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- AI Provider Configuration -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0"><i class="fas fa-brain me-2 text-primary"></i>AI Provider Configuration</h5>
            </div>
            <div class="card-body p-4">
                <ul class="nav nav-pills mb-4" id="aiTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="gemini-tab" data-mdb-tab-init data-mdb-target="#gemini" role="tab">
                            <i class="fab fa-google me-2"></i>Gemini
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="openai-tab" data-mdb-tab-init data-mdb-target="#openai" role="tab">
                            <i class="fas fa-robot me-2"></i>OpenAI
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="aiTabContent">
                    <!-- Gemini Settings -->
                    <div class="tab-pane fade show active" id="gemini" role="tabpanel">
                        <?php
                        $gemini = array_filter($ai_configs, fn($c) => $c['provider'] === 'gemini');
                        $gemini = $gemini ? array_values($gemini)[0] : null;
                        ?>
                        <form method="POST" action="">
                            <input type="hidden" name="ai_config" value="1">
                            <input type="hidden" name="provider" value="gemini">
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Get your API key from <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">API Key</label>
                                <input type="text" name="api_key" class="form-control" 
                                       value="<?php echo $gemini['api_key'] ?? ''; ?>"
                                       placeholder="AIzaSy...">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">API Endpoint</label>
                                <input type="text" name="api_endpoint" class="form-control" 
                                       value="<?php echo $gemini['api_endpoint'] ?? 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent'; ?>">
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Model Name</label>
                                <input type="text" name="model_name" class="form-control" 
                                       value="<?php echo $gemini['model_name'] ?? 'gemini-pro'; ?>">
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Gemini Config
                                </button>
                                <button type="submit" name="test_ai" class="btn btn-outline-secondary">
                                    <i class="fas fa-vial me-2"></i>Test
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- OpenAI Settings -->
                    <div class="tab-pane fade" id="openai" role="tabpanel">
                        <?php
                        $openai = array_filter($ai_configs, fn($c) => $c['provider'] === 'openai');
                        $openai = $openai ? array_values($openai)[0] : null;
                        ?>
                        <form method="POST" action="">
                            <input type="hidden" name="ai_config" value="1">
                            <input type="hidden" name="provider" value="openai">
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">API Key</label>
                                <input type="text" name="api_key" class="form-control" 
                                       value="<?php echo $openai['api_key'] ?? ''; ?>"
                                       placeholder="sk-...">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">API Endpoint</label>
                                <input type="text" name="api_endpoint" class="form-control" 
                                       value="<?php echo $openai['api_endpoint'] ?? 'https://api.openai.com/v1/chat/completions'; ?>">
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Model Name</label>
                                <select name="model_name" class="form-select">
                                    <option value="gpt-3.5-turbo" <?php echo ($openai['model_name'] ?? '') === 'gpt-3.5-turbo' ? 'selected' : ''; ?>>GPT-3.5 Turbo</option>
                                    <option value="gpt-4" <?php echo ($openai['model_name'] ?? '') === 'gpt-4' ? 'selected' : ''; ?>>GPT-4</option>
                                    <option value="gpt-4-turbo" <?php echo ($openai['model_name'] ?? '') === 'gpt-4-turbo' ? 'selected' : ''; ?>>GPT-4 Turbo</option>
                                </select>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save OpenAI Config
                                </button>
                                <button type="submit" name="test_ai" class="btn btn-outline-secondary">
                                    <i class="fas fa-vial me-2"></i>Test
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Usage Instructions -->
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0"><i class="fas fa-book me-2 text-info"></i>Usage Guide</h5>
            </div>
            <div class="card-body">
                <h6 class="fw-bold">How it works:</h6>
                <ol>
                    <li class="mb-2">The chatbot first checks user queries against the <strong>Knowledge Base</strong> for common patterns</li>
                    <li class="mb-2">For medicine/stock queries, it fetches real-time data from the database</li>
                    <li class="mb-2">For complex queries, it sends the conversation to the configured AI (Gemini/OpenAI)</li>
                    <li class="mb-2">All conversations are logged for analysis and improvement</li>
                </ol>
                
                <h6 class="fw-bold mt-3">Capabilities:</h6>
                <ul>
                    <li>✅ Check medicine availability in real-time</li>
                    <li>✅ Provide accurate pricing information</li>
                    <li>✅ Generate sales reports for admins</li>
                    <li>✅ Send low stock alerts</li>
                    <li>✅ Answer general pharmacy questions</li>
                    <li>✅ Never provides medical advice (refers to pharmacists)</li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Knowledge Base Management -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="fas fa-database me-2 text-success"></i>Knowledge Base</h5>
                <button class="btn btn-sm btn-success" data-mdb-modal-init data-mdb-target="#addKnowledgeModal">
                    <i class="fas fa-plus me-1"></i>Add
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Category</th>
                                <th>Pattern</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($knowledge as $item): ?>
                                <tr>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $item['category'] === 'stock' ? 'info' : 
                                                 ($item['category'] === 'price' ? 'success' : 
                                                 ($item['category'] === 'report' ? 'warning' : 'secondary')); 
                                        ?>">
                                            <?php echo ucfirst($item['category']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo truncateText(sanitize($item['question_pattern']), 30); ?></td>
                                    <td>
                                        <?php if ($item['is_active']): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editKnowledge(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Knowledge Modal -->
<div class="modal fade" id="addKnowledgeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add Knowledge Base Entry</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <form method="POST" action="chatbot-knowledge-save.php">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select" required>
                                <option value="stock">Stock/Availability</option>
                                <option value="price">Price</option>
                                <option value="general">General</option>
                                <option value="report">Report</option>
                                <option value="greeting">Greeting</option>
                                <option value="help">Help</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Question Pattern</label>
                            <input type="text" name="question_pattern" class="form-control" placeholder="e.g., is {medicine} available" required>
                            <small class="text-muted">Use {variable} for dynamic parts</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Answer Template</label>
                            <textarea name="answer_template" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="requires_api" id="requires_api" value="1">
                                <label class="form-check-label" for="requires_api">Requires Database/API</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Priority</label>
                            <input type="number" name="priority" class="form-control" value="0" min="0" max="100">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editKnowledge(item) {
    // Populate and show edit modal
    console.log('Edit knowledge:', item);
    // Implementation for edit modal
}
</script>

<?php include 'includes/footer.php'; ?>
