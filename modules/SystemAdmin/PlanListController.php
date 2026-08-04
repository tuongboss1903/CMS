<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\SystemAdminAuth;
use Core\View;

/** GET /system-admin/plans - danh sach goi dich vu, kem so site dang gan cho tung goi. */
final class PlanListController
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

        $plans = $this->database->select('SELECT * FROM plans ORDER BY price_vnd ASC, id ASC');
        $siteCounts = $this->database->select('SELECT plan_id, COUNT(*) as c FROM sites WHERE plan_id IS NOT NULL GROUP BY plan_id');

        $siteCountByPlan = [];

        foreach ($siteCounts as $row) {
            $siteCountByPlan[(int) $row['plan_id']] = (int) $row['c'];
        }

        foreach ($plans as &$plan) {
            $plan['site_count'] = $siteCountByPlan[(int) $plan['id']] ?? 0;
        }

        $html = $this->view->render('system_admin.pages.plans.list', [
            'plans' => $plans,
            'csrf_token' => $this->csrf->token(),
            'current_user_name' => $this->auth->admin()['name'] ?? null,
        ]);

        return Response::html($html);
    }
}
