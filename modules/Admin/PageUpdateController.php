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
use Modules\Page\Actions\PageNotFoundException;
use Modules\Page\Actions\PageValidationException;
use Modules\Page\Actions\UpdatePageAction;

/**
 * POST /admin/pages/{id} - khong PATCH (khong Method Spoofing). Logic nghiep vu dung chung qua
 * Actions\UpdatePageAction voi Modules\Page\EditPageController (Pilot Action Class Pattern,
 * Phase 6).
 */
final class PageUpdateController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly UpdatePageAction $action,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('page.update')) {
            return Response::html('403 Forbidden', 403);
        }

        $pageId = (int) $request->routeParam('id');
        $data = $request->all();

        try {
            $this->action->execute($pageId, $data);
        } catch (PageNotFoundException) {
            return Response::html('404 Not Found', 404);
        } catch (PageValidationException $exception) {
            return $this->renderWithErrors($pageId, $exception->errors(), $data);
        }

        return Response::redirect('/admin/pages');
    }

    /**
     * @param array<string, list<string>> $errors
     * @param array<string, mixed> $data
     */
    private function renderWithErrors(int $pageId, array $errors, array $data): Response
    {
        $siteId = $this->tenantManager->id();

        $parents = $this->database->select(
            'SELECT id, title FROM pages WHERE tenant_id = ? AND deleted_at IS NULL AND id != ? ORDER BY title ASC',
            [$siteId, $pageId]
        );

        $html = $this->view->render('admin.pages.pages.edit', [
            'page' => ['id' => $pageId],
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
