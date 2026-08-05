<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Controllers\Admin;

use Core\Authorization;
use Core\Database;
use Core\Hook;
use Core\Http\Request;
use Core\Http\Response;
use Core\Mail\Mailer;
use Core\Session;
use Plugins\Ecommerce\Actions\EcommerceValidationException;
use Plugins\Ecommerce\Actions\UpdateOrderStatusAction;

/** POST /admin/orders/{id}/status - Phase 20 (CMS-057): ban hook "order.shipped" khi chuyen sang trang thai "shipped" (Hooks.php dang ky listener gui email khach hang). */
final class OrderUpdateStatusController
{
    public function __construct(
        private readonly UpdateOrderStatusAction $action,
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly Hook $hook,
        private readonly Mailer $mailer,
        private readonly Session $session,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('order.update_status')) {
            return Response::html('403 Forbidden', 403);
        }

        $orderId = (int) $request->routeParam('id');
        $newStatus = (string) $request->input('status', '');

        try {
            $this->action->execute($orderId, $newStatus);
        } catch (EcommerceValidationException $exception) {
            $this->session->flash('flash_error', $exception->getMessage());

            return Response::redirect("/admin/orders/{$orderId}");
        }

        if ($newStatus === 'shipped') {
            $order = $this->database->selectOne('SELECT id, order_number, guest_email FROM orders WHERE id = ?', [$orderId]);

            if ($order !== null) {
                $this->hook->do('order.shipped', $order, $this->mailer);
            }
        }

        $this->session->flash('flash_success', 'Đã cập nhật trạng thái đơn hàng.');

        return Response::redirect("/admin/orders/{$orderId}");
    }
}
