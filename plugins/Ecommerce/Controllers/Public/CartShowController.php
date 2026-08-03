<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Controllers\Public;

use Core\Csrf;
use Core\Http\Request;
use Core\Http\Response;
use Core\Session;
use Core\View;
use Plugins\Ecommerce\Services\CartService;

/** GET /cart */
final class CartShowController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        $html = $this->view->render('cart.index', [
            'items' => $this->cartService->items(),
            'total' => $this->cartService->total(),
            'flash_success' => $this->session->isStarted() ? $this->session->getFlash('flash_success') : null,
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
