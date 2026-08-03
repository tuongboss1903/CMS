<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Controllers\Public;

use Core\Csrf;
use Core\Http\Request;
use Core\Http\Response;
use Core\Session;
use Core\TenantManager;
use Core\View;
use Plugins\Ecommerce\Services\ProductService;

/** GET /shop - danh sach san pham "published" cong khai, Cache-aware qua ProductService. */
final class ShopIndexController
{
    public function __construct(
        private readonly Csrf $csrf,
        private readonly ProductService $productService,
        private readonly Session $session,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        $products = $this->productService->publishedList((string) $this->tenantManager->id());

        $html = $this->view->render('shop.index', [
            'products' => $products,
            'cart_error' => $this->session->isStarted() ? $this->session->getFlash('cart_error') : null,
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
