<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Http\Request;
use Core\Http\Response;
use Core\SystemAdminAuth;

/** POST /system-admin/logout - CSRF bat buoc qua route group, khong GET logout. */
final class LogoutController
{
    public function __construct(private readonly SystemAdminAuth $auth)
    {
    }

    public function handle(Request $request): Response
    {
        $this->auth->logout();

        return Response::redirect('/system-admin/login');
    }
}
