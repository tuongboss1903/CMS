<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Http\Request;
use Core\Http\Response;
use Core\Validator;
use Modules\Settings\SettingManager;

/** POST /admin/system-settings - Phase 17 (CMS-054). Upsert qua SettingManager::set() (tu invalidate Cache). */
final class SystemSettingSaveController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly SettingManager $settingManager,
        private readonly Validator $validator,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('settings.manage')) {
            return Response::html('403 Forbidden', 403);
        }

        $data = $request->all();

        $result = $this->validator->validate($data, [
            'key' => 'required|string',
            'setting_group' => 'nullable|string',
            'value' => 'nullable|string',
        ]);

        if ($result->fails()) {
            return Response::redirect('/admin/system-settings');
        }

        $group = (string) ($data['setting_group'] !== '' && $data['setting_group'] !== null ? $data['setting_group'] : 'general');
        $encrypted = ($data['is_encrypted'] ?? '') === '1';

        $this->settingManager->set(
            (string) $data['key'],
            (string) ($data['value'] ?? ''),
            $group,
            $encrypted
        );

        return Response::redirect('/admin/system-settings');
    }
}
