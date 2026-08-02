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

        $media = $this->database->select(
            'SELECT id, file_name, path, mime_type, size, alt_text, title, caption, created_at
             FROM media WHERE tenant_id = ?',
            [$this->tenantManager->id()]
        );

        $html = $this->view->render('admin.pages.media.list', [
            'media' => $media,
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
