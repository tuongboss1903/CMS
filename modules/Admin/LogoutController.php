<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Auth;
use Core\Http\Request;
use Core\Http\Response;

/** POST /admin/logout - CSRF bat buoc (qua route group), khong GET logout (Owner Decision CMS-045). */
final class LogoutController
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function handle(Request $request): Response
    {
        $this->auth->logout();

        return Response::redirect('/admin/login');
    }
}
