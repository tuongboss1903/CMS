<?php

declare(strict_types=1);

namespace Modules\Seo;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;

/**
 * GET /seo/{entity_type}/{entity_id} - entity_type chi ho tro 'page' (post/product chua ton tai).
 * Chua co seo_meta KHONG phai loi - tra success=true, data=null. 404 chi khi entity_type khong
 * ho tro hoac entity khong ton tai/khong thuoc tenant hien tai.
 */
final class ShowSeoMetaController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly Validator $validator,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('seo.view')) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        $entityType = (string) $request->routeParam('entity_type');
        $entityId = (int) $request->routeParam('entity_id');

        $entityResult = $this->validator->validate(
            ['entity_type' => $entityType, 'entity_id' => $entityId],
            ['entity_type' => 'required|in:page', 'entity_id' => 'required|integer']
        );

        if ($entityResult->fails()) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Not Found',
                'errors' => [],
            ], 404);
        }

        $siteId = $this->tenantManager->id();

        $page = $this->database->selectOne(
            'SELECT id FROM pages WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$entityId, $siteId]
        );

        if ($page === null) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Not Found',
                'errors' => [],
            ], 404);
        }

        $meta = $this->database->selectOne(
            'SELECT * FROM seo_meta WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?',
            [$siteId, $entityType, $entityId]
        );

        if ($meta === null) {
            return Response::json([
                'success' => true,
                'data' => null,
                'message' => '',
                'errors' => [],
            ]);
        }

        $meta['schema_data'] = $meta['schema_data'] !== null ? \json_decode((string) $meta['schema_data'], true) : null;

        return Response::json([
            'success' => true,
            'data' => $meta,
            'message' => '',
            'errors' => [],
        ]);
    }
}
