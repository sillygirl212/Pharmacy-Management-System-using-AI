    <!-- Footer -->
    <footer class="footer mt-5">
        <div class="container">
            <div class="row">
                <!-- About -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5 class="mb-3">
                        <i class="fas fa-pharmacy me-2"></i>
                        <?php echo getSetting('site_name', 'Pharmacy Management System'); ?>
                    </h5>
                    <p class="text-white-50">
                        Your trusted online pharmacy for all healthcare needs. We provide quality medicines, health supplements, and medical devices with fast delivery.
                    </p>
                    <div class="d-flex gap-3 mt-3">
                        <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="text-uppercase mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php" class="footer-link">Home</a></li>
                        <li class="mb-2"><a href="products.php" class="footer-link">Products</a></li>
                        <li class="mb-2"><a href="categories.php" class="footer-link">Categories</a></li>
                        <li class="mb-2"><a href="about.php" class="footer-link">About Us</a></li>
                    </ul>
                </div>
                
                <!-- Support -->
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="text-uppercase mb-3">Support</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="faq.php" class="footer-link">FAQ</a></li>
                        <li class="mb-2"><a href="contact.php" class="footer-link">Contact Us</a></li>
                        <li class="mb-2"><a href="terms.php" class="footer-link">Terms of Service</a></li>
                        <li class="mb-2"><a href="privacy.php" class="footer-link">Privacy Policy</a></li>
                    </ul>
                </div>
                
                <!-- Contact Info -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <h6 class="text-uppercase mb-3">Contact Info</h6>
                    <ul class="list-unstyled text-white-50">
                        <li class="mb-3">
                            <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                            <?php echo getSetting('site_address', '123 Pharmacy Street, Medical City'); ?>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-phone me-2 text-primary"></i>
                            <?php echo getSetting('site_phone', '+1 234 567 8900'); ?>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-envelope me-2 text-primary"></i>
                            <?php echo getSetting('site_email', 'support@pharmacy.com'); ?>
                        </li>
                    </ul>
                    
                    <!-- Newsletter -->
                    <h6 class="text-uppercase mb-2 mt-4">Newsletter</h6>
                    <form class="d-flex gap-2" action="newsletter.php" method="POST">
                        <input type="email" class="form-control form-control-sm" placeholder="Your email" required>
                        <button type="submit" class="btn btn-primary btn-sm">Subscribe</button>
                    </form>
                </div>
            </div>
            
            <hr class="my-4" style="border-color: rgba(255,255,255,0.1);">
            
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0 text-white-50">
                        &copy; <?php echo date('Y'); ?> <?php echo getSetting('site_name', 'Pharmacy Management System'); ?>. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <img src="https://via.placeholder.com/50x30?text=VISA" alt="Visa" class="me-2" style="opacity: 0.7;">
                    <img src="https://via.placeholder.com/50x30?text=MC" alt="Mastercard" class="me-2" style="opacity: 0.7;">
                    <img src="https://via.placeholder.com/50x30?text=PP" alt="PayPal" style="opacity: 0.7;">
                </div>
            </div>
        </div>
    </footer>
    
    <!-- MDBootstrap JS -->
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/7.1.0/mdb.umd.min.js"></script>
    
    <!-- Custom Scripts -->
    <script>
        // Initialize MDBootstrap components
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize dropdowns
            const dropdowns = document.querySelectorAll('[data-mdb-dropdown-init]');
            dropdowns.forEach(dropdown => {
                new mdb.Dropdown(dropdown);
            });
            
            // Initialize tooltips
            const tooltips = document.querySelectorAll('[data-mdb-toggle="tooltip"]');
            tooltips.forEach(tooltip => {
                new mdb.Tooltip(tooltip);
            });
            
            // Initialize collapse
            const collapses = document.querySelectorAll('[data-mdb-collapse-init]');
            collapses.forEach(collapse => {
                new mdb.Collapse(collapse);
            });
            
            // Add to cart AJAX
            document.querySelectorAll('.add-to-cart').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const productId = this.dataset.productId;
                    const quantity = this.dataset.quantity || 1;
                    
                    fetch('ajax/add-to-cart.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `product_id=${productId}&quantity=${quantity}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update cart badge
                            const badge = document.querySelector('.cart-badge');
                            if (badge) {
                                badge.textContent = data.cart_count;
                                badge.style.display = 'flex';
                            } else {
                                location.reload();
                            }
                            
                            // Show toast
                            showToast('Product added to cart!', 'success');
                        } else {
                            showToast(data.message || 'Error adding to cart', 'error');
                        }
                    })
                    .catch(error => {
                        showToast('Error adding to cart', 'error');
                    });
                });
            });
        });
        
        // Toast notification
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            toast.style.cssText = 'top: 100px; right: 20px; z-index: 9999; min-width: 300px;';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
        
        // Quantity controls
        function updateQuantity(input, change) {
            const newValue = parseInt(input.value) + change;
            if (newValue >= parseInt(input.min) && newValue <= parseInt(input.max)) {
                input.value = newValue;
            }
        }
    </script>
    <?php include 'includes/chatbot-widget.php'; ?>
</body>
</html>
