<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Controllers\Admin;

use Core\Authorization;
use Core\Csrf;
use Core\Database;
use Core\Database\QueryException;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;
use Core\View;
use Plugins\Ecommerce\Services\ProductService;

/** POST /admin/products/{id} - khong PATCH (khong Method Spoofing, dung tien le PageUpdateController). */
final class ProductUpdateController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly ProductService $productService,
        private readonly TenantManager $tenantManager,
        private readonly Validator $validator,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('product.update')) {
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

        $data = $request->all();
        $result = $this->validator->validate($data, [
            'name' => 'required|string',
            'slug' => 'required|string',
            'price' => 'required|numeric',
            'stock_quantity' => 'nullable|integer',
        ]);

        if ($result->fails()) {
            return $this->renderWithErrors($productId, $result->errors(), $data);
        }

        try {
            $this->database->update(
                'UPDATE products SET name = ?, slug = ?, description = ?, category = ?, price = ?, sku = ?, stock_quantity = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ? AND tenant_id = ?',
                [
                    (string) $data['name'],
                    (string) $data['slug'],
                    (string) ($data['description'] ?? ''),
                    ($data['category'] ?? '') !== '' ? (string) $data['category'] : null,
                    (float) $data['price'],
                    ($data['sku'] ?? '') !== '' ? (string) $data['sku'] : null,
                    (int) ($data['stock_quantity'] ?? 0),
                    ($data['status'] ?? 'draft') === 'published' ? 'published' : 'draft',
                    $productId,
                    $tenantId,
                ]
            );
        } catch (QueryException) {
            return $this->renderWithErrors($productId, ['slug' => ['Slug da ton tai.']], $data);
        }

        $this->productService->forgetPublishedListCache((string) $tenantId);

        return Response::redirect('/admin/products');
    }

    /**
     * @param array<string, list<string>> $errors
     * @param array<string, mixed> $data
     */
    private function renderWithErrors(int $productId, array $errors, array $data): Response
    {
        $html = $this->view->render('admin.pages.ecommerce.products.edit', [
            'product' => ['id' => $productId],
            'errors' => $errors,
            'old' => $data,
            'breadcrumb_items' => [['label' => 'San pham', 'url' => '/admin/products'], ['label' => 'Sua']],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
