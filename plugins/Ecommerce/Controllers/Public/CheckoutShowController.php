<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Controllers\Public;

use Core\Csrf;
use Core\Http\Request;
use Core\Http\Response;
use Core\View;
use Plugins\Ecommerce\Services\CartService;

/** GET /checkout - gio hang rong thi quay ve /cart (khong hien form dat hang vo nghia). */
final class CheckoutShowController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly Csrf $csrf,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if ($this->cartService->isEmpty()) {
            return Response::redirect('/cart');
        }

        $html = $this->view->render('checkout.index', [
            'items' => $this->cartService->items(),
            'total' => $this->cartService->total(),
            'errors' => [],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
