(function () {
    // Phase 18 (CMS-055): dark mode - doc localStorage TRUOC khi DOMContentLoaded de tranh
    // "flash" theme sai luc tai trang (FOUC). Khong doi hanh vi server/Controller nao - thuan
    // client-side, luu y bang [data-theme] tren <html> + localStorage.
    var THEME_KEY = 'cms-theme';
    var savedTheme = null;

    try {
        savedTheme = window.localStorage.getItem(THEME_KEY);
    } catch (error) {
        savedTheme = null;
    }

    if (savedTheme === 'light' || savedTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', savedTheme);
    }
})();

document.addEventListener('DOMContentLoaded', function () {
    var THEME_KEY = 'cms-theme';
    var themeToggle = document.querySelector('[data-theme-toggle]');

    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            var current = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
            var next = current === 'light' ? 'dark' : 'light';

            document.documentElement.setAttribute('data-theme', next);

            try {
                window.localStorage.setItem(THEME_KEY, next);
            } catch (error) {
                // Silent - localStorage khong kha dung (vd private mode) khong duoc lam gian doan UI.
            }
        });
    }

    var sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    var sidebar = document.querySelector('.admin-sidebar');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('is-open');
        });
    }

    var navToggle = document.querySelector('[data-nav-toggle]');
    var siteNav = document.querySelector('.site-nav');

    if (navToggle && siteNav) {
        navToggle.addEventListener('click', function () {
            siteNav.classList.toggle('is-open');
        });
    }

    document.querySelectorAll('.admin-nav a[href]').forEach(function (link) {
        var href = link.getAttribute('href');

        if (href && href !== '/admin/dashboard' && window.location.pathname.indexOf(href) === 0) {
            link.classList.add('is-active');
        } else if (href === '/admin/dashboard' && window.location.pathname === href) {
            link.classList.add('is-active');
        }
    });

    // Phase 18 (CMS-055): thay window.confirm() tho bang modal tuy chinh (#confirm-modal, xem
    // admin.partials.confirm_modal). Neu partial chua duoc include o trang nao do (fallback an
    // toan), quay ve window.confirm() nguyen ban - khong bao gio chan submit ma khong hoi gi ca.
    var confirmModal = document.getElementById('confirm-modal');
    var confirmMessageEl = confirmModal ? confirmModal.querySelector('[data-confirm-message]') : null;
    var confirmAcceptBtn = confirmModal ? confirmModal.querySelector('[data-confirm-accept]') : null;
    var pendingConfirmForm = null;

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.getAttribute('data-confirmed') === 'true') {
                return;
            }

            if (!confirmModal || !confirmAcceptBtn) {
                if (!window.confirm(form.getAttribute('data-confirm'))) {
                    event.preventDefault();
                }

                return;
            }

            event.preventDefault();
            pendingConfirmForm = form;

            if (confirmMessageEl) {
                confirmMessageEl.textContent = form.getAttribute('data-confirm') || '';
            }

            confirmModal.classList.add('is-open');
        });
    });

    if (confirmAcceptBtn) {
        confirmAcceptBtn.addEventListener('click', function () {
            confirmModal.classList.remove('is-open');

            if (pendingConfirmForm) {
                pendingConfirmForm.setAttribute('data-confirmed', 'true');
                pendingConfirmForm.submit();
                pendingConfirmForm = null;
            }
        });
    }

    document.querySelectorAll('[data-modal-open]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            var modal = document.getElementById(trigger.getAttribute('data-modal-open'));

            if (modal) {
                modal.classList.add('is-open');
            }
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            var modal = document.getElementById(trigger.getAttribute('data-modal-close'));

            if (modal) {
                modal.classList.remove('is-open');
            }
        });
    });

    // Phase 18 (CMS-055): Media Grid/List toggle - thuan CSS class .is-hidden, khong tai lai trang.
    var viewToggle = document.querySelector('[data-view-toggle]');

    if (viewToggle) {
        var viewButtons = viewToggle.querySelectorAll('[data-view]');
        var viewPanels = document.querySelectorAll('[data-view-panel]');

        viewButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var target = button.getAttribute('data-view');

                viewButtons.forEach(function (btn) {
                    btn.classList.toggle('is-active', btn === button);
                });

                viewPanels.forEach(function (panel) {
                    panel.classList.toggle('is-hidden', panel.getAttribute('data-view-panel') !== target);
                });
            });
        });
    }

    var dropzone = document.getElementById('media-dropzone');
    var fileInput = document.getElementById('media-file-input');

    if (dropzone && fileInput) {
        ['dragenter', 'dragover'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                dropzone.classList.add('is-dragover');
            });
        });

        ['dragleave', 'drop'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                dropzone.classList.remove('is-dragover');
            });
        });

        dropzone.addEventListener('drop', function (event) {
            if (event.dataTransfer && event.dataTransfer.files.length > 0) {
                fileInput.files = event.dataTransfer.files;
            }
        });
    }

    var menuTree = document.querySelector('[data-menu-tree]');

    if (menuTree) {
        var csrfToken = menuTree.getAttribute('data-csrf-token');
        var draggedId = null;

        menuTree.addEventListener('dragstart', function (event) {
            var item = event.target.closest('.menu-tree-item');

            if (!item) {
                return;
            }

            draggedId = item.getAttribute('data-item-id');
            item.classList.add('is-dragging');
        });

        menuTree.addEventListener('dragend', function () {
            document.querySelectorAll('.menu-tree-item.is-dragging').forEach(function (el) {
                el.classList.remove('is-dragging');
            });
            document.querySelectorAll('.menu-tree-item.is-drop-target').forEach(function (el) {
                el.classList.remove('is-drop-target');
            });
        });

        menuTree.addEventListener('dragover', function (event) {
            event.preventDefault();
            var item = event.target.closest('.menu-tree-item');

            if (item && item.getAttribute('data-item-id') !== draggedId) {
                item.classList.add('is-drop-target');
            }
        });

        menuTree.addEventListener('dragleave', function (event) {
            var item = event.target.closest('.menu-tree-item');

            if (item) {
                item.classList.remove('is-drop-target');
            }
        });

        menuTree.addEventListener('drop', function (event) {
            event.preventDefault();
            var targetItem = event.target.closest('.menu-tree-item');
            var newParentId = '';

            if (targetItem) {
                targetItem.classList.remove('is-drop-target');

                if (targetItem.getAttribute('data-item-id') !== draggedId) {
                    newParentId = targetItem.getAttribute('data-item-id');
                }
            }

            if (!draggedId) {
                return;
            }

            var formData = new FormData();
            formData.append('_token', csrfToken);
            formData.append('parent_id', newParentId);

            fetch('/admin/menu-items/' + draggedId, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            }).then(function (response) {
                return response.json();
            }).then(function (result) {
                if (result.success) {
                    window.location.reload();
                } else {
                    window.alert(result.message || 'Khong the cap nhat vi tri.');
                }
            }).catch(function () {
                window.alert('Loi ket noi. Vui long thu lai.');
            });

            draggedId = null;
        });
    }
});
