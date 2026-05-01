        </div>
    </main>
    
    <!-- MDBootstrap JS -->
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/7.1.0/mdb.umd.min.js"></script>
    
    <script>
        // Initialize MDBootstrap components
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle for mobile
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.admin-sidebar');
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }
            
            // Initialize dropdowns
            document.querySelectorAll('[data-mdb-dropdown-init]').forEach(el => {
                new mdb.Dropdown(el);
            });
            
            // Initialize collapse
            document.querySelectorAll('[data-mdb-collapse-init]').forEach(el => {
                new mdb.Collapse(el);
            });
            
            // Initialize tooltips
            document.querySelectorAll('[data-mdb-toggle="tooltip"]').forEach(el => {
                new mdb.Tooltip(el);
            });
        });
        
        // Confirm delete
        function confirmDelete(message = 'Are you sure you want to delete this item?') {
            return confirm(message);
        }
    </script>
</body>
</html>
