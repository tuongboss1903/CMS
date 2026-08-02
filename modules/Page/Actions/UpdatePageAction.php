<?php

declare(strict_types=1);

namespace Modules\Page\Actions;

use Core\Database;
use Core\TenantManager;
use Core\Validator;

/**
 * Pilot Action Class Pattern (Phase 6) - nghiep vu "cap nhat Page" (partial update) dung chung
 * giua Modules\Page\EditPageController (JSON) va Modules\Admin\PageUpdateController (Admin HTML).
 */
final class UpdatePageAction
{
    public function __construct(
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly Validator $validator,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws PageNotFoundException
     * @throws PageValidationException
     */
    public function execute(int $pageId, array $data): void
    {
        $siteId = $this->tenantManager->id();

        $exists = $this->database->selectOne(
            'SELECT id FROM pages WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$pageId, $siteId]
        );

        if ($exists === null) {
            throw new PageNotFoundException();
        }

        $result = $this->validator->validate($data, [
            'title' => 'nullable|string',
            'slug' => 'nullable|string',
            'content' => 'nullable|array',
            'template' => 'nullable|string',
            'parent_id' => 'nullable|integer',
        ]);

        if ($result->fails()) {
            throw new PageValidationException('Du lieu khong hop le.', $result->errors());
        }

        $fields = [];
        $bindings = [];

        if (\array_key_exists('title', $data) && $data['title'] !== null) {
            $fields[] = 'title = ?';
            $bindings[] = (string) $data['title'];
        }

        if (\array_key_exists('slug', $data) && $data['slug'] !== null) {
            $fields[] = 'slug = ?';
            $bindings[] = (string) $data['slug'];
        }

        if (\array_key_exists('content', $data) && $data['content'] !== null) {
            $fields[] = 'content = ?';
            $bindings[] = \json_encode($data['content']);
        }

        if (\array_key_exists('template', $data) && $data['template'] !== null) {
            $fields[] = 'template = ?';
            $bindings[] = (string) $data['template'];
        }

        if (\array_key_exists('parent_id', $data) && $data['parent_id'] !== null && $data['parent_id'] !== '') {
            $parentId = (int) $data['parent_id'];
            $parent = $this->database->selectOne(
                'SELECT id FROM pages WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
                [$parentId, $siteId]
            );

            if ($parent === null) {
                throw new PageValidationException('Parent page khong hop le.', ['parent_id' => ['Parent page khong hop le.']]);
            }

            $fields[] = 'parent_id = ?';
            $bindings[] = $parentId;
        }

        if ($fields === []) {
            throw new PageValidationException('Khong co du lieu de cap nhat.', ['title' => ['Khong co du lieu de cap nhat.']]);
        }

        $bindings[] = $pageId;

        $this->database->statement(
            'UPDATE pages SET ' . \implode(', ', $fields) . ' WHERE id = ?',
            $bindings
        );
    }
}
