<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;

/** GET /admin/media - copy logic tu Modules\Media\ListMediaController, tra HTML Grid. */
final class MediaListController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('media.view')) {
            return Response::html('403 Forbidden', 403);
        }

        $siteId = $this->tenantManager->id();
        $search = \trim((string) ($request->query('q') ?? ''));
        $type = \trim((string) ($request->query('type') ?? ''));
        $folderParam = (string) ($request->query('folder_id') ?? '');
        $folderId = $folderParam !== '' ? (int) $folderParam : null;

        $conditions = ['tenant_id = ?'];
        $bindings = [$siteId];

        if ($search !== '') {
            $conditions[] = 'file_name LIKE ?';
            $bindings[] = '%' . $search . '%';
        }

        if ($type !== '') {
            $conditions[] = 'mime_type LIKE ?';
            $bindings[] = $type . '%';
        }

        if ($folderParam !== '') {
            $conditions[] = $folderId === 0 ? 'folder_id IS NULL' : 'folder_id = ?';

            if ($folderId !== 0) {
                $bindings[] = $folderId;
            }
        }

        $where = \implode(' AND ', $conditions);

        $media = $this->database->select(
            "SELECT id, file_name, path, mime_type, size, alt_text, title, caption, folder_id, created_at
             FROM media WHERE {$where} ORDER BY created_at DESC",
            $bindings
        );

        $folders = $this->database->select(
            'SELECT id, parent_id, name FROM media_folders WHERE tenant_id = ? ORDER BY name ASC',
            [$siteId]
        );

        $html = $this->view->render('admin.pages.media.list', [
            'breadcrumb_items' => [['label' => 'Media']],
            'media' => $media,
            'folders' => $folders,
            'csrf_token' => $this->csrf->token(),
            'filters' => ['q' => $search, 'type' => $type, 'folder_id' => $folderParam],
        ]);

        return Response::html($html);
    }
}
