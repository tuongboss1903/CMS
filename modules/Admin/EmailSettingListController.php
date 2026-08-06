<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Config;
use Core\Csrf;
use Core\Http\Request;
use Core\Http\Response;
use Core\Session;
use Core\View;

/**
 * GET /admin/email-settings - Phase 24 (CMS-081). Man hinh CHI ĐỌC hien thi cau hinh SMTP dang
 * hieu luc (doc tu config/mail.php, nguon la getenv() - dung dung quy uoc "cau hinh qua .env,
 * KHONG qua Database" da ap dung xuyen suot du an, xem CLAUDE.md muc config/). KHONG cho sua qua
 * DB o day - MailerDriver duoc dang ky 1 lan duy nhat trong Core\Application::boot() (Container
 * singleton, KHONG phu thuoc Modules\Settings\SettingManager - Core khong duoc phep phu thuoc
 * Modules, xem CLAUDE.md "Kien truc tom tat"), nen bat/tat SMTP qua DB se khong bao gio duoc
 * MailerDriver hien co doc lai giua request. Gia tri thuc cua tinh nang nay la "Gui email thu"
 * (EmailSettingTestController) de xac minh cau hinh .env hien tai co hoat dong that hay khong,
 * khac voi Payment Management (CMS-081) la bat/tat that qua DB vi PaymentGatewaySettings khong
 * nam trong Core.
 */
final class EmailSettingListController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Config $config,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('settings.manage')) {
            return Response::html('403 Forbidden', 403);
        }

        $driver = (string) $this->config->get('mail.default', 'log');
        /** @var array<string, mixed> $smtp */
        $smtp = (array) $this->config->get('mail.drivers.smtp', []);

        $html = $this->view->render('admin.pages.email_settings.show', [
            'driver' => $driver,
            'from_address' => (string) $this->config->get('mail.from.address', ''),
            'from_name' => (string) $this->config->get('mail.from.name', ''),
            'smtp_host' => (string) ($smtp['host'] ?? ''),
            'smtp_port' => (string) ($smtp['port'] ?? ''),
            'smtp_encryption' => (string) ($smtp['encryption'] ?? ''),
            'smtp_username' => (string) ($smtp['username'] ?? ''),
            'smtp_has_password' => !empty($smtp['password']),
            'breadcrumb_items' => [['label' => 'Cấu hình Email']],
            'csrf_token' => $this->csrf->token(),
            'flash_success' => $this->session->getFlash('flash_success'),
            'flash_error' => $this->session->getFlash('flash_error'),
        ]);

        return Response::html($html);
    }
}
