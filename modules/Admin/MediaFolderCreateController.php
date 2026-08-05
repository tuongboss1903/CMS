<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;

/**
 * POST /admin/media/folders - tao Media Folder (media_folders.parent_id NULL = cap goc). Tai su
 * dung permission "media.upload" (khong tao permission moi - quan ly Folder la mot phan cua quan
 * ly Media, cung nhom quyen voi tao/tai file len).
 */
final class MediaFolderCreateController
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
        if (!$this->authorization->can('media.upload')) {
            return Response::html('403 Forbidden', 403);
        }

        $data = $request->all();

        $result = $this->validator->validate($data, [
            'name' => 'required|string|max:150',
        ]);

        if ($result->fails()) {
            return Response::redirect('/admin/media');
        }

        $siteId = $this->tenantManager->id();
        $parentId = null;

        if (!empty($data['parent_id'])) {
            $parent = $this->database->selectOne(
                'SELECT id FROM media_folders WHERE id = ? AND tenant_id = ?',
                [(int) $data['parent_id'], $siteId]
            );

            $parentId = $parent !== null ? (int) $parent['id'] : null;
        }

        $this->database->insert(
            'INSERT INTO media_folders (tenant_id, parent_id, name) VALUES (?, ?, ?)',
            [$siteId, $parentId, (string) $data['name']]
        );

        return Response::redirect('/admin/media');
    }
}
