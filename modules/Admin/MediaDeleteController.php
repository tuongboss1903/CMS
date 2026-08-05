<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * POST /admin/media/{id}/delete - khong DELETE method. Copy logic tu Modules\Media\DeleteMediaController
 * (transaction DELETE + tru storage_used_bytes, unlink file SAU KHI commit thanh cong).
 */
final class MediaDeleteController
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
        if (!$this->authorization->can('media.delete')) {
            return Response::html('403 Forbidden', 403);
        }

        $mediaId = (int) $request->routeParam('id');
        $siteId = $this->tenantManager->id();

        $media = $this->database->selectOne(
            'SELECT id, path, size FROM media WHERE id = ? AND tenant_id = ?',
            [$mediaId, $siteId]
        );

        if ($media === null) {
            return Response::html('404 Not Found', 404);
        }

        // Doc truoc list variant (vd thumbnail) de unlink file that SAU KHI transaction commit -
        // row media_variants tu dong bi xoa qua FK CASCADE, nhung file vat ly tren disk thi khong.
        $variants = $this->database->select(
            'SELECT path FROM media_variants WHERE media_id = ?',
            [$mediaId]
        );

        $this->database->transaction(function (Database $db) use ($mediaId, $siteId, $media): void {
            $db->statement('DELETE FROM media WHERE id = ?', [$mediaId]);
            $db->statement(
                'UPDATE sites SET storage_used_bytes = storage_used_bytes - ? WHERE id = ?',
                [(int) $media['size'], $siteId]
            );
        });

        $fullPath = \rtrim($this->storagePath, '/\\') . DIRECTORY_SEPARATOR . $media['path'];

        if (\is_file($fullPath)) {
            @\unlink($fullPath);
        }

        foreach ($variants as $variant) {
            $variantPath = \rtrim($this->storagePath, '/\\') . DIRECTORY_SEPARATOR . $variant['path'];

            if (\is_file($variantPath)) {
                @\unlink($variantPath);
            }
        }

        return Response::redirect('/admin/media');
    }
}
