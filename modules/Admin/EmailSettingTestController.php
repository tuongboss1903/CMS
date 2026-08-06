<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Http\Request;
use Core\Http\Response;
use Core\Mail\Mailer;
use Core\Session;
use Core\Validator;

/** POST /admin/email-settings/test - Phase 24 (CMS-081). Gui 1 email thu qua Mailer that (khong mock) de xac minh cau hinh SMTP dang hieu luc (`config/mail.php`) hoat dong dung. */
final class EmailSettingTestController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Mailer $mailer,
        private readonly Session $session,
        private readonly Validator $validator,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('settings.manage')) {
            return Response::html('403 Forbidden', 403);
        }

        $data = $request->all();
        $result = $this->validator->validate($data, ['to' => 'required|email']);

        if ($result->fails()) {
            $this->session->flash('flash_error', 'Địa chỉ email không hợp lệ.');

            return Response::redirect('/admin/email-settings');
        }

        $sent = $this->mailer->send(
            (string) $data['to'],
            'Email thử nghiệm cấu hình SMTP',
            'emails.test_email',
            ['sent_at' => \date('Y-m-d H:i:s')]
        );

        $this->session->flash(
            $sent ? 'flash_success' : 'flash_error',
            $sent ? 'Đã gửi email thử thành công tới ' . $data['to'] . '.' : 'Gửi email thử thất bại - kiểm tra lại cấu hình SMTP trong .env.'
        );

        return Response::redirect('/admin/email-settings');
    }
}
