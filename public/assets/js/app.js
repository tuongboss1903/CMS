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

    // Phase D (Polish): flash message tu dismiss (nut x hoac tu dong sau 5s cho alert-success),
    // fade-out qua class .is-dismissing (xem @keyframes alert-out trong components.css).
    document.querySelectorAll('[data-flash]').forEach(function (flash) {
        var dismiss = function () {
            if (flash.classList.contains('is-dismissing')) {
                return;
            }

            flash.classList.add('is-dismissing');
            flash.addEventListener('animationend', function () {
                flash.remove();
            }, { once: true });
        };

        var dismissBtn = flash.querySelector('[data-flash-dismiss]');

        if (dismissBtn) {
            dismissBtn.addEventListener('click', dismiss);
        }

        if (flash.classList.contains('alert-success')) {
            window.setTimeout(dismiss, 5000);
        }
    });

    // Phase D (Polish): trang thai loading tren nut submit de chan double-submit va cho nguoi
    // dung biet thao tac dang duoc xu ly. Bo qua form[data-confirm] (da co luong rieng qua modal
    // xac nhan - disable som se hien sai trang thai khi nguoi dung con dang can nhac/huy modal).
    document.querySelectorAll('form:not([data-confirm])').forEach(function (form) {
        form.addEventListener('submit', function () {
            var submitBtn = form.querySelector('button[type="submit"]');

            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                submitBtn.setAttribute('data-loading-text', submitBtn.textContent);
                submitBtn.textContent = 'Đang xử lý...';
            }
        });
    });

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

    // Accessibility (WCAG 2.1 SC 2.1.2/4.1.2): moi modal (.modal-overlay.is-open) phai - dua focus
    // vao ben trong khi mo, tra focus ve dung phan tu da kich hoat modal khi dong, dong duoc bang
    // phim Escape, va bam ra vung nen (backdrop) ngoai .modal cung dong duoc - khong chi dong qua
    // nut [data-modal-close] nhu truoc. Dung chung cho ca #confirm-modal lan moi modal [data-modal-open].
    var lastFocusedBeforeModal = null;

    function focusableElementsIn(container) {
        return Array.prototype.slice.call(container.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        ));
    }

    function openModal(modal, trigger) {
        if (!modal) {
            return;
        }

        lastFocusedBeforeModal = trigger || document.activeElement;
        modal.classList.add('is-open');

        var dialog = modal.querySelector('.modal');

        window.setTimeout(function () {
            if (dialog && typeof dialog.focus === 'function') {
                dialog.focus();
            }
        }, 0);
    }

    function closeModal(modal) {
        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');

        if (lastFocusedBeforeModal && typeof lastFocusedBeforeModal.focus === 'function') {
            lastFocusedBeforeModal.focus();
        }

        lastFocusedBeforeModal = null;
    }

    function topmostOpenModal() {
        var openModals = document.querySelectorAll('.modal-overlay.is-open');

        return openModals.length > 0 ? openModals[openModals.length - 1] : null;
    }

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape' && event.key !== 'Tab') {
            return;
        }

        var modal = topmostOpenModal();

        if (!modal) {
            return;
        }

        if (event.key === 'Escape') {
            closeModal(modal);

            return;
        }

        // Tab: bay focus ben trong modal (focus trap) - Shift+Tab tu phan tu dau quay ve phan tu
        // cuoi va nguoc lai, tranh Tab thoat ra ngoai noi dung trang phia sau backdrop.
        var dialog = modal.querySelector('.modal');
        var focusable = dialog ? focusableElementsIn(dialog) : [];

        if (focusable.length === 0) {
            return;
        }

        var first = focusable[0];
        var last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('mousedown', function (event) {
            if (event.target === overlay) {
                closeModal(overlay);
            }
        });
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

            openModal(confirmModal, document.activeElement);
        });
    });

    if (confirmAcceptBtn) {
        confirmAcceptBtn.addEventListener('click', function () {
            closeModal(confirmModal);

            if (pendingConfirmForm) {
                pendingConfirmForm.setAttribute('data-confirmed', 'true');
                pendingConfirmForm.submit();
                pendingConfirmForm = null;
            }
        });
    }

    document.querySelectorAll('[data-modal-open]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            openModal(document.getElementById(trigger.getAttribute('data-modal-open')), trigger);
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            closeModal(document.getElementById(trigger.getAttribute('data-modal-close')));
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
                    window.alert(result.message || 'Không thể cập nhật vị trí.');
                }
            }).catch(function () {
                window.alert('Lỗi kết nối. Vui lòng thử lại.');
            });

            draggedId = null;
        });
    }

    // Phase C (Public UX): Quantity stepper o trang chi tiet San pham (shop.show) - chi tang/giam
    // gia tri input[type=number] da co san, KHONG thay doi ten field/gia tri submit qua form.
    document.querySelectorAll('.qty-stepper').forEach(function (stepper) {
        var input = stepper.querySelector('input[type="number"]');
        var decreaseBtn = stepper.querySelector('[data-qty-decrease]');
        var increaseBtn = stepper.querySelector('[data-qty-increase]');

        if (!input) {
            return;
        }

        if (decreaseBtn) {
            decreaseBtn.addEventListener('click', function () {
                var min = parseInt(input.min, 10) || 1;
                var value = parseInt(input.value, 10) || min;
                input.value = Math.max(min, value - 1);
            });
        }

        if (increaseBtn) {
            increaseBtn.addEventListener('click', function () {
                var value = parseInt(input.value, 10) || 1;
                input.value = value + 1;
            });
        }
    });
});
