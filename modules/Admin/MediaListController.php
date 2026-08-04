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

        $search = \trim((string) ($request->query('q') ?? ''));
        $type = \trim((string) ($request->query('type') ?? ''));

        $conditions = ['tenant_id = ?'];
        $bindings = [$this->tenantManager->id()];

        if ($search !== '') {
            $conditions[] = 'file_name LIKE ?';
            $bindings[] = '%' . $search . '%';
        }

        if ($type !== '') {
            $conditions[] = 'mime_type LIKE ?';
            $bindings[] = $type . '%';
        }

        $where = \implode(' AND ', $conditions);

        $media = $this->database->select(
            "SELECT id, file_name, path, mime_type, size, alt_text, title, caption, created_at
             FROM media WHERE {$where} ORDER BY created_at DESC",
            $bindings
        );

        $html = $this->view->render('admin.pages.media.list', [
            'media' => $media,
            'csrf_token' => $this->csrf->token(),
            'filters' => ['q' => $search, 'type' => $type],
        ]);

        return Response::html($html);
    }
}
