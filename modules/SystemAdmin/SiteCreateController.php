<?php

declare(strict_types=1);

namespace Modules\SystemAdmin;

use Core\Csrf;
use Core\Database;
use Core\Database\QueryException;
use Core\Http\Request;
use Core\Http\Response;
use Core\SystemAdminAuth;
use Core\Validator;
use Core\View;

/** POST /system-admin/sites - tao site + domain chinh trong 1 transaction (thay bin/bootstrap.php cho site thu 2+). */
final class SiteCreateController
{
    public function __construct(
        private readonly SystemAdminAuth $auth,
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly Validator $validator,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/system-admin/login');
        }

        $data = $request->all();

        $result = $this->validator->validate($data, [
            'name' => 'required|string',
            'domain' => 'required|string',
        ]);

        if ($result->fails()) {
            return $this->renderWithErrors($result->errors(), $data);
        }

        $name = (string) $data['name'];
        $domain = \strtolower(\trim((string) $data['domain']));

        try {
            $this->database->transaction(function (Database $db) use ($name, $domain): void {
                $db->insert('INSERT INTO sites (name) VALUES (?)', [$name]);
                $siteId = (int) $db->connection()->lastInsertId();

                $db->insert(
                    'INSERT INTO site_domains (site_id, domain, is_primary) VALUES (?, ?, 1)',
                    [$siteId, $domain]
                );
            });
        } catch (QueryException $exception) {
            return $this->renderWithErrors(['domain' => ['Domain da duoc su dung.']], $data);
        }

        return Response::redirect('/system-admin/sites');
    }

    /**
     * @param array<string, list<string>> $errors
     * @param array<string, mixed> $data
     */
    private function renderWithErrors(array $errors, array $data): Response
    {
        $html = $this->view->render('system_admin.pages.sites.create', [
            'errors' => $errors,
            'old' => ['name' => (string) ($data['name'] ?? ''), 'domain' => (string) ($data['domain'] ?? '')],
            'csrf_token' => $this->csrf->token(),
            'current_user_name' => $this->auth->admin()['name'] ?? null,
        ]);

        return Response::html($html);
    }
}
