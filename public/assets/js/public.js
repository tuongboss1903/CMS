/**
 * JS rieng cho Public site (Trang cong khai/Shop/Cart/Checkout) - TRUOC DAY dung chung
 * /assets/js/app.js voi Admin, khien khach truy cap tai ve ~15KB JS trong do ~95% la logic
 * chi Admin dung (modal accessibility, sidebar toggle, Media dropzone, Menu Builder keo-tha...)
 * ma DOM Public khong bao gio co cac phan tu tuong ung nen toan bo code do la "dead weight".
 * File nay chi giu 4 tinh nang Public THAT SU dung (xac minh qua grep toan bo
 * themes/default/views/{layouts,cart,checkout,shop,pages}): flash message tu dismiss (cart co
 * dung), trang thai loading nut submit (Checkout - tranh dat hang trung khi bam nhieu lan), toggle
 * Menu mobile (nav-toggle), Quantity stepper (shop.show). Khong dung <script> Admin nao khac -
 * xem public/assets/js/app.js cho toan bo logic con lai (thuan Admin/System Admin).
 */
document.addEventListener('DOMContentLoaded', function () {
    // Flash message tu dismiss (nut x hoac tu dong sau 5s cho alert-success).
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

    // Trang thai loading tren nut submit - chan double-submit (quan trong nhat o Checkout,
    // tranh khach bam nhieu lan tao trung don hang).
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

    // Toggle Menu dieu huong tren mobile.
    var navToggle = document.querySelector('[data-nav-toggle]');
    var siteNav = document.querySelector('.site-nav');

    if (navToggle && siteNav) {
        navToggle.addEventListener('click', function () {
            siteNav.classList.toggle('is-open');
        });
    }

    // Quantity stepper o trang chi tiet San pham (shop.show) - chi tang/giam gia tri
    // input[type=number] da co san, khong doi ten field/gia tri submit qua form.
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
