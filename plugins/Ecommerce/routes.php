<?php

declare(strict_types=1);

use Core\Middleware\CsrfMiddleware;
use Core\Router;
use Plugins\Ecommerce\Controllers\Admin\OrderListController;
use Plugins\Ecommerce\Controllers\Admin\OrderShowController;
use Plugins\Ecommerce\Controllers\Admin\OrderUpdateStatusController;
use Plugins\Ecommerce\Controllers\Admin\PaymentListController;
use Plugins\Ecommerce\Controllers\Admin\PaymentSettingListController;
use Plugins\Ecommerce\Controllers\Admin\PaymentSettingToggleController;
use Plugins\Ecommerce\Controllers\Admin\ProductCreateController;
use Plugins\Ecommerce\Controllers\Admin\ProductDeleteController;
use Plugins\Ecommerce\Controllers\Admin\ProductListController;
use Plugins\Ecommerce\Controllers\Admin\ProductShowCreateController;
use Plugins\Ecommerce\Controllers\Admin\ProductShowEditController;
use Plugins\Ecommerce\Controllers\Admin\ProductUpdateController;
use Plugins\Ecommerce\Controllers\Public\CartAddController;
use Plugins\Ecommerce\Controllers\Public\CartRemoveController;
use Plugins\Ecommerce\Controllers\Public\CartShowController;
use Plugins\Ecommerce\Controllers\Public\CheckoutPlaceOrderController;
use Plugins\Ecommerce\Controllers\Public\CheckoutShowController;
use Plugins\Ecommerce\Controllers\Public\PaymentReturnController;
use Plugins\Ecommerce\Controllers\Public\PaymentWebhookController;
use Plugins\Ecommerce\Controllers\Public\ShopIndexController;
use Plugins\Ecommerce\Controllers\Public\ShopShowController;
use Plugins\Ecommerce\EcommercePluginGuardMiddleware;

/**
 * @var Router $router - trong scope qua closure "plugin.routes.register" (xem Hooks.php cua chinh
 *     plugin nay + core/Application.php::boot()). Toan bo route cua Plugin nam trong 1 group duy
 *     nhat duoc boc EcommercePluginGuardMiddleware - tenant chua kich hoat plugin nhan 404 o MOI
 *     route ben duoi (Admin lan Public), khong can lap lai kiem tra trong tung Controller.
 */
$router->group(['middleware' => [EcommercePluginGuardMiddleware::class]], static function (Router $router): void {
    // Admin (GET) - Auth::check()/Authorization::can() tu xu ly trong Controller, dung tien le
    // PageListController (khong AuthMiddleware o day vi class do tra JSON 401, sai flow HTML).
    $router->get('/admin/products', [ProductListController::class, 'handle']);
    $router->get('/admin/products/create', [ProductShowCreateController::class, 'handle']);
    $router->get('/admin/products/{id}/edit', [ProductShowEditController::class, 'handle']);
    $router->get('/admin/orders', [OrderListController::class, 'handle']);
    $router->get('/admin/orders/{id}', [OrderShowController::class, 'handle']);
    $router->get('/admin/payments', [PaymentListController::class, 'handle']);
    $router->get('/admin/payment-settings', [PaymentSettingListController::class, 'handle']);

    // Public (GET)
    $router->get('/shop', [ShopIndexController::class, 'handle']);
    $router->get('/shop/{slug}', [ShopShowController::class, 'handle']);
    $router->get('/cart', [CartShowController::class, 'handle']);
    $router->get('/checkout', [CheckoutShowController::class, 'handle']);
    $router->get('/payment/return/{driver}', [PaymentReturnController::class, 'handle']);

    // Webhook/IPN cong thanh toan - KHONG boc CsrfMiddleware (request tu server cong thanh toan,
    // khong co browser session/form token). Bao ve rieng qua verifyWebhookSignature() cua tung
    // Driver, thuc hien BEN TRONG PaymentWebhookController (xem docblock class do).
    $router->post('/payment/webhook/{driver}', [PaymentWebhookController::class, 'handle']);

    $router->group(['middleware' => [CsrfMiddleware::class]], static function (Router $router): void {
        $router->post('/admin/products', [ProductCreateController::class, 'handle']);
        $router->post('/admin/products/{id}', [ProductUpdateController::class, 'handle']);
        $router->post('/admin/products/{id}/delete', [ProductDeleteController::class, 'handle']);
        $router->post('/admin/orders/{id}/status', [OrderUpdateStatusController::class, 'handle']);
        $router->post('/admin/payment-settings/{key}/toggle', [PaymentSettingToggleController::class, 'handle']);

        $router->post('/cart/add', [CartAddController::class, 'handle']);
        $router->post('/cart/remove', [CartRemoveController::class, 'handle']);
        $router->post('/checkout', [CheckoutPlaceOrderController::class, 'handle']);
    });
});
