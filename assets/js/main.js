// Wolf Palomar Gym System - Main JavaScript

document.addEventListener('DOMContentLoaded', function() {
    
    // Sidebar Toggle
    const moreBtn = document.querySelector('.nav-more-btn');
    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.querySelector('.sidebar-overlay');
    
    if (moreBtn) {
        moreBtn.addEventListener('click', function(e) {
            e.preventDefault();
            toggleSidebar();
        });
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            closeSidebar();
        });
    }
    
    function toggleSidebar() {
        sidebar.classList.toggle('active');
        sidebarOverlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
    }
    
    function closeSidebar() {
        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    // Dropdown Menu Toggle
    const dropdownMenus = document.querySelectorAll('.menu-dropdown');
    
    dropdownMenus.forEach(menu => {
        menu.addEventListener('click', function(e) {
            e.preventDefault();
            const dropdownContent = this.nextElementSibling;
            
            // Close other dropdowns
            dropdownMenus.forEach(otherMenu => {
                if (otherMenu !== this) {
                    otherMenu.classList.remove('active');
                    const otherContent = otherMenu.nextElementSibling;
                    if (otherContent && otherContent.classList.contains('dropdown-content')) {
                        otherContent.classList.remove('active');
                    }
                }
            });
            
            // Toggle current dropdown
            this.classList.toggle('active');
            if (dropdownContent && dropdownContent.classList.contains('dropdown-content')) {
                dropdownContent.classList.toggle('active');
            }
        });
    });
    
    // Active Navigation Item
    const navItems = document.querySelectorAll('.nav-item');
    const currentPage = window.location.pathname.split('/').pop();
    
    navItems.forEach(item => {
        const href = item.getAttribute('href');
        if (href && href.includes(currentPage)) {
            item.classList.add('active');
        }
    });
    
    // Form Validation
    const forms = document.querySelectorAll('form[data-validate="true"]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                    showError(field, 'This field is required');
                } else {
                    field.classList.remove('error');
                    removeError(field);
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showAlert('Please fill in all required fields', 'error');
            }
        });
    });
    
    // Show Error Message
    function showError(field, message) {
        removeError(field);
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.style.color = '#ff4444';
        errorDiv.style.fontSize = '12px';
        errorDiv.style.marginTop = '5px';
        errorDiv.textContent = message;
        field.parentNode.appendChild(errorDiv);
    }
    
    function removeError(field) {
        const errorMsg = field.parentNode.querySelector('.error-message');
        if (errorMsg) {
            errorMsg.remove();
        }
    }
    
    // Alert System
    window.showAlert = function(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.style.position = 'fixed';
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.padding = '15px 20px';
        alertDiv.style.borderRadius = '8px';
        alertDiv.style.boxShadow = '0 4px 15px rgba(0,0,0,0.2)';
        alertDiv.style.zIndex = '9999';
        alertDiv.style.maxWidth = '300px';
        alertDiv.style.animation = 'slideInRight 0.3s ease';
        
        if (type === 'success') {
            alertDiv.style.background = '#7FFF00';
            alertDiv.style.color = '#000';
        } else if (type === 'error') {
            alertDiv.style.background = '#ff4444';
            alertDiv.style.color = '#fff';
        } else {
            alertDiv.style.background = '#000';
            alertDiv.style.color = '#fff';
        }
        
        alertDiv.textContent = message;
        document.body.appendChild(alertDiv);
        
        setTimeout(() => {
            alertDiv.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => alertDiv.remove(), 300);
        }, 3000);
    };
    
    // Confirm Dialog
    window.confirmAction = function(message, callback) {
        const confirmed = confirm(message);
        if (confirmed && typeof callback === 'function') {
            callback();
        }
        return confirmed;
    };
    
    // Format Currency
    window.formatCurrency = function(amount) {
        return '₱' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    };
    
    // Format Date
    window.formatDate = function(dateString) {
        const date = new Date(dateString);
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        return date.toLocaleDateString('en-US', options);
    };
    
    // Format Time
    window.formatTime = function(timeString) {
        const time = new Date('2000-01-01 ' + timeString);
        return time.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    };
    
    // Search Functionality
    const searchInputs = document.querySelectorAll('[data-search]');
    
    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const targetSelector = this.getAttribute('data-search');
            const items = document.querySelectorAll(targetSelector);
            
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
    
    // Auto-refresh data every 30 seconds
    setInterval(function() {
        if (typeof refreshDashboardData === 'function') {
            refreshDashboardData();
        }
    }, 30000);
    
    // Image Preview on Upload
    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    
    imageInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const previewId = input.getAttribute('data-preview');
                    if (previewId) {
                        const preview = document.getElementById(previewId);
                        if (preview) {
                            preview.src = event.target.result;
                            preview.style.display = 'block';
                        }
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    });
    
    // Print Function
    window.printElement = function(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            const printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write('<html><head><title>Print</title>');
            printWindow.document.write('<link rel="stylesheet" href="assets/css/style.css">');
            printWindow.document.write('</head><body>');
            printWindow.document.write(element.innerHTML);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.print();
        }
    };
    
    // Copy to Clipboard
    window.copyToClipboard = function(text) {
        const tempInput = document.createElement('textarea');
        tempInput.value = text;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
        showAlert('Copied to clipboard!', 'success');
    };
    
    // Loading Spinner
    window.showLoading = function() {
        const loader = document.createElement('div');
        loader.id = 'loading-spinner';
        loader.style.position = 'fixed';
        loader.style.top = '0';
        loader.style.left = '0';
        loader.style.width = '100%';
        loader.style.height = '100%';
        loader.style.background = 'rgba(0,0,0,0.5)';
        loader.style.display = 'flex';
        loader.style.alignItems = 'center';
        loader.style.justifyContent = 'center';
        loader.style.zIndex = '99999';
        loader.innerHTML = '<div style="width:50px;height:50px;border:5px solid #7FFF00;border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite"></div>';
        document.body.appendChild(loader);
    };
    
    window.hideLoading = function() {
        const loader = document.getElementById('loading-spinner');
        if (loader) {
            loader.remove();
        }
    };
    
    // Add CSS animation for spinner
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
});