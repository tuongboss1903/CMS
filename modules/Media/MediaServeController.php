<?php

declare(strict_types=1);

namespace Modules\Media;

use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * GET /media/{filename} - phuc vu file cong khai (khong Authorization::can(), Public route).
 * tenant_id LUON lay tu TenantManager::id() (domain, qua TenantResolverMiddleware) - KHONG BAO
 * GIO nhan tenant_id tu URL/request (chan IDOR - xem Architecture Analysis Phase 5).
 *
 * QUAN TRONG: {filename} khop voi TEN FILE VAT LY DUY NHAT (basename cua media.path, do
 * UploadMediaController tu sinh qua uniqid()) - KHONG PHAI media.file_name (ten goc nguoi dung
 * luc upload, khong duy nhat trong pham vi 1 tenant). Match bang path = "{tenant_id}/{filename}"
 * (bind tham so, khong LIKE, khong noi truc tiep input vao filesystem path - chan Path Traversal).
 * $storagePath mac dinh tro storage/app/media that, test override qua Container::singleton() voi
 * thu muc temp rieng, cung pattern UploadMediaController/DeleteMediaController/MediaFileController.
 */
final class MediaServeController
{
    public function __construct(
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly string $storagePath = __DIR__ . '/../../storage/app/media',
    ) {
    }

    public function handle(Request $request): Response
    {
        $filename = (string) $request->routeParam('filename');
        $tenantId = $this->tenantManager->id();
        $expectedPath = $tenantId . '/' . $filename;

        $media = $this->database->selectOne(
            'SELECT path, mime_type, size FROM media WHERE tenant_id = ? AND path = ?',
            [$tenantId, $expectedPath]
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

        $lastModifiedTimestamp = \filemtime($fullPath);
        $lastModified = $lastModifiedTimestamp !== false
            ? \gmdate('D, d M Y H:i:s', $lastModifiedTimestamp) . ' GMT'
            : \gmdate('D, d M Y H:i:s') . ' GMT';

        $etag = '"' . \md5($tenantId . '|' . $media['path'] . '|' . $media['size']) . '"';

        return (new Response($contents, 200, ['Content-Type' => (string) $media['mime_type']]))
            ->withHeader('ETag', $etag)
            ->withHeader('Last-Modified', $lastModified)
            ->withCache(86400);
    }
}
