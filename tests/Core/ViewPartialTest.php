<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\View;
use PHPUnit\Framework\TestCase;

/**
 * Unit test cho Phase 18 (UI/UX Admin Dashboard Overhaul, CMS-055) - 5 partial moi trong
 * themes/default/views/admin/partials/*.php. Render doc lap qua View::render() that (khong can
 * Router/Database/Controller) - moi partial KHONG goi extend() nen render() tra ve dung noi dung
 * partial, khong can layout.
 */
final class ViewPartialTest extends TestCase
{
    private const REAL_THEMES_PATH = __DIR__ . '/../../themes';

    private function view(): View
    {
        return new View(self::REAL_THEMES_PATH, 'default', 'default');
    }

    // ---- breadcrumb.php ----

    public function testBreadcrumbRendersItemsWithLinks(): void
    {
        $html = $this->view()->render('admin.partials.breadcrumb', [
            'breadcrumb_items' => [
                ['label' => 'Pages', 'url' => '/admin/pages'],
                ['label' => 'Sua trang'],
            ],
        ]);

        self::assertStringContainsString('href="/admin/pages"', $html);
        self::assertStringContainsString('Pages', $html);
        self::assertStringContainsString('Sua trang', $html);
    }

    public function testBreadcrumbLastItemHasNoLinkEvenIfUrlProvided(): void
    {
        $html = $this->view()->render('admin.partials.breadcrumb', [
            'breadcrumb_items' => [
                ['label' => 'Pages', 'url' => '/admin/pages'],
                ['label' => 'Chi tiet', 'url' => '/admin/pages/1'],
            ],
        ]);

        self::assertStringContainsString('class="crumb-current">Chi tiet<', $html);
        self::assertStringNotContainsString('href="/admin/pages/1"', $html);
    }

    public function testBreadcrumbRendersNothingWhenEmpty(): void
    {
        $html = $this->view()->render('admin.partials.breadcrumb', ['breadcrumb_items' => []]);

        self::assertSame('', \trim($html));
    }

    public function testBreadcrumbEscapesXss(): void
    {
        $html = $this->view()->render('admin.partials.breadcrumb', [
            'breadcrumb_items' => [['label' => '<script>alert(1)</script>']],
        ]);

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    // ---- pagination.php ----

    public function testPaginationRendersPageLinks(): void
    {
        $html = $this->view()->render('admin.partials.pagination', [
            'page' => 1,
            'total_pages' => 3,
            'base_url' => '/admin/audit-logs?',
        ]);

        self::assertStringContainsString('/admin/audit-logs?page=2', $html);
        self::assertStringContainsString('/admin/audit-logs?page=3', $html);
    }

    public function testPaginationHighlightsCurrentPageWithoutLink(): void
    {
        $html = $this->view()->render('admin.partials.pagination', [
            'page' => 2,
            'total_pages' => 3,
            'base_url' => '/admin/audit-logs?',
        ]);

        self::assertStringContainsString('pagination-current">2<', $html);
        self::assertStringNotContainsString('/admin/audit-logs?page=2', $html);
    }

    public function testPaginationRendersNothingWhenSinglePage(): void
    {
        $html = $this->view()->render('admin.partials.pagination', [
            'page' => 1,
            'total_pages' => 1,
            'base_url' => '/admin/audit-logs?',
        ]);

        self::assertSame('', \trim($html));
    }

    // ---- table_filter.php ----

    public function testTableFilterRendersTextField(): void
    {
        $html = $this->view()->render('admin.partials.table_filter', [
            'filter_action' => '/admin/audit-logs',
            'filter_fields' => [
                ['name' => 'date_from', 'label' => 'Tu ngay', 'type' => 'date', 'value' => '2026-08-01'],
            ],
        ]);

        self::assertStringContainsString('name="date_from"', $html);
        self::assertStringContainsString('value="2026-08-01"', $html);
        self::assertStringContainsString('action="/admin/audit-logs"', $html);
    }

    public function testTableFilterRendersSelectFieldWithSelectedOption(): void
    {
        $html = $this->view()->render('admin.partials.table_filter', [
            'filter_action' => '/admin/audit-logs',
            'filter_fields' => [
                [
                    'name' => 'event',
                    'label' => 'Su kien',
                    'type' => 'select',
                    'value' => 'page.created',
                    'options' => [
                        ['value' => 'page.created', 'label' => 'page.created'],
                        ['value' => 'auth.login_success', 'label' => 'auth.login_success'],
                    ],
                ],
            ],
        ]);

        self::assertStringContainsString('<option value="page.created" selected>', $html);
        self::assertStringContainsString('<option value="auth.login_success">', $html);
    }

    public function testTableFilterRendersNothingWhenNoFields(): void
    {
        $html = $this->view()->render('admin.partials.table_filter', ['filter_action' => '/x', 'filter_fields' => []]);

        self::assertSame('', \trim($html));
    }

    // ---- flash_messages.php ----

    public function testFlashMessagesRendersSuccess(): void
    {
        $html = $this->view()->render('admin.partials.flash_messages', ['flash_success' => 'Da luu thanh cong.']);

        self::assertStringContainsString('alert-success', $html);
        self::assertStringContainsString('Da luu thanh cong.', $html);
    }

    public function testFlashMessagesRendersError(): void
    {
        $html = $this->view()->render('admin.partials.flash_messages', ['flash_error' => 'Co loi xay ra.']);

        self::assertStringContainsString('alert-danger', $html);
        self::assertStringContainsString('Co loi xay ra.', $html);
    }

    public function testFlashMessagesRendersNothingWhenEmpty(): void
    {
        $html = $this->view()->render('admin.partials.flash_messages', []);

        self::assertSame('', \trim($html));
    }

    // ---- confirm_modal.php ----

    public function testConfirmModalRendersModalMarkupWithFixedId(): void
    {
        $html = $this->view()->render('admin.partials.confirm_modal', []);

        self::assertStringContainsString('id="confirm-modal"', $html);
        self::assertStringContainsString('data-confirm-message', $html);
        self::assertStringContainsString('data-confirm-accept', $html);
        self::assertStringContainsString('data-modal-close="confirm-modal"', $html);
    }
}
