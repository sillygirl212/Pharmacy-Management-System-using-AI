<?php
require_once 'config/functions.php';
$page_title = 'FAQ';

$db = getDB();
$faqs = $db->query("SELECT * FROM faq WHERE status = 'active' ORDER BY category, sort_order")->fetchAll();

// Group FAQs by category
$grouped_faqs = [];
foreach ($faqs as $faq) {
    $category = $faq['category'] ?: 'General';
    if (!isset($grouped_faqs[$category])) {
        $grouped_faqs[$category] = [];
    }
    $grouped_faqs[$category][] = $faq;
}

include 'includes/header.php';
?>

<!-- Page Banner -->
<section class="page-banner" style="padding: 60px 0;">
    <div class="container">
        <h1 class="fw-bold mb-2">Frequently Asked Questions</h1>
        <p class="mb-0">Find answers to common questions about our pharmacy services</p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <?php if (empty($faqs)): ?>
            <div class="text-center py-5">
                <i class="fas fa-question-circle fa-4x text-muted mb-4"></i>
                <h3 class="fw-bold">No FAQs Available</h3>
                <p class="text-muted">Check back later for updates.</p>
            </div>
        <?php else: ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <?php foreach ($grouped_faqs as $category => $category_faqs): ?>
                        <div class="mb-5">
                            <h4 class="fw-bold mb-4 text-primary text-uppercase"><?php echo sanitize($category); ?></h4>
                            
                            <div class="accordion faq-accordion" id="faq-<?php echo strtolower(str_replace(' ', '-', $category)); ?>">
                                <?php foreach ($category_faqs as $index => $faq): ?>
                                    <div class="accordion-item mb-2 border rounded-3 overflow-hidden">
                                        <h2 class="accordion-header" id="heading-<?php echo $faq['id']; ?>">
                                            <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?> fw-medium" 
                                                    type="button" 
                                                    data-mdb-collapse-init 
                                                    data-mdb-target="#collapse-<?php echo $faq['id']; ?>"
                                                    aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>">
                                                <?php echo sanitize($faq['question']); ?>
                                            </button>
                                        </h2>
                                        <div id="collapse-<?php echo $faq['id']; ?>" 
                                             class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>"
                                             data-mdb-parent="#faq-<?php echo strtolower(str_replace(' ', '-', $category)); ?>">
                                            <div class="accordion-body text-muted">
                                                <?php echo nl2br(sanitize($faq['answer'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Contact CTA -->
        <div class="row mt-5">
            <div class="col-lg-8 mx-auto">
                <div class="card border-0 shadow-lg rounded-4 text-center p-5" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <h3 class="text-white fw-bold mb-3">Still have questions?</h3>
                    <p class="text-white-75 mb-4">
                        Can't find the answer you're looking for? Please contact our support team.
                    </p>
                    <a href="contact.php" class="btn btn-light btn-lg mx-auto" style="width: fit-content;">
                        <i class="fas fa-envelope me-2"></i>Contact Support
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
