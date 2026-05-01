<?php
require_once 'config/functions.php';
$page_title = 'Home';

// Get featured products
$featured_products = getFeaturedProducts(8);

// Get all categories
$db = getDB();
$categories = $db->query("SELECT * FROM categories WHERE status = 'active' LIMIT 6")->fetchAll();

// Get testimonials
$testimonials = [
    [
        'name' => 'John Smith',
        'role' => 'Regular Customer',
        'image' => 'https://ui-avatars.com/api/?name=John+Smith&background=667eea&color=fff',
        'content' => 'Excellent service and fast delivery! The products are genuine and the prices are very competitive. Highly recommended!'
    ],
    [
        'name' => 'Sarah Johnson',
        'role' => 'Healthcare Professional',
        'image' => 'https://ui-avatars.com/api/?name=Sarah+Johnson&background=764ba2&color=fff',
        'content' => 'As a healthcare professional, I appreciate the quality and authenticity of medicines available here. The prescription verification process is thorough.'
    ],
    [
        'name' => 'Michael Brown',
        'role' => 'Customer',
        'image' => 'https://ui-avatars.com/api/?name=Michael+Brown&background=f093fb&color=fff',
        'content' => 'Great user experience on the website. Easy to find products, and the checkout process is smooth. Will definitely order again!'
    ]
];

// Get FAQ
$faqs = $db->query("SELECT * FROM faq WHERE status = 'active' ORDER BY sort_order LIMIT 5")->fetchAll();

include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="hero-title">Your Health,<br>Our Priority</h1>
                <p class="hero-subtitle">
                    Discover a wide range of quality medicines, health supplements, and medical devices. 
                    Fast delivery, genuine products, and trusted by thousands of customers.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="products.php" class="btn btn-light btn-lg btn-hero">
                        <i class="fas fa-shopping-bag me-2"></i>Explore Products
                    </a>
                    <a href="categories.php" class="btn btn-outline-light btn-lg btn-hero">
                        <i class="fas fa-th-large me-2"></i>View Categories
                    </a>
                </div>
                
                <!-- Stats -->
                <div class="row mt-5">
                    <div class="col-4 text-center">
                        <h3 class="fw-bold">5000+</h3>
                        <p class="mb-0 opacity-75">Products</p>
                    </div>
                    <div class="col-4 text-center border-start">
                        <h3 class="fw-bold">10000+</h3>
                        <p class="mb-0 opacity-75">Happy Customers</p>
                    </div>
                    <div class="col-4 text-center border-start">
                        <h3 class="fw-bold">24/7</h3>
                        <p class="mb-0 opacity-75">Support</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 d-none d-lg-block">
                <img src="https://img.freepik.com/free-vector/medical-healthcare-protection-shield-with-cross-sign_1017-23042.jpg?w=800" 
                     alt="Pharmacy" class="img-fluid rounded-4 shadow-lg" style="transform: perspective(1000px) rotateY(-15deg);">
            </div>
        </div>
    </div>
</section>

<!-- Categories Section -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h6 class="text-primary text-uppercase fw-bold mb-2">Shop by Category</h6>
            <h2 class="fw-bold">Browse Our Categories</h2>
            <p class="text-muted">Find exactly what you need from our wide range of healthcare categories</p>
        </div>
        
        <div class="row g-4">
            <?php foreach ($categories as $category): ?>
                <div class="col-lg-4 col-md-6">
                    <a href="products.php?category=<?php echo $category['slug']; ?>" class="text-decoration-none">
                        <div class="category-card shadow">
                            <img src="<?php echo $category['image'] ? $category['image'] : 'https://via.placeholder.com/400x200?text=' . urlencode($category['name']); ?>" 
                                 alt="<?php echo sanitize($category['name']); ?>">
                            <div class="category-overlay">
                                <h5 class="mb-1"><?php echo sanitize($category['name']); ?></h5>
                                <p class="mb-0 small text-white-50">
                                    <?php echo truncateText(sanitize($category['description']), 50); ?>
                                </p>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="categories.php" class="btn btn-outline-primary">
                View All Categories <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</section>

<!-- Featured Products Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h6 class="text-primary text-uppercase fw-bold mb-2">Popular Products</h6>
                <h2 class="fw-bold mb-0">Featured Products</h2>
            </div>
            <a href="products.php?featured=1" class="btn btn-outline-primary d-none d-md-block">
                View All <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
        
        <div class="row g-4">
            <?php foreach ($featured_products as $product): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="card product-card h-100">
                        <?php if ($product['featured']): ?>
                            <span class="badge badge-primary badge-featured">Featured</span>
                        <?php endif; ?>
                        <?php if ($product['prescription_required']): ?>
                            <span class="badge badge-danger badge-prescription"><i class="fas fa-prescription me-1"></i>Rx</span>
                        <?php endif; ?>
                        
                        <div class="bg-image hover-zoom ripple" data-mdb-ripple-color="light">
                            <img src="<?php echo getProductImage($product['id']); ?>" 
                                 class="product-image w-100" 
                                 alt="<?php echo sanitize($product['name']); ?>">
                            <a href="product.php?slug=<?php echo $product['slug']; ?>">
                                <div class="mask">
                                    <div class="d-flex justify-content-start align-items-end h-100 p-3">
                                        <span class="btn btn-primary btn-sm">View Details</span>
                                    </div>
                                </div>
                            </a>
                        </div>
                        
                        <div class="card-body d-flex flex-column">
                            <h6 class="text-muted mb-1"><?php echo sanitize($product['category_name'] ?? 'Uncategorized'); ?></h6>
                            <h5 class="card-title mb-2">
                                <a href="product.php?slug=<?php echo $product['slug']; ?>" class="text-dark text-decoration-none">
                                    <?php echo sanitize($product['name']); ?>
                                </a>
                            </h5>
                            <p class="text-muted small mb-3 flex-grow-1">
                                <?php echo truncateText(sanitize($product['short_description'] ?? $product['description']), 80); ?>
                            </p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <?php if ($product['sale_price']): ?>
                                        <span class="price-tag"><?php echo formatPrice($product['sale_price']); ?></span>
                                        <span class="old-price ms-2"><?php echo formatPrice($product['price']); ?></span>
                                    <?php else: ?>
                                        <span class="price-tag"><?php echo formatPrice($product['price']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <button class="btn btn-primary btn-sm add-to-cart" 
                                        data-product-id="<?php echo $product['id']; ?>"
                                        <?php echo $product['prescription_required'] ? 'disabled' : ''; ?>>
                                    <i class="fas fa-cart-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-4 d-md-none">
            <a href="products.php" class="btn btn-outline-primary">View All Products <i class="fas fa-arrow-right ms-2"></i></a>
        </div>
    </div>
</section>

<!-- Why Choose Us Section -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h6 class="text-primary text-uppercase fw-bold mb-2">Why Choose Us</h6>
            <h2 class="fw-bold">The Trusted Pharmacy Partner</h2>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm text-center p-4">
                    <div class="mx-auto mb-3">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-4" style="width: 80px; height: 80px;">
                            <i class="fas fa-certificate fa-2x text-primary"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold">Genuine Products</h5>
                    <p class="text-muted mb-0">All our products are sourced directly from authorized manufacturers and distributors.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm text-center p-4">
                    <div class="mx-auto mb-3">
                        <div class="rounded-circle bg-success bg-opacity-10 p-4" style="width: 80px; height: 80px;">
                            <i class="fas fa-shipping-fast fa-2x text-success"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold">Fast Delivery</h5>
                    <p class="text-muted mb-0">Get your medicines delivered to your doorstep within 24-48 hours.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm text-center p-4">
                    <div class="mx-auto mb-3">
                        <div class="rounded-circle bg-info bg-opacity-10 p-4" style="width: 80px; height: 80px;">
                            <i class="fas fa-user-md fa-2x text-info"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold">Expert Support</h5>
                    <p class="text-muted mb-0">Our team of licensed pharmacists is available 24/7 to answer your queries.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h6 class="text-primary text-uppercase fw-bold mb-2">Testimonials</h6>
            <h2 class="fw-bold">What Our Customers Say</h2>
        </div>
        
        <div class="row g-4">
            <?php foreach ($testimonials as $testimonial): ?>
                <div class="col-md-4">
                    <div class="testimonial-card h-100">
                        <div class="d-flex align-items-center mb-3">
                            <img src="<?php echo $testimonial['image']; ?>" 
                                 alt="<?php echo $testimonial['name']; ?>" 
                                 class="rounded-circle me-3" width="60" height="60">
                            <div>
                                <h6 class="mb-0 fw-bold"><?php echo $testimonial['name']; ?></h6>
                                <small class="text-muted"><?php echo $testimonial['role']; ?></small>
                            </div>
                        </div>
                        <div class="mb-3">
                            <?php for ($i = 0; $i < 5; $i++): ?>
                                <i class="fas fa-star text-warning"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="text-muted mb-0">"<?php echo $testimonial['content']; ?>"</p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<?php if (!empty($faqs)): ?>
<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center mb-5">
                    <h6 class="text-primary text-uppercase fw-bold mb-2">FAQ</h6>
                    <h2 class="fw-bold">Frequently Asked Questions</h2>
                </div>
                
                <div class="accordion faq-accordion" id="faqAccordion">
                    <?php foreach ($faqs as $index => $faq): ?>
                        <div class="accordion-item mb-2 border rounded-3 overflow-hidden">
                            <h2 class="accordion-header" id="heading<?php echo $faq['id']; ?>">
                                <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?> fw-medium" 
                                        type="button" 
                                        data-mdb-collapse-init 
                                        data-mdb-target="#collapse<?php echo $faq['id']; ?>"
                                        aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>">
                                    <?php echo sanitize($faq['question']); ?>
                                </button>
                            </h2>
                            <div id="collapse<?php echo $faq['id']; ?>" 
                                 class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>"
                                 data-mdb-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    <?php echo nl2br(sanitize($faq['answer'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="text-center mt-4">
                    <a href="faq.php" class="btn btn-outline-primary">View All FAQs <i class="fas fa-arrow-right ms-2"></i></a>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- CTA Section -->
<section class="py-5">
    <div class="container">
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="card-body p-5 text-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h2 class="text-white fw-bold mb-3">Ready to Get Started?</h2>
                <p class="text-white-75 mb-4 lead">
                    Browse our extensive collection of healthcare products and experience the convenience of online pharmacy shopping.
                </p>
                <div class="d-flex justify-content-center gap-3">
                    <?php if (!isLoggedIn()): ?>
                        <a href="register.php" class="btn btn-light btn-lg px-5">Create Account</a>
                    <?php endif; ?>
                    <a href="products.php" class="btn btn-outline-light btn-lg px-5">Shop Now</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
