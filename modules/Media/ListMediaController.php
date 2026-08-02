<?php

declare(strict_types=1);

namespace Modules\Media;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/** GET /media - list scoped tenant hien tai, khong pagination (dung tien le ListPagesController). */
final class ListMediaController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('media.view')) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        $media = $this->database->select(
            'SELECT id, file_name, path, mime_type, size, alt_text, title, caption, created_at
             FROM media WHERE tenant_id = ?',
            [$this->tenantManager->id()]
        );

        return Response::json([
            'success' => true,
            'data' => $media,
            'message' => '',
            'errors' => [],
        ]);
    }
}
