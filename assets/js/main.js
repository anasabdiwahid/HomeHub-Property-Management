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

    // Generic Confirm Delete Helper
    window.confirmAction = function (message = 'Are you sure you want to proceed with this action?') {
        return confirm(message);
    };

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
