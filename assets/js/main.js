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

    // Modern In-Page Centered Confirm Dialog (Completely removes native browser "localhost says" alert)
    window.confirmDialog = function (options = {}) {
        if (typeof options === 'string') {
            options = { text: options };
        }

        const isDelete = /delete|tirtir|remove|vacate|cancel/i.test(options.text || '');
        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';

        const defaultOptions = {
            title: isDelete ? 'Confirm Deletion' : 'Confirm Action',
            text: 'Please confirm this action before proceeding.',
            icon: isDelete ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonColor: isDelete ? '#dc3545' : '#102a45',
            cancelButtonColor: '#6c757d',
            confirmButtonText: isDelete ? '<i class="bi bi-trash3-fill me-1"></i> Yes, Delete' : '<i class="bi bi-check-lg me-1"></i> Yes, Confirm',
            cancelButtonText: 'Cancel',
            reverseButtons: true,
            focusCancel: true,
            background: isDark ? '#1e293b' : '#ffffff',
            color: isDark ? '#f8fafc' : '#0f172a',
            customClass: {
                popup: 'rounded-4 shadow-lg border border-secondary border-opacity-25'
            }
        };

        const merged = Object.assign({}, defaultOptions, options);

        if (window.Swal) {
            return Swal.fire(merged);
        } else {
            return fallbackBootstrapConfirm(merged);
        }
    };

    // In-Page Bootstrap Modal Fallback (guarantees zero native alerts even if Swal is not ready)
    function fallbackBootstrapConfirm(options) {
        return new Promise((resolve) => {
            const oldModal = document.getElementById('homehubDynamicConfirmModal');
            if (oldModal) oldModal.remove();

            const isDelete = /delete|tirtir|remove|vacate|cancel/i.test(options.text || '');
            const title = options.title || (isDelete ? 'Confirm Deletion' : 'Confirm Action');
            const text = options.text || 'Please confirm this action before proceeding.';
            const confirmBtnText = options.confirmButtonText || (isDelete ? '<i class="bi bi-trash3-fill me-1"></i> Yes, Delete' : '<i class="bi bi-check-lg me-1"></i> Confirm');
            const cancelBtnText = options.cancelButtonText || 'Cancel';
            const confirmBtnClass = isDelete ? 'btn-danger' : 'btn-primary';

            const modalEl = document.createElement('div');
            modalEl.id = 'homehubDynamicConfirmModal';
            modalEl.className = 'modal fade';
            modalEl.tabIndex = -1;
            modalEl.setAttribute('aria-hidden', 'true');
            modalEl.innerHTML = `
                <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
                    <div class="modal-content shadow-lg border-0 rounded-4">
                        <div class="modal-body text-center p-4">
                            <div class="mb-3">
                                <span class="d-inline-flex align-items-center justify-content-center rounded-circle ${isDelete ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary'}" style="width: 64px; height: 64px; font-size: 28px;">
                                    <i class="bi ${isDelete ? 'bi-exclamation-triangle-fill' : 'bi-question-circle-fill'}"></i>
                                </span>
                            </div>
                            <h5 class="fw-bold mb-2">${title}</h5>
                            <p class="text-muted small mb-4">${text}</p>
                            <div class="d-flex justify-content-center gap-2">
                                <button type="button" class="btn btn-secondary px-4 py-2 rounded-3" data-bs-dismiss="modal">${cancelBtnText}</button>
                                <button type="button" class="btn ${confirmBtnClass} px-4 py-2 rounded-3" id="dynamicConfirmBtn">${confirmBtnText}</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modalEl);

            if (window.bootstrap && bootstrap.Modal) {
                const bsModal = new bootstrap.Modal(modalEl);
                let confirmed = false;

                const confirmBtn = document.getElementById('dynamicConfirmBtn');
                if (confirmBtn) {
                    confirmBtn.addEventListener('click', function () {
                        confirmed = true;
                        bsModal.hide();
                    });
                }

                modalEl.addEventListener('hidden.bs.modal', function () {
                    modalEl.remove();
                    resolve({ isConfirmed: confirmed });
                });

                bsModal.show();
            } else {
                modalEl.remove();
                resolve({ isConfirmed: true });
            }
        });
    }

    window.confirmAction = function (message = 'Are you sure you want to proceed with this action?') {
        return window.confirmDialog({ text: message });
    };

    // Modern In-Page Notice Replacement (Never triggers native "localhost says" alert)
    window.alert = function (message) {
        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        if (window.Swal) {
            Swal.fire({
                title: 'Notice',
                text: message,
                icon: 'info',
                confirmButtonText: 'OK',
                confirmButtonColor: '#102a45',
                background: isDark ? '#1e293b' : '#ffffff',
                color: isDark ? '#f8fafc' : '#0f172a',
                customClass: {
                    popup: 'rounded-4 shadow-lg border border-secondary border-opacity-25'
                }
            });
        } else {
            console.log('HomeHub Notice:', message);
        }
    };

    // Suppress any accidental native browser confirm()
    window.confirm = function (message) {
        console.warn('Native window.confirm was suppressed in favor of in-page dialogs:', message);
        return false;
    };

    // Modern Password Copied In-Page Modal
    window.showPasswordCopied = function (pwd) {
        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        if (window.Swal) {
            Swal.fire({
                icon: 'success',
                title: 'Password Generated & Copied!',
                html: `
                    <p class="text-muted small mb-2">This password has been automatically copied to your clipboard:</p>
                    <div class="p-3 bg-body-tertiary rounded-3 border my-2 text-center">
                        <span class="font-monospace fs-4 fw-bold text-primary">${pwd}</span>
                    </div>
                    <small class="text-success fw-semibold"><i class="bi bi-clipboard-check-fill me-1"></i> You can paste it now (Ctrl + V).</small>
                `,
                confirmButtonText: 'OK',
                confirmButtonColor: '#102a45',
                background: isDark ? '#1e293b' : '#ffffff',
                color: isDark ? '#f8fafc' : '#0f172a',
                customClass: {
                    popup: 'rounded-4 shadow-lg border border-secondary border-opacity-25'
                }
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
                confirmMsg = 'Are you sure you want to proceed with this action?';
            }
            form.removeAttribute('onsubmit');
        }

        if (confirmMsg) {
            e.preventDefault();
            e.stopPropagation();

            window.confirmDialog({
                text: confirmMsg
            }).then((result) => {
                if (result && result.isConfirmed) {
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

    // Instant Table Live Filter Utility (Real-time as-you-type search)
    window.initTableLiveFilter = function (config) {
        if (!config) return null;
        const input = typeof config.input === 'string' ? document.querySelector(config.input) : config.input;
        const tableBody = typeof config.tableBody === 'string' ? document.querySelector(config.tableBody) : config.tableBody;
        if (!input || !tableBody) return null;

        const rowSelector = config.rowSelector || 'tr[data-name]';
        const clearBtn = typeof config.clearBtn === 'string' ? document.querySelector(config.clearBtn) : config.clearBtn;
        const countDisplay = typeof config.countDisplay === 'string' ? document.querySelector(config.countDisplay) : config.countDisplay;
        const itemLabel = config.itemLabel || 'properties';
        const columnsCount = config.columnsCount || 9;

        const rows = Array.from(tableBody.querySelectorAll(rowSelector));
        const totalCount = rows.length;

        // Prevent Enter key from triggering page reload during typing
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            } else if (e.key === 'Escape') {
                input.value = '';
                runFilter();
            }
        });

        function runFilter() {
            const query = input.value.trim().toLowerCase();
            const tokens = query ? query.split(/\s+/).filter(Boolean) : [];

            // Toggle clear button visibility
            if (clearBtn) {
                clearBtn.classList.toggle('d-none', query.length === 0);
            }

            const customFilter = typeof config.customFilter === 'function' ? config.customFilter : null;

            // Remove any previous dynamic "no results" row
            const oldNoRow = tableBody.querySelector('.dynamic-no-results-row');
            if (oldNoRow) oldNoRow.remove();

            let visibleCount = 0;

            rows.forEach(row => {
                // If a custom filter (e.g. dropdowns) rejects the row, hide it
                if (customFilter && !customFilter(row)) {
                    row.style.display = 'none';
                    return;
                }

                if (tokens.length > 0) {
                    const name = (row.dataset.name || '').toLowerCase();
                    const code = (row.dataset.code || '').toLowerCase();
                    const city = (row.dataset.city || '').toLowerCase();
                    const address = (row.dataset.address || '').toLowerCase();
                    const catName = (row.dataset.categoryName || '').toLowerCase();
                    const mgrName = (row.dataset.managerName || '').toLowerCase();
                    const allText = `${name} ${city} ${address} ${code} ${catName} ${mgrName} ${row.innerText.toLowerCase()}`;

                    // Extract word tokens for prefix matching (e.g., 'h' matches 'hodan', 'house', etc.)
                    const words = `${name} ${city} ${address} ${catName} ${mgrName}`.split(/[\s,.\-\/]+/).filter(Boolean);

                    const matches = tokens.every(token => {
                        if (token.length === 1) {
                            // Single character: match words starting with this letter (or code without 'hh-')
                            const cleanCode = code.replace(/^hh-?/i, '');
                            return words.some(w => w.startsWith(token)) || (cleanCode && cleanCode.startsWith(token));
                        } else {
                            // Multiple characters: match word prefix OR substring anywhere in row
                            return words.some(w => w.startsWith(token)) || allText.includes(token);
                        }
                    });

                    if (!matches) {
                        row.style.display = 'none';
                        return;
                    }
                }

                row.style.display = '';
                visibleCount++;
            });

            // Update live counter badge
            if (countDisplay) {
                const hasActiveDropdowns = typeof config.hasActiveDropdowns === 'function' ? config.hasActiveDropdowns() : false;
                if (query.length > 0 || hasActiveDropdowns) {
                    countDisplay.innerHTML = `Showing <span class="badge bg-primary px-2">${visibleCount}</span> of ${totalCount} ${itemLabel}`;
                } else {
                    countDisplay.textContent = `${totalCount} ${itemLabel} registered`;
                }
            }

            // Show empty search state if 0 rows matched
            if (visibleCount === 0 && totalCount > 0) {
                const noRow = document.createElement('tr');
                noRow.className = 'dynamic-no-results-row';
                noRow.innerHTML = `
                    <td colspan="${columnsCount}" class="text-center py-4 text-muted">
                        <i class="bi bi-search me-2 fs-5"></i>
                        No matching ${itemLabel} found for "<strong>${escapeHtml(query || 'selected filters')}</strong>".
                    </td>
                `;
                tableBody.appendChild(noRow);
            }
        }

        function escapeHtml(str) {
            return str.replace(/[&<>"']/g, function (m) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[m];
            });
        }

        // Real-time events
        input.addEventListener('input', runFilter);
        input.addEventListener('search', runFilter);

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                input.value = '';
                runFilter();
                input.focus();
            });
        }

        // Run immediately if already pre-filled
        if (input.value.trim().length > 0) {
            runFilter();
        }

        return { runFilter };
    };

    // Initialize on DOM Ready
    document.addEventListener('DOMContentLoaded', () => {
        initTheme();
        initAlerts();

        // Clear any previous dashboard zoom override
        localStorage.removeItem('homehub-dashboard-zoom');
        document.documentElement.style.removeProperty('--dashboard-zoom');

        // Enable tooltips if Bootstrap Tooltips are present
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
})();
