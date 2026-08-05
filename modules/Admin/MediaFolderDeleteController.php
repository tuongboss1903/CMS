<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * POST /admin/media/folders/{id}/delete - xoa Folder. Media ben trong KHONG bi xoa theo (folder_id
 * khong FK cung - xem migration 2026_08_22_000001) - tu tay UPDATE media SET folder_id = NULL truoc
 * khi xoa row Folder, tranh gia tri folder_id mo coi tro toi Folder da khong con ton tai. Folder con
 * (media_folders.parent_id) da co FK cung ON DELETE CASCADE (bang moi tao, chua co du lieu that luc
 * thiet ke) nen tu dong bi xoa theo o tang DB.
 */
final class MediaFolderDeleteController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('media.delete')) {
            return Response::html('403 Forbidden', 403);
        }

        $folderId = (int) $request->routeParam('id');
        $siteId = $this->tenantManager->id();

        $folder = $this->database->selectOne(
            'SELECT id FROM media_folders WHERE id = ? AND tenant_id = ?',
            [$folderId, $siteId]
        );

        if ($folder === null) {
            return Response::html('404 Not Found', 404);
        }

        $this->database->transaction(function (Database $db) use ($folderId): void {
            $db->statement('UPDATE media SET folder_id = NULL WHERE folder_id = ?', [$folderId]);
            $db->statement('DELETE FROM media_folders WHERE id = ?', [$folderId]);
        });

        return Response::redirect('/admin/media');
    }
}
