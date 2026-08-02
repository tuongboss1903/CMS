<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Auth;
use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * POST /admin/media - copy logic tu Modules\Media\UploadMediaController (validate size/mime,
 * move_uploaded_file()/rename() fallback cho CLI/test, transaction INSERT + cong don
 * storage_used_bytes, don file neu transaction loi). Loi tra ve redirect /admin/media (khong co
 * trang Upload rieng - Modal tren chinh list.php), khac PageCreateController (co trang Create
 * rieng nen render lai form voi errors).
 */
final class MediaUploadController
{
    private const MAX_SIZE_BYTES = 5 * 1024 * 1024;

    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
    ];

    public function __construct(
        private readonly Authorization $authorization,
        private readonly Auth $auth,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly string $storagePath = __DIR__ . '/../../storage/app/media',
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('media.upload')) {
            return Response::html('403 Forbidden', 403);
        }

        $file = $request->file('file');

        if ($file === null || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return Response::redirect('/admin/media');
        }

        $size = (int) $file['size'];

        if ($size > self::MAX_SIZE_BYTES) {
            return Response::redirect('/admin/media');
        }

        $mimeType = (string) $file['type'];

        if (!\in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return Response::redirect('/admin/media');
        }

        $siteId = $this->tenantManager->id();
        $originalName = (string) $file['name'];
        $extension = \pathinfo($originalName, PATHINFO_EXTENSION);
        $uniqueName = \uniqid('media_', true) . ($extension !== '' ? '.' . $extension : '');

        $tenantDir = \rtrim($this->storagePath, '/\\') . DIRECTORY_SEPARATOR . $siteId;

        if (!\is_dir($tenantDir) && !\mkdir($tenantDir, 0755, true) && !\is_dir($tenantDir)) {
            return Response::redirect('/admin/media');
        }

        $relativePath = $siteId . '/' . $uniqueName;
        $destination = $tenantDir . DIRECTORY_SEPARATOR . $uniqueName;

        $tmpName = (string) $file['tmp_name'];
        $moved = \is_uploaded_file($tmpName)
            ? \move_uploaded_file($tmpName, $destination)
            : \rename($tmpName, $destination);

        if (!$moved) {
            return Response::redirect('/admin/media');
        }

        try {
            $this->database->transaction(function (Database $db) use ($originalName, $relativePath, $mimeType, $size, $siteId): void {
                $db->insert(
                    'INSERT INTO media (tenant_id, file_name, path, mime_type, size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)',
                    [$siteId, $originalName, $relativePath, $mimeType, $size, $this->auth->id()]
                );

                $db->statement(
                    'UPDATE sites SET storage_used_bytes = storage_used_bytes + ? WHERE id = ?',
                    [$size, $siteId]
                );
            });
        } catch (\Throwable $exception) {
            @\unlink($destination);

            return Response::redirect('/admin/media');
        }

        return Response::redirect('/admin/media');
    }
}
