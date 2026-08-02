<?php

declare(strict_types=1);

namespace Modules\Public;

use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;
use Modules\Settings\SiteSettingsManager;

/**
 * GET /{slug} - render page cong khai theo slug cua tenant hien tai. Khong Authorization::can()
 * (public, khong yeu cau dang nhap). Cross-tenant/draft/deleted deu tra 404 giong nhau (an danh
 * su ton tai - cung nguyen tac da dung o User/Role/Page Module).
 *
 * Reserved slug (login, users, roles, pages, dashboard...) co the khong truy cap duoc cong khai
 * vi route Admin dang ky truoc va khop pattern 1-segment truoc - da ghi nhan la Technical Debt
 * chap nhan theo Owner Decision (CMS-044), khong xu ly trong scope nay.
 *
 * Public Website Polish: them SEO meta injection (title/description/canonical/OG/JSON-LD, chi khi
 * co ban ghi seo_meta - Option A, khong tu sinh mac dinh) va Navigation Menu (location_key='header',
 * an han <nav> neu chua co Menu - Owner Decision). Trung lap logic voi HomeController.php co chu
 * dich (Option A, khong tao abstraction moi).
 */
final class PublicPageController
{
    public function __construct(
        private readonly Database $database,
        private readonly SiteSettingsManager $siteSettings,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        $slug = (string) $request->routeParam('slug');
        $tenantId = $this->tenantManager->id();

        $page = $this->database->selectOne(
            'SELECT id, title, content, template FROM pages
             WHERE tenant_id = ? AND slug = ? AND status = ? AND deleted_at IS NULL',
            [$tenantId, $slug, 'published']
        );

        if ($page === null) {
            return $this->render404();
        }

        return $this->render($page);
    }

    /** @param array{id: int|string, title: string, content: string|null, template: string|null} $page */
    private function render(array $page): Response
    {
        $pageId = (int) $page['id'];
        $tenantId = $this->tenantManager->id();

        $templateName = $page['template'] !== null && $page['template'] !== ''
            ? "pages.{$page['template']}"
            : 'pages.default';

        if (!$this->view->exists($templateName)) {
            $templateName = 'pages.default';
        }

        $seo = $this->fetchSeoMeta($tenantId, $pageId);

        $html = $this->view->render($templateName, [
            'title' => $seo['title'] ?? $page['title'],
            'content' => \json_decode($page['content'] ?? 'null', true),
            'seo' => $seo,
            'menu' => $this->fetchNavigation($tenantId, $pageId),
            'site_settings' => $this->siteSettings->get(),
        ]);

        return Response::html($html);
    }

    private function render404(): Response
    {
        if ($this->view->exists('pages.404')) {
            return Response::html($this->view->render('pages.404', [
                'title' => '404 Not Found',
                'site_settings' => $this->siteSettings->get(),
            ]), 404);
        }

        return Response::html('404 Not Found', 404);
    }

    /** @return array<string, mixed>|null */
    private function fetchSeoMeta(int|string|null $tenantId, int $pageId): ?array
    {
        $meta = $this->database->selectOne(
            'SELECT title, description, canonical, schema_type, schema_data
             FROM seo_meta WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?',
            [$tenantId, 'page', $pageId]
        );

        if ($meta === null) {
            return null;
        }

        $meta['schema_data'] = $meta['schema_data'] !== null ? \json_decode((string) $meta['schema_data'], true) : null;

        return $meta;
    }

    /**
     * Location co dinh 'header' (Owner Decision Public Website Polish - PHASE 2). 1 query lay
     * menu_items + 1 query lay slug cac page duoc tham chieu (khong N+1), dung cay bang PHP thuan
     * (cung cach ShowMenuController::buildTree() da lam, khong tai su dung truc tiep - trung lap
     * co chu dich).
     *
     * @return list<array<string, mixed>>
     */
    private function fetchNavigation(int|string|null $tenantId, ?int $activePageId): array
    {
        $menu = $this->database->selectOne(
            'SELECT id FROM menus WHERE tenant_id = ? AND location_key = ?',
            [$tenantId, 'header']
        );

        if ($menu === null) {
            return [];
        }

        $items = $this->database->select(
            'SELECT id, parent_id, label, type, reference_id, url, target, sort_order
             FROM menu_items WHERE menu_id = ? ORDER BY sort_order ASC',
            [(int) $menu['id']]
        );

        if ($items === []) {
            return [];
        }

        $pageIds = \array_values(\array_unique(\array_map(
            static fn (array $item): int => (int) $item['reference_id'],
            \array_filter($items, static fn (array $item): bool => $item['type'] === 'page')
        )));

        $slugById = [];

        if ($pageIds !== []) {
            $placeholders = \implode(',', \array_fill(0, \count($pageIds), '?'));
            $pages = $this->database->select(
                "SELECT id, slug FROM pages WHERE id IN ({$placeholders}) AND tenant_id = ?",
                [...$pageIds, $tenantId]
            );

            foreach ($pages as $row) {
                $slugById[(int) $row['id']] = (string) $row['slug'];
            }
        }

        $resolved = \array_map(function (array $item) use ($slugById, $activePageId): array {
            $referenceId = $item['reference_id'] !== null ? (int) $item['reference_id'] : null;

            return [
                'id' => (int) $item['id'],
                'parent_id' => $item['parent_id'],
                'label' => $item['label'],
                'url' => $item['type'] === 'page'
                    ? '/' . ($slugById[$referenceId] ?? '')
                    : (string) $item['url'],
                'target' => $item['target'],
                'active' => $item['type'] === 'page' && $referenceId !== null && $referenceId === $activePageId,
            ];
        }, $items);

        return $this->buildTree($resolved);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function buildTree(array $items): array
    {
        $byParent = [];

        foreach ($items as $item) {
            $parentKey = $item['parent_id'] === null ? '' : (string) $item['parent_id'];
            $byParent[$parentKey][] = $item;
        }

        return $this->attachChildren($byParent[''] ?? [], $byParent);
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param array<string, list<array<string, mixed>>> $byParent
     * @return list<array<string, mixed>>
     */
    private function attachChildren(array $nodes, array $byParent): array
    {
        foreach ($nodes as &$node) {
            $node['children'] = $this->attachChildren($byParent[(string) $node['id']] ?? [], $byParent);
        }

        return $nodes;
    }
}
