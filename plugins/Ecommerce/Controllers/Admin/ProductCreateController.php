<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Controllers\Admin;

use Core\Authorization;
use Core\Csrf;
use Core\Database;
use Core\Database\QueryException;
use Core\TenantManager;
use Core\Http\Request;
use Core\Http\Response;
use Core\Validator;
use Core\View;
use Plugins\Ecommerce\Services\ProductService;

/** POST /admin/products */
final class ProductCreateController
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
        if (!$this->authorization->can('product.create')) {
            return Response::html('403 Forbidden', 403);
        }

        $data = $request->all();
        $result = $this->validator->validate($data, [
            'name' => 'required|string',
            'slug' => 'required|string',
            'price' => 'required|numeric',
            'stock_quantity' => 'nullable|integer',
            'image_id' => 'nullable|integer',
        ]);

        if ($result->fails()) {
            return $this->renderWithErrors($result->errors(), $data);
        }

        $tenantId = $this->tenantManager->id();
        $imageId = $this->resolveImageId($data, (string) $tenantId);

        if ($imageId === false) {
            return $this->renderWithErrors(['image_id' => ['Anh khong hop le.']], $data);
        }

        try {
            $this->database->insert(
                'INSERT INTO products (tenant_id, name, slug, description, category, price, sku, stock_quantity, status, image_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $tenantId,
                    (string) $data['name'],
                    (string) $data['slug'],
                    (string) ($data['description'] ?? ''),
                    ($data['category'] ?? '') !== '' ? (string) $data['category'] : null,
                    (float) $data['price'],
                    ($data['sku'] ?? '') !== '' ? (string) $data['sku'] : null,
                    (int) ($data['stock_quantity'] ?? 0),
                    ($data['status'] ?? 'draft') === 'published' ? 'published' : 'draft',
                    $imageId,
                ]
            );
        } catch (QueryException) {
            return $this->renderWithErrors(['slug' => ['Slug da ton tai.']], $data);
        }

        $this->productService->forgetPublishedListCache((string) $tenantId);

        return Response::redirect('/admin/products');
    }

    /**
     * @param array<string, mixed> $data
     * @return int|null|false null = khong chon anh, false = image_id gui len khong hop le/khac tenant
     */
    private function resolveImageId(array $data, string $tenantId): int|null|false
    {
        if (($data['image_id'] ?? '') === '' || ($data['image_id'] ?? null) === null) {
            return null;
        }

        $imageId = (int) $data['image_id'];
        $media = $this->database->selectOne('SELECT id FROM media WHERE id = ? AND tenant_id = ?', [$imageId, $tenantId]);

        return $media !== null ? $imageId : false;
    }

    /**
     * @param array<string, list<string>> $errors
     * @param array<string, mixed> $data
     */
    private function renderWithErrors(array $errors, array $data): Response
    {
        $html = $this->view->render('admin.pages.ecommerce.products.create', [
            'errors' => $errors,
            'old' => $data,
            'images' => $this->database->select('SELECT id, file_name FROM media WHERE tenant_id = ? ORDER BY created_at DESC', [$this->tenantManager->id()]),
            'breadcrumb_items' => [['label' => 'Sản phẩm', 'url' => '/admin/products'], ['label' => 'Thêm mới']],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
