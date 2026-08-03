<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Controllers\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Plugins\Ecommerce\Services\ProductService;

/** POST /admin/products/{id}/delete - soft-delete (deleted_at), dung tien le PageDeleteController. */
final class ProductDeleteController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly ProductService $productService,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('product.delete')) {
            return Response::html('403 Forbidden', 403);
        }

        $productId = (int) $request->routeParam('id');
        $tenantId = $this->tenantManager->id();

        $product = $this->database->selectOne(
            'SELECT id FROM products WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$productId, $tenantId]
        );

        if ($product === null) {
            return Response::html('404 Not Found', 404);
        }

        $this->database->update(
            'UPDATE products SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?',
            [$productId, $tenantId]
        );

        $this->productService->forgetPublishedListCache((string) $tenantId);

        return Response::redirect('/admin/products');
    }
}
