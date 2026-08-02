<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;
use Modules\Settings\SiteSettingsManager;

/** POST /admin/settings - upsert qua SiteSettingsManager::set(). */
final class SettingUpdateController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly SiteSettingsManager $settings,
        private readonly TenantManager $tenantManager,
        private readonly Validator $validator,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('settings.update')) {
            return Response::html('403 Forbidden', 403);
        }

        $data = $request->all();

        foreach (['default_og_image_id', 'favicon_id'] as $field) {
            if (\array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        $result = $this->validator->validate($data, [
            'site_name' => 'nullable|string|max:150',
            'site_tagline' => 'nullable|string|max:255',
            'default_meta_description' => 'nullable|string|max:500',
            'default_og_image_id' => 'nullable|integer',
            'favicon_id' => 'nullable|integer',
            'robots_txt_custom' => 'nullable|string',
        ]);

        if ($result->fails()) {
            return Response::redirect('/admin/settings');
        }

        $siteId = $this->tenantManager->id();
        $fields = [];

        foreach (['site_name', 'site_tagline', 'default_meta_description', 'robots_txt_custom'] as $field) {
            if (\array_key_exists($field, $data)) {
                $fields[$field] = $data[$field] !== null && $data[$field] !== '' ? (string) $data[$field] : null;
            }
        }

        foreach (['default_og_image_id', 'favicon_id'] as $field) {
            if (\array_key_exists($field, $data)) {
                $mediaId = null;

                if ($data[$field] !== null) {
                    $mediaId = (int) $data[$field];
                    $media = $this->database->selectOne(
                        'SELECT id FROM media WHERE id = ? AND tenant_id = ?',
                        [$mediaId, $siteId]
                    );

                    if ($media === null) {
                        return Response::redirect('/admin/settings');
                    }
                }

                $fields[$field] = $mediaId;
            }
        }

        $this->settings->set($fields);

        return Response::redirect('/admin/settings');
    }
}
