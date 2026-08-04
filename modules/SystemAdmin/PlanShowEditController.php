<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\SystemAdminAuth;
use Core\View;

/** GET /system-admin/plans/{id}/edit - form sua goi dich vu. */
final class PlanShowEditController
{
    public function __construct(
        private readonly SystemAdminAuth $auth,
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/system-admin/login');
        }

        $planId = (int) $request->routeParam('id');
        $plan = $this->database->selectOne('SELECT * FROM plans WHERE id = ?', [$planId]);

        if ($plan === null) {
            return Response::html('404 Not Found', 404);
        }

        $html = $this->view->render('system_admin.pages.plans.edit', [
            'plan' => $plan,
            'errors' => [],
            'old' => $plan,
            'csrf_token' => $this->csrf->token(),
            'current_user_name' => $this->auth->admin()['name'] ?? null,
        ]);

        return Response::html($html);
    }
}
