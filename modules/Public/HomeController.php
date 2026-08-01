<?php

declare(strict_types=1);

namespace Modules\Public;

use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;

/**
 * GET / - render page danh dau is_homepage cua tenant hien tai. Khong Authorization::can()
 * (public, khong yeu cau dang nhap). Chua co homepage -> 404 (khong phai loi he thong).
 */
final class HomeController
{
    public function __construct(
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        $page = $this->database->selectOne(
            'SELECT title, content, template FROM pages
             WHERE tenant_id = ? AND is_homepage = 1 AND status = ? AND deleted_at IS NULL',
            [$this->tenantManager->id(), 'published']
        );

        if ($page === null) {
            return Response::html('404 Not Found', 404);
        }

        return $this->render($page);
    }

    /** @param array{title: string, content: string|null, template: string|null} $page */
    private function render(array $page): Response
    {
        $templateName = $page['template'] !== null && $page['template'] !== ''
            ? "pages.{$page['template']}"
            : 'pages.default';

        if (!$this->view->exists($templateName)) {
            $templateName = 'pages.default';
        }

        $html = $this->view->render($templateName, [
            'title' => $page['title'],
            'content' => \json_decode($page['content'] ?? 'null', true),
        ]);

        return Response::html($html);
    }
}
