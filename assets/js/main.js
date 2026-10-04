/**
 * HomeHub Property Management System
 * Core JavaScript & Theme Controller
 */

(function () {
    'use strict';

    // Theme Switcher Initialization
    const initTheme = () => {
        const storedTheme = localStorage.getItem('homehub-theme') || 'light';
        setTheme(storedTheme);
    };

    const setTheme = (theme) => {
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem('homehub-theme', theme);

        const themeIcons = document.querySelectorAll('.theme-toggle-icon');
        themeIcons.forEach(icon => {
            if (theme === 'dark') {
                icon.classList.remove('bi-moon-stars-fill');
                icon.classList.add('bi-sun-fill');
            } else {
                icon.classList.remove('bi-sun-fill');
                icon.classList.add('bi-moon-stars-fill');
            }
        });
    };

    window.toggleTheme = function () {
        const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        setTheme(newTheme);
    };

    // Auto-dismiss alerts after 5 seconds
    const initAlerts = () => {
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                if (bsAlert) {
                    bsAlert.close();
                }
            });
        }, 5000);
    };

    // Mobile Sidebar Toggle
    window.toggleSidebar = function () {
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.classList.toggle('show');
        }
    };

    // Close mobile sidebar on click outside
    document.addEventListener('click', (e) => {
        const sidebar = document.querySelector('.sidebar');
        const toggleBtn = e.target.closest('[onclick*="toggleSidebar"]');
        if (sidebar && sidebar.classList.contains('show') && !sidebar.contains(e.target) && !toggleBtn) {
            sidebar.classList.remove('show');
        }
    });

    // Modern SweetAlert2 Confirm Dialog Helper
    window.confirmDialog = function (options = {}) {
        const defaultOptions = {
            title: 'Ma Hubtaa? (Confirmation)',
            text: 'Fadlan xaqiiji tallaabadan ka hor inta aadan sii wadin.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-check-lg me-1"></i> Haa, Fuliy (Confirm)',
            cancelButtonText: 'Maya (Cancel)',
            reverseButtons: true,
            focusCancel: true
        };

        if (typeof options === 'string') {
            options = { text: options };
        }

        const merged = Object.assign({}, defaultOptions, options);

        if (window.Swal) {
            return Swal.fire(merged);
        } else {
            return Promise.resolve({ isConfirmed: confirm(merged.text) });
        }
    };

    window.confirmAction = function (message = 'Are you sure you want to proceed with this action?') {
        return window.confirmDialog({ text: message });
    };

    // Modern Alert Dialog Replacement (replaces window.alert to remove "localhost says")
    window.alert = function (message) {
        if (window.Swal) {
            Swal.fire({
                title: 'Ogeysiis (Notice)',
                text: message,
                icon: 'info',
                confirmButtonText: 'Waan Fahmay (OK)',
                confirmButtonColor: '#102a45'
            });
        } else {
            console.log('Notice:', message);
        }
    };

    // Modern Password Copied SweetAlert Modal
    window.showPasswordCopied = function (pwd) {
        if (window.Swal) {
            Swal.fire({
                icon: 'success',
                title: 'Password La Sameeyay & La Koobiyeeyay!',
                html: `
                    <p class="text-muted small mb-2">Password-kan waxaa si toos ah loogu koobiyeeyay clipboard-kaaga:</p>
                    <div class="p-3 bg-body-tertiary rounded-3 border my-2 text-center">
                        <span class="font-monospace fs-4 fw-bold text-primary">${pwd}</span>
                    </div>
                    <small class="text-success fw-semibold"><i class="bi bi-clipboard-check-fill me-1"></i> Waad paste gareysan kartaa hadda (Ctrl + V).</small>
                `,
                confirmButtonText: 'Waan Fahmay (OK)',
                confirmButtonColor: '#102a45'
            });
        } else {
            window.alert('Generated Password: ' + pwd);
        }
    };

    // Global Form Submit Interception for data-confirm & inline confirm()
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form || form.dataset.swalBypass === 'true') {
            return;
        }

        let confirmMsg = form.getAttribute('data-confirm') || form.dataset.confirm;

        // If form has inline onsubmit with confirm(...), extract message and intercept
        if (!confirmMsg && form.getAttribute('onsubmit') && form.getAttribute('onsubmit').includes('confirm(')) {
            const match = form.getAttribute('onsubmit').match(/confirm\s*\(\s*['"](.*?)['"]\s*\)/);
            if (match && match[1]) {
                confirmMsg = match[1].replace(/\\'/g, "'").replace(/\\"/g, '"');
            } else {
                confirmMsg = 'Ma hubtaa inaad tirtirto ama falkan fuliso?';
            }
            form.removeAttribute('onsubmit');
        }

        if (confirmMsg) {
            e.preventDefault();
            e.stopPropagation();

            window.confirmDialog({
                title: 'Ma Hubtaa? (Confirmation)',
                text: confirmMsg,
                icon: 'warning',
                confirmButtonColor: '#dc3545',
                confirmButtonText: '<i class="bi bi-trash3 me-1"></i> Haa, Tirtir (Confirm)',
                cancelButtonText: 'Maya (Cancel)'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.dataset.swalBypass = 'true';
                    form.submit();
                }
            });
        }
    }, true);

    // Export Table to CSV
    window.exportTableToCSV = function (tableId, filename = 'report.csv') {
        const table = document.getElementById(tableId);
        if (!table) return;

        let csv = [];
        const rows = table.querySelectorAll('tr');

        for (let i = 0; i < rows.length; i++) {
            const row = [], cols = rows[i].querySelectorAll('td, th');
            for (let j = 0; j < cols.length; j++) {
                // Ignore elements with class 'no-export'
                if (cols[j].classList.contains('no-export')) continue;
                let text = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s+)/g, ' ');
                text = text.replace(/"/g, '""');
                row.push('"' + text.trim() + '"');
            }
            if (row.length > 0) {
                csv.push(row.join(','));
            }
        }

        const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
        const downloadLink = document.createElement('a');
        downloadLink.download = filename;
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = 'none';
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    };

    // Initialize on DOM Ready
    document.addEventListener('DOMContentLoaded', () => {
        initTheme();
        initAlerts();

        // Enable tooltips if Bootstrap Tooltips are present
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
})();
