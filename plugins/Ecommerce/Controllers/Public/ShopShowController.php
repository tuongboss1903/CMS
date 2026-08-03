<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Controllers\Public;

use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;

/** GET /shop/{slug} */
final class ShopShowController
{
    public function __construct(
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        $slug = (string) $request->routeParam('slug');
        $tenantId = $this->tenantManager->id();

        $product = $this->database->selectOne(
            "SELECT * FROM products WHERE tenant_id = ? AND slug = ? AND status = 'published' AND deleted_at IS NULL",
            [$tenantId, $slug]
        );

        if ($product === null) {
            return Response::html('404 Not Found', 404);
        }

        $variants = $this->database->select(
            'SELECT * FROM product_variants WHERE tenant_id = ? AND product_id = ?',
            [$tenantId, $product['id']]
        );

        $html = $this->view->render('shop.show', [
            'product' => $product,
            'variants' => $variants,
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
