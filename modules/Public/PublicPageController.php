<?php

declare(strict_types=1);

namespace Modules\Public;

use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;

/**
 * GET /{slug} - render page cong khai theo slug cua tenant hien tai. Khong Authorization::can()
 * (public, khong yeu cau dang nhap). Cross-tenant/draft/deleted deu tra 404 giong nhau (an danh
 * su ton tai - cung nguyen tac da dung o User/Role/Page Module).
 *
 * Reserved slug (login, users, roles, pages, dashboard...) co the khong truy cap duoc cong khai
 * vi route Admin dang ky truoc va khop pattern 1-segment truoc - da ghi nhan la Technical Debt
 * chap nhan theo Owner Decision (CMS-044), khong xu ly trong scope nay.
 */
final class PublicPageController
{
    public function __construct(
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        $slug = (string) $request->routeParam('slug');

        $page = $this->database->selectOne(
            'SELECT title, content, template FROM pages
             WHERE tenant_id = ? AND slug = ? AND status = ? AND deleted_at IS NULL',
            [$this->tenantManager->id(), $slug, 'published']
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
