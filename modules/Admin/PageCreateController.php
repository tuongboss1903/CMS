<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Auth;
use Core\Authorization;
use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;
use Modules\Page\Actions\CreatePageAction;
use Modules\Page\Actions\PageValidationException;

/**
 * POST /admin/pages - logic nghiep vu (validate + INSERT) dung chung qua Actions\CreatePageAction
 * voi Modules\Page\CreatePageController (Pilot Action Class Pattern, Phase 6) - Controller nay
 * chi con trach nhiem HTTP: check permission, goi Action, dinh dang Response HTML/redirect. content
 * gui len dang content[html] (Quill.js), luu dung quy uoc {"html": "..."} (Owner Decision Phase 3).
 */
final class PageCreateController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly CreatePageAction $action,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('page.create')) {
            return Response::html('403 Forbidden', 403);
        }

        $data = $request->all();

        try {
            $this->action->execute($data, $this->auth->id());
        } catch (PageValidationException $exception) {
            return $this->renderWithErrors($exception->errors(), $data);
        }

        return Response::redirect('/admin/pages');
    }

    /**
     * @param array<string, list<string>> $errors
     * @param array<string, mixed> $data
     */
    private function renderWithErrors(array $errors, array $data): Response
    {
        $parents = $this->database->select(
            'SELECT id, title FROM pages WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY title ASC',
            [$this->tenantManager->id()]
        );

        $html = $this->view->render('admin.pages.pages.create', [
            'parents' => $parents,
            'errors' => $errors,
            'old' => [
                'title' => (string) ($data['title'] ?? ''),
                'slug' => (string) ($data['slug'] ?? ''),
                'content_html' => (string) ($data['content']['html'] ?? ''),
                'template' => (string) ($data['template'] ?? ''),
                'parent_id' => (string) ($data['parent_id'] ?? ''),
            ],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
