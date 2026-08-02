<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * GET /admin/media/{id}/file - phuc vu byte file tu storage/app/media cho preview trong Admin
 * Grid. Chi dung trong Admin (media.view), khong phai route Public - Public van chua co Media URL
 * (Owner Decision hoan tai Public Website Polish). $storagePath mac dinh tro storage/app/media
 * that, test override qua Container::singleton() voi thu muc temp rieng, dung pattern
 * UploadMediaController/DeleteMediaController.
 */
final class MediaFileController
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
            'SELECT path, mime_type FROM media WHERE id = ? AND tenant_id = ?',
            [$mediaId, $this->tenantManager->id()]
        );

        if ($media === null) {
            return Response::html('404 Not Found', 404);
        }

        $fullPath = \rtrim($this->storagePath, '/\\') . DIRECTORY_SEPARATOR . $media['path'];

        if (!\is_file($fullPath)) {
            return Response::html('404 Not Found', 404);
        }

        $contents = \file_get_contents($fullPath);

        if ($contents === false) {
            return Response::html('404 Not Found', 404);
        }

        return new Response($contents, 200, ['Content-Type' => (string) $media['mime_type']]);
    }
}
