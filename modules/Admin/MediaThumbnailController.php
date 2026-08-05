<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * GET /admin/media/{id}/thumbnail - phuc vu bien the "thumbnail" (media_variants.size_type)
 * neu co, fallback ve file goc neu chua sinh duoc thumbnail (vd file PDF, hoac GD loi luc upload -
 * xem MediaUploadController::generateThumbnail()) - Admin Grid/List luon co anh de hien thi, khong
 * bao gio vo layout vi thieu thumbnail. Controller rieng (khong nhap vao MediaFileController) -
 * dung dung quy uoc 1-class-1-action cua du an.
 */
final class MediaThumbnailController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly string $storagePath = __DIR__ . '/../../storage/app/media',
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('media.view')) {
            return Response::html('403 Forbidden', 403);
        }

        $mediaId = (int) $request->routeParam('id');

        $media = $this->database->selectOne(
            'SELECT id, path, mime_type FROM media WHERE id = ? AND tenant_id = ?',
            [$mediaId, $this->tenantManager->id()]
        );

        if ($media === null) {
            return Response::html('404 Not Found', 404);
        }

        $variant = $this->database->selectOne(
            "SELECT path FROM media_variants WHERE media_id = ? AND size_type = 'thumbnail'",
            [$mediaId]
        );

        $relativePath = $variant['path'] ?? $media['path'];
        $mimeType = $variant !== null ? 'image/jpeg' : (string) $media['mime_type'];

        $fullPath = \rtrim($this->storagePath, '/\\') . DIRECTORY_SEPARATOR . $relativePath;

        if (!\is_file($fullPath)) {
            return Response::html('404 Not Found', 404);
        }

        $contents = \file_get_contents($fullPath);

        if ($contents === false) {
            return Response::html('404 Not Found', 404);
        }

        return (new Response($contents, 200, ['Content-Type' => $mimeType]))
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withCache(3600);
    }
}
