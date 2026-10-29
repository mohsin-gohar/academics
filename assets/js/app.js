// Academics Common JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // CSRF token from meta tag
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // ---------- Theme ----------
    const themeToggle = document.getElementById('themeToggle');
    const applyTheme = (t) => {
        document.documentElement.setAttribute('data-theme', t);
        try { localStorage.setItem('es_theme', t); } catch (e) {}
        if (themeToggle) {
            themeToggle.innerHTML = t === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
            themeToggle.setAttribute('aria-label', t === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
        }
    };
    (function initTheme() {
        let current = document.documentElement.getAttribute('data-theme');
        if (!current) {
            try { current = localStorage.getItem('es_theme'); } catch (e) {}
        }
        current = current === 'dark' ? 'dark' : 'light';
        applyTheme(current);
    })();
    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            applyTheme(next);
        });
    }

    // Sidebar toggle for mobile
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });
    }
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }

    // Toast notification function
    window.showToast = function(message, type = 'success') {
        const existing = document.querySelector('.custom-toast');
        if (existing) existing.remove();

        const bgClass = type === 'success' ? 'bg-success' : (type === 'danger' ? 'bg-danger' : (type === 'warning' ? 'bg-warning' : 'bg-info'));
        const toastHtml = `
            <div class="custom-toast position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
                <div class="toast ${bgClass} text-white" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-body d-flex align-items-center gap-2">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'danger' ? 'exclamation-circle' : 'info-circle')}"></i>
                        <span>${message}</span>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', toastHtml);

        const toastElement = document.querySelector('.custom-toast .toast');
        const bsToast = new bootstrap.Toast(toastElement, { autohide: true, delay: 3000 });
        bsToast.show();

        toastElement.addEventListener('hidden.bs.toast', () => {
            document.querySelector('.custom-toast')?.remove();
        });
    };

    // CSRF-aware fetch wrapper
    window.fetchWithCsrf = function(url, options = {}) {
        options.headers = options.headers || {};
        options.headers['X-CSRF-Token'] = csrfToken;
        if (options.method && options.method.toUpperCase() !== 'GET') {
            options.headers['Content-Type'] = options.headers['Content-Type'] || 'application/json';
        }
        return fetch(url, options);
    };

    // Confirm dialog
    window.confirmAction = function(message) {
        return confirm(message || 'Are you sure?');
    };

    // Auto-dismiss alerts after 5 seconds
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 5000);
    });
});
