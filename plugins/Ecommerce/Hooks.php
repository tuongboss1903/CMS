<?php

declare(strict_types=1);

use Core\Hook;
use Core\Mail\Mailer;
use Core\PluginActivationService;
use Core\Router;
use Core\TenantManager;
use Modules\Admin\NotificationService;

/** @var \Core\Hook $hook */

/**
 * Phase 19 (Ecommerce MVP, CMS-056). PluginManager::boot() require file nay voi DUY NHAT $hook
 * trong scope (xem core/PluginManager.php::boot()) - moi dependency khac (Router/TenantManager/
 * PluginActivationService) den qua tham so BO SUNG cua Hook::do()/Hook::apply(), khong phai
 * Container (Plugin khong duoc inject Container truc tiep, dung nguyen tac co lap voi Hook).
 *
 * "plugin.routes.register": nap routes.php cua CHINH plugin nay (PluginManager::boot() TRUOC GIO
 * chi nap Hooks.php, khong nap routes.php nhu ModuleManager - Plugin dau tien can Route nen bo
 * sung diem hook nay o Application::boot(), xem core/Application.php).
 *
 * "admin.menu.items": chi tra ve muc menu khi PLUGIN NAY DANG BAT cho DUNG tenant hien tai (doc
 * qua PluginActivationService, tenant lay tu TenantManager duoc truyen kem) - tranh hien thi link
 * dan toi trang se bi EcommercePluginGuardMiddleware chan (404) neu tenant chua kich hoat.
 */
$hook->action('plugin.routes.register', static function (Router $router): void {
    require __DIR__ . '/routes.php';
});

$hook->filter('admin.menu.items', static function (array $items, ?TenantManager $tenantManager = null, ?PluginActivationService $pluginActivation = null): array {
    if ($tenantManager === null || $pluginActivation === null || !$tenantManager->check()) {
        return $items;
    }

    if (!$pluginActivation->isActive((string) $tenantManager->id(), 'ecommerce')) {
        return $items;
    }

    return [
        ...$items,
        ['label' => 'San pham', 'url' => '/admin/products'],
        ['label' => 'Don hang', 'url' => '/admin/orders'],
    ];
});

/**
 * Phase 20 (CMS-057, Order Notification System). Tai dung nguyen ha tang da co tu Phase 15 (Core\
 * Mail\Mailer, Modules\Admin\NotificationService) - KHONG viet lai co che gui mail/notification
 * moi. Ca 3 listener deu nhan Mailer/NotificationService qua tham so BO SUNG cua Hook::do() (dung
 * nguyen tac da ap dung cho admin.menu.items o tren) - noi ban hook (Controller) da co san Container
 * nen tu truyen dung instance, Hooks.php khong can biet Container.
 *
 * Silent-fail: Mailer::send() da tu silent-fail noi bo (xem docblock Core\Mail\Mailer), khong can
 * boc try/catch rieng o day.
 */
$hook->action('order.created', static function (array $order, ?Mailer $mailer = null, ?NotificationService $notificationService = null): void {
    if ($mailer !== null) {
        $mailer->send(
            (string) $order['guest_email'],
            'Xac nhan don hang ' . $order['order_number'],
            'emails.order_created',
            ['order' => $order]
        );
    }

    if ($notificationService !== null) {
        $notificationService->notifyAdmins(
            'order.created',
            'order',
            (int) $order['id'],
            'Don hang moi',
            'Don hang ' . $order['order_number'] . ' vua duoc tao.',
            'Don hang moi can xu ly',
            'emails.order_created',
            ['order' => $order]
        );
    }
});

$hook->action('order.payment_completed', static function (array $order, ?Mailer $mailer = null): void {
    if ($mailer === null) {
        return;
    }

    $mailer->send(
        (string) $order['guest_email'],
        'Thanh toan thanh cong - Don hang ' . $order['order_number'],
        'emails.order_payment_completed',
        ['order' => $order]
    );
});

$hook->action('order.shipped', static function (array $order, ?Mailer $mailer = null): void {
    if ($mailer === null) {
        return;
    }

    $mailer->send(
        (string) $order['guest_email'],
        'Don hang ' . $order['order_number'] . ' dang duoc van chuyen',
        'emails.order_shipped',
        ['order' => $order]
    );
});
