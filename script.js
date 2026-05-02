// API Base URL
const API_URL = 'api/';

// DOM Elements
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.querySelector('.sidebar');
const navLinks = document.querySelectorAll('.sidebar-nav a');
const pages = document.querySelectorAll('.page');
const pageTitle = document.querySelector('.page-title');

// Modal Elements
const medicineModal = document.getElementById('medicineModal');
const addMedicineBtn = document.getElementById('addMedicineBtn');
const closeModalBtns = document.querySelectorAll('.close-modal');
const medicineForm = document.getElementById('medicineForm');

// POS Elements - Cart removed from Sales page
// const cartItems = document.getElementById('cartItems');
// const subtotalEl = document.getElementById('subtotal');
// const taxEl = document.getElementById('tax');
// const totalEl = document.getElementById('total');
// const clearCartBtn = document.getElementById('clearCart');
// const checkoutBtn = document.getElementById('checkoutBtn');

// Cart State - Disabled as Current Order section removed
// let cart = [];
let medicinesData = [];
let categoriesData = [];
let customersData = [];

// Check authentication on load
checkAuthentication();

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    initNavigation();
    initModal();
    initHomePage();
    initSearch();
    initLogout();
    loadDashboardData();
    loadMedicines();
    loadCategories();
    loadCustomers();
    loadSuppliers();
    displayUserInfo();
    
    // Update notification badge on load
    updateNotificationBadge();
});

// Check if user is authenticated
function checkAuthentication() {
    const user = sessionStorage.getItem('user');
    if (!user) {
        // Not logged in, redirect to login
        if (!window.location.href.includes('login.html')) {
            window.location.href = 'login.html';
        }
    }
}

// Display user info in sidebar and header
function displayUserInfo() {
    const userStr = sessionStorage.getItem('user');
    if (!userStr) return;
    
    const user = JSON.parse(userStr);
    
    // Update sidebar
    const sidebarName = document.getElementById('userName');
    const sidebarRole = document.getElementById('userRole');
    const sidebarAvatar = document.getElementById('userAvatar');
    
    if (sidebarName) sidebarName.textContent = user.full_name || user.username;
    if (sidebarRole) sidebarRole.textContent = user.role;
    if (sidebarAvatar) {
        sidebarAvatar.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(user.full_name || user.username)}&background=0ea5e9&color=fff`;
    }
    
    // Update header
    const headerName = document.getElementById('headerUserName');
    const headerAvatar = document.getElementById('headerAvatar');
    
    if (headerName) headerName.textContent = user.full_name || user.username;
    if (headerAvatar) {
        headerAvatar.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(user.full_name || user.username)}&background=0ea5e9&color=fff`;
    }
    
    // Check user role and apply access control
    applyRoleBasedAccess(user.role);
    
    // Update notification badge count
    updateNotificationBadge();
}

// Apply role-based access control
function applyRoleBasedAccess(role) {
    // Add is-admin class to body if user is admin
    if (role === 'admin') {
        document.body.classList.add('is-admin');
    } else {
        document.body.classList.remove('is-admin');
        
        // If not admin and trying to access admin-only page, redirect to customers
        const adminOnlyPages = ['dashboard', 'suppliers', 'inventory', 'reports'];
        const currentHash = window.location.hash.replace('#', '') || 'home';
        
        if (adminOnlyPages.includes(currentHash)) {
            window.location.hash = 'customers';
            showNotification('You do not have permission to access this page', 'error');
        }
    }
}

// Initialize logout button
function initLogout() {
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            
            try {
                await fetch(API_URL + 'auth.php?action=logout');
            } catch (error) {
                console.error('Logout error:', error);
            } finally {
                // Clear session and redirect
                sessionStorage.removeItem('user');
                window.location.href = 'login.html';
            }
        });
    }
}

// Navigation
function initNavigation() {
    // Mobile menu toggle
    if (menuToggle) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
    }

    // Nav link click handlers
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const pageId = link.getAttribute('data-page');
            if (pageId) {
                showPage(pageId);
                updateActiveNav(link);
                
                // Close mobile menu
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('active');
                }
            }
        });
    });

    // Sidebar footer links
    document.querySelectorAll('.sidebar-footer a[data-page]').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const pageId = link.getAttribute('data-page');
            if (pageId) {
                showPage(pageId);
                updateActiveNav(null);
            }
        });
    });
}

function showPage(pageId) {
    // Check access control for admin-only pages
    const userStr = sessionStorage.getItem('user');
    if (userStr) {
        const user = JSON.parse(userStr);
        const adminOnlyPages = ['dashboard', 'suppliers', 'inventory', 'reports'];
        
        if (adminOnlyPages.includes(pageId) && user.role !== 'admin') {
            showNotification('You do not have permission to access this page', 'error');
            window.location.hash = 'customers';
            pageId = 'customers';
        }
    }
    
    // Hide all pages
    pages.forEach(page => {
        page.classList.remove('active');
    });

    // Show target page
    const targetPage = document.getElementById(`${pageId}-page`);
    if (targetPage) {
        targetPage.classList.add('active');
    }

    // Update page title
    const titles = {
        'dashboard': 'Dashboard',
        'home': 'Home',
        'customers': 'Customers',
        'suppliers': 'Suppliers',
        'inventory': 'Inventory',
        'reports': 'Reports',
        'settings': 'Settings'
    };
    pageTitle.textContent = titles[pageId] || 'Home';
    
    // Update notification badge when showing settings page
    if (pageId === 'settings') {
        updateNotificationBadge();
    }

    // Reinitialize charts if on dashboard
    if (pageId === 'dashboard') {
        setTimeout(initCharts, 100);
    }
    
    // Check if we need to open add medicine modal (from Home page)
    if (pageId === 'home') {
        const openAddMedicine = sessionStorage.getItem('openAddMedicine');
        if (openAddMedicine === 'true') {
            sessionStorage.removeItem('openAddMedicine');
            setTimeout(() => {
                const addMedicineBtn = document.getElementById('addMedicineBtn');
                if (addMedicineBtn) {
                    addMedicineBtn.click();
                }
            }, 300);
        }
        
        // Check if we need to open edit medicine modal
        const editMedicineId = sessionStorage.getItem('editMedicineId');
        if (editMedicineId) {
            sessionStorage.removeItem('editMedicineId');
            setTimeout(() => {
                editMedicine(editMedicineId);
            }, 300);
        }
    }
    
    // Load products for Home page (main product view)
    if (pageId === 'home') {
        loadHomeProducts();
    }
}

function updateActiveNav(activeLink) {
    navLinks.forEach(link => {
        link.parentElement.classList.remove('active');
    });
    if (activeLink) {
        activeLink.parentElement.classList.add('active');
    }
}

// ==================== API FUNCTIONS ====================

// Generic API request function with timeout
async function apiRequest(endpoint, method = 'GET', data = null, timeout = 10000) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        }
    };
    
    if (data && (method === 'POST' || method === 'PUT')) {
        options.body = JSON.stringify(data);
    }
    
    // Create an AbortController for timeout
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), timeout);
    options.signal = controller.signal;
    
    try {
        const response = await fetch(API_URL + endpoint, options);
        clearTimeout(timeoutId);
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message || 'API request failed');
        }
        
        return result;
    } catch (error) {
        clearTimeout(timeoutId);
        console.error('API Error:', error);
        if (error.name === 'AbortError') {
            throw new Error('Request timed out. Please check your connection.');
        }
        showNotification(error.message, 'error');
        throw error;
    }
}

// Load Dashboard Statistics
async function loadDashboardData() {
    try {
        const result = await apiRequest('dashboard.php');
        const stats = result.data;
        
        // Update stat cards
        updateStatCard(0, '$' + stats.today_sales.toLocaleString(), 
            (stats.sales_change_percent >= 0 ? '+' : '') + stats.sales_change_percent + '% from yesterday',
            stats.sales_change_percent >= 0);
        updateStatCard(1, stats.total_medicines.toLocaleString(), 'Total medicines in stock');
        updateStatCard(2, (stats.low_stock_count + stats.out_of_stock_count).toString(), 
            stats.low_stock_count + ' low, ' + stats.out_of_stock_count + ' out of stock', false);
        updateStatCard(3, stats.total_customers.toLocaleString(), 'Registered customers');
        
        // Update recent sales table
        updateRecentSalesTable(stats.recent_sales);
        
        // Update low stock table
        updateLowStockTable(stats.low_stock_medicines);
        
        // Update expiring table
        updateExpiringTable(stats.expiring_medicines);
        
        // Update chart
        updateChart(stats.chart_data);
        
    } catch (error) {
        console.error('Failed to load dashboard data:', error);
    }
}

function updateStatCard(index, value, change, positive = true) {
    const cards = document.querySelectorAll('.stat-card');
    if (cards[index]) {
        const valueEl = cards[index].querySelector('.stat-value');
        const changeEl = cards[index].querySelector('.stat-change');
        
        if (valueEl) valueEl.textContent = value;
        if (changeEl) {
            changeEl.textContent = change;
            changeEl.className = 'stat-change ' + (positive ? 'positive' : 'negative');
        }
    }
}

function updateRecentSalesTable(sales) {
    const tbody = document.querySelector('#dashboard-page .data-table tbody');
    if (!tbody || !sales) return;
    
    tbody.innerHTML = sales.map(sale => `
        <tr>
            <td>#${sale.invoice_number}</td>
            <td>${sale.customer_name || 'Walk-in'}</td>
            <td>$${parseFloat(sale.total_amount).toFixed(2)}</td>
            <td>${new Date(sale.sale_date).toLocaleDateString()}</td>
            <td><span class="badge-status ${sale.payment_status === 'completed' ? 'success' : 'pending'}">${sale.payment_status}</span></td>
        </tr>
    `).join('');
}

function updateLowStockTable(medicines) {
    const tbody = document.querySelectorAll('#dashboard-page .data-table')[1]?.querySelector('tbody');
    if (!tbody || !medicines) return;
    
    tbody.innerHTML = medicines.map(med => `
        <tr>
            <td>${med.name}</td>
            <td>${med.category_name || 'Uncategorized'}</td>
            <td><span class="stock-low">${med.stock_quantity} units</span></td>
            <td><button class="btn btn-sm btn-primary" onclick="restockMedicine(${med.id})">Restock</button></td>
        </tr>
    `).join('');
}

function updateExpiringTable(medicines) {
    const tbody = document.querySelectorAll('#dashboard-page .data-table')[2]?.querySelector('tbody');
    if (!tbody || !medicines) return;
    
    tbody.innerHTML = medicines.map(med => `
        <tr>
            <td>${med.name}</td>
            <td>${med.batch_number || 'N/A'}</td>
            <td>${med.expiry_date}</td>
            <td><span class="${med.days_left <= 30 ? 'expiry-danger' : 'expiry-warning'}">${med.days_left} days</span></td>
        </tr>
    `).join('');
}

function updateChart(chartData) {
    if (!chartData) return;
    
    const ctx = document.getElementById('salesChart');
    if (!ctx) return;
    
    if (window.salesChartInstance) {
        window.salesChartInstance.destroy();
    }
    
    window.salesChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Sales ($)',
                data: chartData.values,
                borderColor: '#0ea5e9',
                backgroundColor: 'rgba(14, 165, 233, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#0ea5e9',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: {
                        color: '#64748b',
                        callback: function(value) { return '$' + value; }
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#64748b' }
                }
            }
        }
    });
}

// Load Medicines
async function loadMedicines() {
    try {
        const result = await apiRequest('medicines.php');
        medicinesData = result.data || [];
        renderMedicinesTable(medicinesData);
        // Home page products are loaded separately by loadHomeProducts()
    } catch (error) {
        console.error('Failed to load medicines:', error);
    }
}

function renderMedicinesTable(medicines) {
    const tbody = document.querySelector('#medicines-page .data-table tbody');
    if (!tbody) return;
    
    tbody.innerHTML = medicines.map(med => `
        <tr>
            <td><input type="checkbox"></td>
            <td>
                <div class="medicine-info">
                    <div class="medicine-icon"><i class="fas fa-pills"></i></div>
                    <div>
                        <p class="medicine-name">${med.name}</p>
                        <span class="medicine-code">${med.medicine_code}</span>
                    </div>
                </div>
            </td>
            <td>${med.category_name || 'Uncategorized'}</td>
            <td>${med.generic_name || '-'}</td>
            <td><span class="stock-badge ${getStockClass(med.stock_quantity, med.reorder_level)}">${med.stock_quantity} units</span></td>
            <td>$${parseFloat(med.unit_price).toFixed(2)}</td>
            <td>${med.expiry_date || '-'}</td>
            <td>
                <button class="btn-icon" title="Edit" onclick="editMedicine(${med.id})"><i class="fas fa-edit"></i></button>
                <button class="btn-icon" title="Delete" onclick="deleteMedicine(${med.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('');
    
    // Update pagination info
    const paginationSpan = document.querySelector('.pagination span');
    if (paginationSpan) {
        paginationSpan.textContent = `Showing 1-${medicines.length} of ${medicines.length} medicines`;
    }
}

function getStockClass(stock, reorderLevel) {
    if (stock === 0) return 'low';
    if (stock <= reorderLevel) return 'medium';
    return 'high';
}


// Load Categories
async function loadCategories() {
    try {
        const result = await apiRequest('categories.php');
        categoriesData = result.data || [];
        updateCategorySelects(categoriesData);
    } catch (error) {
        console.error('Failed to load categories:', error);
    }
}

function updateCategorySelects(categories) {
    const selects = document.querySelectorAll('select[name="category_id"]');
    
    selects.forEach(select => {
        // Keep only the first "Select Category" option
        const placeholder = select.querySelector('option:first-child');
        const placeholderText = placeholder ? placeholder.textContent : 'Select Category';
        
        // Build new options with proper IDs from database
        let optionsHtml = `<option value="">${placeholderText}</option>`;
        
        if (categories.length === 0) {
            optionsHtml += `<option value="" disabled>No categories found</option>`;
        } else {
            categories.forEach(c => {
                optionsHtml += `<option value="${c.id}">${c.name}</option>`;
            });
        }
        
        select.innerHTML = optionsHtml;
    });
}

// Load Customers
async function loadCustomers() {
    try {
        const result = await apiRequest('customers.php');
        customersData = result.data || [];
        renderCustomersTable(customersData);
        updateCustomerSelect(customersData);
    } catch (error) {
        console.error('Failed to load customers:', error);
    }
}

function renderCustomersTable(customers) {
    const tbody = document.querySelector('#customers-page .data-table tbody');
    if (!tbody) return;
    
    tbody.innerHTML = customers.map(cust => `
        <tr>
            <td><input type="checkbox"></td>
            <td>
                <div class="customer-info">
                    <img src="https://ui-avatars.com/api/?name=${cust.first_name}+${cust.last_name}&background=0ea5e9&color=fff" alt="${cust.first_name}">
                    <div>
                        <p class="customer-name">${cust.first_name} ${cust.last_name}</p>
                        <span class="customer-id">${cust.customer_code}</span>
                    </div>
                </div>
            </td>
            <td>${cust.email || '-'}</td>
            <td>${cust.phone || '-'}</td>
            <td>$${parseFloat(cust.total_purchases || 0).toFixed(2)}</td>
            <td><span class="badge-status ${cust.is_active ? 'success' : 'danger'}">${cust.is_active ? 'Active' : 'Inactive'}</span></td>
            <td>
                <button class="btn-icon" title="Edit" onclick="editCustomer(${cust.id})"><i class="fas fa-edit"></i></button>
                <button class="btn-icon" title="Delete" onclick="deleteCustomer(${cust.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('');
}

function updateCustomerSelect(customers) {
    const select = document.getElementById('customerSelect');
    if (!select) return;
    
    const options = customers.map(c => `<option value="${c.id}">${c.first_name} ${c.last_name}</option>`).join('');
    select.innerHTML = '<option value="">Walk-in Customer</option>' + options;
}

// Load Suppliers
async function loadSuppliers() {
    try {
        const result = await apiRequest('suppliers.php');
        const suppliers = result.data || [];
        console.log('Loaded suppliers:', suppliers.length, suppliers);
        renderSuppliersTable(suppliers);
        updateSupplierSelects(suppliers);
    } catch (error) {
        console.error('Failed to load suppliers:', error);
    }
}

async function refreshCategoriesForModal() {
    try {
        const result = await apiRequest('categories.php');
        const categories = result.data || [];
        console.log('Refreshing categories for modal:', categories.length);
        updateCategorySelects(categories);
    } catch (error) {
        console.error('Failed to refresh categories:', error);
    }
}

async function refreshSuppliersForModal() {
    try {
        const result = await apiRequest('suppliers.php');
        const suppliers = result.data || [];
        console.log('Refreshing suppliers for modal:', suppliers.length);
        updateSupplierSelects(suppliers);
    } catch (error) {
        console.error('Failed to refresh suppliers:', error);
    }
}

function updateSupplierSelects(suppliers) {
    const selects = document.querySelectorAll('select[name="supplier_id"]');
    
    selects.forEach(select => {
        // Keep only the first "Select Supplier" option
        const placeholder = select.querySelector('option:first-child');
        const placeholderText = placeholder ? placeholder.textContent : 'Select Supplier';
        
        // Build new options with proper IDs from database
        let optionsHtml = `<option value="">${placeholderText}</option>`;
        
        if (suppliers.length === 0) {
            optionsHtml += `<option value="" disabled>No suppliers found - Add suppliers first</option>`;
        } else {
            suppliers.forEach(s => {
                optionsHtml += `<option value="${s.id}">${s.name}</option>`;
            });
        }
        
        select.innerHTML = optionsHtml;
    });
}

function renderSuppliersTable(suppliers) {
    const tbody = document.querySelector('#suppliers-page .data-table tbody');
    if (!tbody) return;
    
    tbody.innerHTML = suppliers.map(sup => `
        <tr>
            <td><input type="checkbox"></td>
            <td>
                <div class="supplier-info">
                    <div class="supplier-icon"><i class="fas fa-building"></i></div>
                    <div>
                        <p class="supplier-name">${sup.name}</p>
                        <span class="supplier-id">${sup.supplier_code}</span>
                    </div>
                </div>
            </td>
            <td>${sup.contact_person || '-'}</td>
            <td>${sup.email || '-'}</td>
            <td>${sup.phone || '-'}</td>
            <td><span class="badge-status ${sup.status === 'active' ? 'success' : sup.status === 'pending' ? 'warning' : 'danger'}">${sup.status}</span></td>
            <td>
                <button class="btn-icon" title="Edit" onclick="editSupplier(${sup.id})"><i class="fas fa-edit"></i></button>
                <button class="btn-icon" title="Delete" onclick="deleteSupplier(${sup.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('');
}

// CRUD Operations
async function editMedicine(id) {
    try {
        // Fetch medicine data
        const result = await apiRequest(`medicines.php?id=${id}`, 'GET');
        if (result.success) {
            const medicine = result.data;
            
            // Populate form fields
            document.getElementById('medicineCode').value = medicine.medicine_code || '';
            document.getElementById('medicineName').value = medicine.name || '';
            document.getElementById('genericName').value = medicine.generic_name || '';
            document.getElementById('category').value = medicine.category_id || '';
            document.getElementById('unitPrice').value = medicine.unit_price || '';
            document.getElementById('stockQuantity').value = medicine.stock_quantity || '';
            document.getElementById('reorderLevel').value = medicine.reorder_level || '';
            document.getElementById('unit').value = medicine.unit || '';
            document.getElementById('expiryDate').value = medicine.expiry_date || '';
            document.getElementById('batchNumber').value = medicine.batch_number || '';
            document.getElementById('barcode').value = medicine.barcode || '';
            document.getElementById('description').value = medicine.description || '';
            
            // Store ID for update
            medicineForm.dataset.medicineId = id;
            
            // Change modal title
            document.querySelector('#medicineModal .modal-header h3').textContent = 'Edit Medicine';
            
            // Open modal
            openModal(medicineModal);
            showNotification('Loading medicine details...', 'info');
        }
    } catch (error) {
        showNotification('Failed to load medicine details', 'error');
    }
}

async function deleteMedicine(id) {
    if (!confirm('Are you sure you want to delete this medicine?')) return;
    
    try {
        await apiRequest('medicines.php?id=' + id, 'DELETE');
        showNotification('Medicine deleted successfully!', 'success');
        loadMedicines();
    } catch (error) {
        console.error('Failed to delete medicine:', error);
    }
}

async function editCustomer(id) {
    showNotification('Edit functionality coming soon!', 'info');
}

async function deleteCustomer(id) {
    if (!confirm('Are you sure you want to delete this customer?')) return;
    
    try {
        await apiRequest('customers.php?id=' + id, 'DELETE');
        showNotification('Customer deleted successfully!', 'success');
        loadCustomers();
    } catch (error) {
        console.error('Failed to delete customer:', error);
    }
}

async function editSupplier(id) {
    showNotification('Edit functionality coming soon!', 'info');
}

async function deleteSupplier(id) {
    if (!confirm('Are you sure you want to delete this supplier?')) return;
    
    try {
        await apiRequest('suppliers.php?id=' + id, 'DELETE');
        showNotification('Supplier deleted successfully!', 'success');
        loadSuppliers();
    } catch (error) {
        console.error('Failed to delete supplier:', error);
    }
}

async function restockMedicine(id) {
    showNotification('Restock order initiated for medicine #' + id, 'success');
}

// Modal Functions
function initModal() {
    // Add Medicine button
    if (addMedicineBtn) {
        addMedicineBtn.addEventListener('click', async () => {
            // Refresh categories and suppliers before opening modal
            await Promise.all([
                refreshCategoriesForModal(),
                refreshSuppliersForModal()
            ]);
            openModal(medicineModal);
        });
    }

    // Close modal buttons
    closeModalBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            closeAllModals();
        });
    });

    // Image upload preview
    const imageInput = document.getElementById('medicineImage');
    const imagePreview = document.getElementById('imagePreview');
    const uploadPlaceholder = document.getElementById('uploadPlaceholder');
    const removeImageBtn = document.getElementById('removeImage');
    const imageUploadBox = document.getElementById('imageUploadBox');
    
    if (imageInput) {
        imageInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                // Validate file size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    showNotification('Image too large. Maximum 5MB allowed.', 'error');
                    imageInput.value = '';
                    return;
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = (e) => {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';
                    uploadPlaceholder.style.display = 'none';
                    removeImageBtn.style.display = 'flex';
                    imageUploadBox.classList.add('has-image');
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    if (removeImageBtn) {
        removeImageBtn.addEventListener('click', () => {
            imageInput.value = '';
            imagePreview.src = '';
            imagePreview.style.display = 'none';
            uploadPlaceholder.style.display = 'block';
            removeImageBtn.style.display = 'none';
            imageUploadBox.classList.remove('has-image');
        });
    }
    
    // Form submission
    if (medicineForm) {
        medicineForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // Get form data using FormData API
            const form = new FormData(medicineForm);
            
            // Debug: Log all form values
            console.log('Form Data:');
            for (let [key, value] of form.entries()) {
                console.log(`${key}: ${value}`);
            }
            
            // Get all values first
            const nameVal = form.get('name')?.toString().trim();
            const genericNameVal = form.get('generic_name')?.toString().trim() || '';
            const categoryIdVal = form.get('category_id')?.toString().trim();
            const supplierIdVal = form.get('supplier_id')?.toString().trim() || '';
            const descriptionVal = form.get('description')?.toString().trim() || '';
            const unitPriceVal = parseFloat(form.get('unit_price'));
            const stockQtyVal = parseInt(form.get('stock_quantity'));
            const expiryDateVal = form.get('expiry_date')?.toString().trim();
            const batchNumberVal = form.get('batch_number')?.toString().trim() || '';
            
            // Calculate cost price (70% of unit price)
            const costPriceVal = (!isNaN(unitPriceVal) && unitPriceVal > 0) ? (unitPriceVal * 0.7).toFixed(2) : '0';
            
            // Add computed and cleaned fields
            form.set('name', nameVal);
            form.set('generic_name', genericNameVal);
            form.set('category_id', categoryIdVal);
            form.set('supplier_id', supplierIdVal);
            form.set('description', descriptionVal);
            form.set('unit_price', unitPriceVal.toString());
            form.set('cost_price', costPriceVal);
            form.set('stock_quantity', stockQtyVal.toString());
            form.set('expiry_date', expiryDateVal);
            form.set('batch_number', batchNumberVal);
            form.append('medicine_code', 'MED-' + Date.now());
            form.append('reorder_level', '10');
            form.append('unit', 'pieces');
            form.append('barcode', '');
            
            console.log('Form values:', { 
                name: nameVal, 
                categoryId: categoryIdVal, 
                unitPrice: unitPriceVal, 
                stockQty: stockQtyVal, 
                expiryDate: expiryDateVal,
                costPrice: costPriceVal 
            });
            
            if (!nameVal || nameVal === '') {
                showNotification('Please enter Medicine Name', 'error');
                return;
            }
            if (!categoryIdVal || categoryIdVal === '' || categoryIdVal === 'Select Category') {
                showNotification('Please select a valid Category from the dropdown', 'error');
                return;
            }
            if (isNaN(unitPriceVal) || unitPriceVal <= 0) {
                showNotification('Please enter a valid Unit Price (must be greater than 0)', 'error');
                return;
            }
            if (isNaN(stockQtyVal) || stockQtyVal < 0) {
                showNotification('Please enter a valid Stock Quantity (must be 0 or more)', 'error');
                return;
            }
            if (!expiryDateVal || expiryDateVal === '') {
                showNotification('Please select an Expiry Date', 'error');
                return;
            }
            
            try {
                const medicineId = medicineForm.dataset.medicineId;
                let result;
                
                if (medicineId) {
                    // Update existing medicine - use JSON
                    const jsonData = Object.fromEntries(form);
                    jsonData.id = medicineId;
                    result = await apiRequest('medicines.php', 'PUT', jsonData);
                    showNotification('Medicine updated successfully!', 'success');
                } else {
                    // Add new medicine - use FormData for image upload
                    result = await fetch('api/medicines.php', {
                        method: 'POST',
                        body: form,
                        credentials: 'include'
                    });
                    
                    // Get response text first to check if it's JSON
                    const responseText = await result.text();
                    console.log('API Response:', responseText);
                    
                    let data;
                    try {
                        data = JSON.parse(responseText);
                    } catch (e) {
                        // Not valid JSON - probably PHP error output
                        throw new Error('Server error: ' + responseText.substring(0, 200));
                    }
                    
                    if (!result.ok || !data.success) {
                        throw new Error(data.message || 'Failed to create medicine');
                    }
                    
                    showNotification('Medicine added successfully!', 'success');
                    
                    // Navigate to Home page to see the new medicine
                    closeAllModals();
                    medicineForm.reset();
                    resetImageUpload();
                    window.location.hash = 'home';
                    showPage('home');
                    return; // Exit early as showPage will load products
                }
                
                // For edit case - just close modal and refresh
                closeAllModals();
                medicineForm.reset();
                resetImageUpload();
                loadMedicines(); // Refresh the medicines list
                loadHomeProducts(); // Refresh Home page products too
            } catch (error) {
                console.error('Failed to save medicine:', error);
                showNotification('Failed to save medicine: ' + error.message, 'error');
            }
        });
    }
    
    // Settings form handling
    document.querySelectorAll('#settings-page form').forEach(form => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            showNotification('Settings saved successfully!', 'success');
            updateNotificationBadge(); // Update badge count after saving
        });
    });
    
    // Update notification count when checkboxes change
    const notifCheckboxes = document.querySelectorAll('#settings-page .form-check input[type="checkbox"]');
    notifCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateNotificationBadge);
    });

    // Click outside to close
    window.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal')) {
            closeAllModals();
        }
    });
}

// Update notification badge count based on checked notification settings
function updateNotificationBadge() {
    const notifCheckboxes = document.querySelectorAll('#settings-page .form-check input[type="checkbox"]');
    const checkedCount = Array.from(notifCheckboxes).filter(cb => cb.checked).length;
    
    const badge = document.querySelector('.notifications .badge');
    if (badge) {
        badge.textContent = checkedCount;
        // Hide badge if 0
        badge.style.display = checkedCount > 0 ? 'flex' : 'none';
    }
}

function openModal(modal) {
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeAllModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.classList.remove('active');
    });
    document.body.style.overflow = '';
    
    // Reset medicine form if exists
    if (medicineForm) {
        medicineForm.reset();
        delete medicineForm.dataset.medicineId;
        // Reset modal title
        const modalTitle = document.querySelector('#medicineModal .modal-header h3');
        if (modalTitle) modalTitle.textContent = 'Add New Medicine';
        // Reset image upload
        resetImageUpload();
    }
}

function resetImageUpload() {
    const imageInput = document.getElementById('medicineImage');
    const imagePreview = document.getElementById('imagePreview');
    const uploadPlaceholder = document.getElementById('uploadPlaceholder');
    const removeImageBtn = document.getElementById('removeImage');
    const imageUploadBox = document.getElementById('imageUploadBox');
    
    if (imageInput) imageInput.value = '';
    if (imagePreview) {
        imagePreview.src = '';
        imagePreview.style.display = 'none';
    }
    if (uploadPlaceholder) uploadPlaceholder.style.display = 'block';
    if (removeImageBtn) removeImageBtn.style.display = 'none';
    if (imageUploadBox) imageUploadBox.classList.remove('has-image');
}

// Home Page - Product management view
function initHomePage() {
    // Add Product button
    const addProductBtn = document.getElementById('addProductBtn');
    if (addProductBtn) {
        addProductBtn.addEventListener('click', async () => {
            // Refresh categories and suppliers before opening modal
            await Promise.all([
                refreshCategoriesForModal(),
                refreshSuppliersForModal()
            ]);
            // Open add medicine modal directly on Home page
            medicineForm.reset();
            delete medicineForm.dataset.medicineId;
            document.querySelector('#medicineModal .modal-header h3').textContent = 'Add New Medicine';
            openModal(medicineModal);
        });
    }
    
    // Search functionality for Home page
    const homeSearch = document.getElementById('homeSearch');
    if (homeSearch) {
        homeSearch.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            document.querySelectorAll('.product-card-home').forEach(card => {
                const productName = card.querySelector('h4').textContent.toLowerCase();
                if (productName.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }
    
    // Product Edit/Delete buttons (admin only)
    document.querySelectorAll('.product-card-home').forEach(card => {
        const editBtn = card.querySelector('.btn-icon[title="Edit"]');
        const deleteBtn = card.querySelector('.btn-icon[title="Delete"]');
        const productId = card.getAttribute('data-id');
        
        if (editBtn) {
            editBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                editProduct(productId);
            });
        }
        
        if (deleteBtn) {
            deleteBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                deleteProduct(productId);
            });
        }
    });
    
}

// Cart and checkout functions removed - Current Order section removed from Sales page
// Products now display with Edit/Delete actions for admin users

// Pagination settings
let homePageCurrentPage = 1;
const homePageItemsPerPage = 20;
let homePageTotalMedicines = [];

// Load products for Home page
async function loadHomeProducts(page = 1) {
    const productsGrid = document.getElementById('productsGrid');
    if (!productsGrid) return;
    
    // Show loading state
    if (page === 1) {
        productsGrid.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-spinner fa-spin"></i>
                <h3>Loading Products...</h3>
                <p>Please wait while we load your inventory</p>
            </div>
        `;
    }
    
    try {
        const result = await apiRequest('medicines.php', 'GET');
        if (result.success && result.data) {
            homePageTotalMedicines = result.data.filter(m => m.is_active !== 0);
            
            if (homePageTotalMedicines.length === 0) {
                productsGrid.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <h3>No Products Yet</h3>
                        <p>Click "Add Medicine" to add your first product</p>
                    </div>
                `;
                return;
            }
            
            // Calculate pagination
            const totalPages = Math.ceil(homePageTotalMedicines.length / homePageItemsPerPage);
            const startIndex = (page - 1) * homePageItemsPerPage;
            const endIndex = startIndex + homePageItemsPerPage;
            const medicines = homePageTotalMedicines.slice(startIndex, endIndex);
            
            let html = medicines.map(med => {
                const stockClass = med.stock_quantity <= 10 ? 'low' : 'high';
                const stockText = med.stock_quantity <= 10 ? 'Low Stock' : 'In Stock';
                
                // Image display
                let imageHtml;
                if (med.image_path) {
                    imageHtml = `<img src="${med.image_path}" alt="${med.name}" loading="lazy">`;
                } else {
                    // Use category-based icon
                    const iconClass = med.category_name?.toLowerCase().includes('capsule') ? 'fa-capsules' : 
                                     med.category_name?.toLowerCase().includes('tablet') ? 'fa-tablets' : 
                                     med.category_name?.toLowerCase().includes('syrup') ? 'fa-flask' : 
                                     med.category_name?.toLowerCase().includes('injection') ? 'fa-syringe' : 'fa-pills';
                    imageHtml = `<div class="no-image"><i class="fas ${iconClass}"></i></div>`;
                }
                
                return `
                    <div class="product-card-home" data-id="${med.id}">
                        <div class="product-image-wrapper">
                            ${imageHtml}
                        </div>
                        <div class="product-info">
                            <h4>${med.name}</h4>
                            ${med.generic_name ? `<p class="generic-name">${med.generic_name}</p>` : ''}
                            <p class="description">${med.description || 'No description available'}</p>
                            <div class="product-meta">
                                <span class="product-price">৳${parseFloat(med.unit_price).toFixed(2)}</span>
                                <span class="product-stock ${stockClass}">${stockText}</span>
                            </div>
                        </div>
                        <div class="product-actions admin-only">
                            <button class="btn-icon" title="Edit" onclick="editProduct(${med.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon" title="Delete" onclick="deleteProduct(${med.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
            
            // Add pagination controls
            if (totalPages > 1) {
                html += `
                    <div class="pagination" style="grid-column: 1 / -1; margin-top: 24px;">
                        <div class="page-nav" style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                            <button class="btn btn-outline" ${page === 1 ? 'disabled' : ''} onclick="loadHomeProducts(${page - 1})">Previous</button>
                            ${Array.from({length: totalPages}, (_, i) => i + 1).map(p => `
                                <button class="btn ${p === page ? 'btn-primary' : 'btn-outline'}" onclick="loadHomeProducts(${p})">${p}</button>
                            `).join('')}
                            <button class="btn btn-outline" ${page === totalPages ? 'disabled' : ''} onclick="loadHomeProducts(${page + 1})">Next</button>
                        </div>
                        <p style="text-align: center; color: #6b7280; margin-top: 8px; font-size: 0.875rem;">
                            Showing ${startIndex + 1}-${Math.min(endIndex, homePageTotalMedicines.length)} of ${homePageTotalMedicines.length} medicines
                        </p>
                    </div>
                `;
            }
            
            productsGrid.innerHTML = html;
            homePageCurrentPage = page;
            
            // Re-attach event listeners for edit/delete buttons
            initHomePage();
        }
    } catch (error) {
        console.error('Failed to load Home products:', error);
        productsGrid.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-exclamation-circle" style="color: #ef4444;"></i>
                <h3>Failed to Load Products</h3>
                <p>${error.message || 'Please check your connection and try again'}</p>
                <button class="btn btn-primary" onclick="loadHomeProducts(1)" style="margin-top: 16px;">
                    <i class="fas fa-redo"></i> Retry
                </button>
            </div>
        `;
    }
}

// Product management functions
function editProduct(id) {
    // Open edit modal directly on Home page
    editMedicine(id);
}

async function deleteProduct(id) {
    if (confirm('Are you sure you want to delete this product?')) {
        try {
            await apiRequest('medicines.php?id=' + id, 'DELETE');
            showNotification('Product deleted successfully', 'success');
            loadHomeProducts(); // Refresh the product list
            loadMedicines(); // Refresh medicines table too
        } catch (error) {
            showNotification('Failed to delete product', 'error');
        }
    }
}

// Charts
function initCharts() {
    const ctx = document.getElementById('salesChart');
    if (!ctx) return;
    
    // Destroy existing chart if any
    if (window.salesChartInstance) {
        window.salesChartInstance.destroy();
    }
    
    window.salesChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Sales ($)',
                data: [1200, 1900, 1500, 2200, 1800, 2800, 2400],
                borderColor: '#0ea5e9',
                backgroundColor: 'rgba(14, 165, 233, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#0ea5e9',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#f1f5f9'
                    },
                    ticks: {
                        color: '#64748b',
                        callback: function(value) {
                            return '$' + value;
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#64748b'
                    }
                }
            }
        }
    });
}

// Search functionality
function initSearch() {
    // POS search
    const posSearch = document.getElementById('posSearch');
    if (posSearch) {
        posSearch.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            document.querySelectorAll('.product-card').forEach(card => {
                const name = card.getAttribute('data-name').toLowerCase();
                card.style.display = name.includes(query) ? 'block' : 'none';
            });
        });
    }
}

// Notification system
function showNotification(message, type = 'info') {
    // Remove existing notifications
    document.querySelectorAll('.notification-toast').forEach(n => n.remove());
    
    const notification = document.createElement('div');
    notification.className = `notification-toast ${type}`;
    notification.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
        <span>${message}</span>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#0ea5e9'};
        color: white;
        padding: 16px 20px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 9999;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add notification animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);

// Table select all functionality
document.querySelectorAll('.select-all').forEach(checkbox => {
    checkbox.addEventListener('change', (e) => {
        const table = checkbox.closest('table');
        const checkboxes = table.querySelectorAll('tbody input[type="checkbox"]');
        checkboxes.forEach(cb => cb.checked = e.target.checked);
    });
});

// Tab functionality for POS
document.querySelectorAll('.category-tabs .tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.category-tabs .tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        
        // Filter products by category
        const category = tab.textContent;
        document.querySelectorAll('.product-card').forEach(card => {
            // For demo, show all when "All" is selected
            if (category === 'All') {
                card.style.display = 'block';
            } else {
                // Simple random filter for demo
                card.style.display = Math.random() > 0.5 ? 'block' : 'none';
            }
        });
    });
});

// Button click handlers for tables
document.querySelectorAll('.btn-icon').forEach(btn => {
    btn.addEventListener('click', function() {
        const title = this.getAttribute('title');
        if (title === 'Edit') {
            showNotification('Edit functionality coming soon!', 'info');
        } else if (title === 'Delete') {
            if (confirm('Are you sure you want to delete this item?')) {
                showNotification('Item deleted successfully!', 'success');
            }
        }
    });
});

// Add Customer button
document.getElementById('addCustomerBtn')?.addEventListener('click', () => {
    showNotification('Add customer form coming soon!', 'info');
});

// Add Supplier button
document.getElementById('addSupplierBtn')?.addEventListener('click', () => {
    showNotification('Add supplier form coming soon!', 'info');
});

// Restock buttons
document.querySelectorAll('.btn-sm').forEach(btn => {
    if (btn.textContent === 'Restock') {
        btn.addEventListener('click', () => {
            showNotification('Restock order initiated!', 'success');
        });
    }
});
