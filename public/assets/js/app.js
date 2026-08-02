document.addEventListener('DOMContentLoaded', function () {
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

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

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
