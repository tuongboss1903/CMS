<?php

declare(strict_types=1);

namespace Modules\Page\Actions;

use Core\Database;
use Core\Database\QueryException;
use Core\Security\HtmlSanitizer;
use Core\TenantManager;
use Core\Validator;

/**
 * Pilot Action Class Pattern (Phase 6) - nghiep vu "tao Page" dung chung giua
 * Modules\Page\CreatePageController (JSON) va Modules\Admin\PageCreateController (Admin HTML).
 * Action CHI chua validate + thao tac DB, KHONG biet gi ve HTTP/Response - Controller goi tu tu
 * bat exception va tu dinh dang Response rieng (JSON vs HTML/redirect).
 */
final class CreatePageAction
{
    public function __construct(
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly Validator $validator,
        private readonly HtmlSanitizer $htmlSanitizer,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{id: int, title: string, slug: string, status: string}
     *
     * @throws PageValidationException
     */
    public function execute(array $data, int|string $createdBy): array
    {
        $result = $this->validator->validate($data, [
            'title' => 'required|string',
            'slug' => 'required|string',
            'content' => 'nullable|array',
            'template' => 'nullable|string',
            'parent_id' => 'nullable|integer',
        ]);

        if ($result->fails()) {
            throw new PageValidationException('Du lieu khong hop le.', $result->errors());
        }

        $siteId = $this->tenantManager->id();
        $parentId = $this->resolveParentId($data, $siteId);

        $title = (string) $data['title'];
        $slug = (string) $data['slug'];
        $content = \array_key_exists('content', $data) && \is_array($data['content'])
            ? \json_encode($this->htmlSanitizer->sanitizeContentArray($data['content']))
            : null;
        $template = \array_key_exists('template', $data) && $data['template'] !== null && $data['template'] !== ''
            ? (string) $data['template']
            : null;

        try {
            $this->database->insert(
                'INSERT INTO pages (tenant_id, parent_id, title, slug, content, template, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$siteId, $parentId, $title, $slug, $content, $template, 'draft', $createdBy]
            );
        } catch (QueryException) {
            throw new PageValidationException('Slug da ton tai.', ['slug' => ['Slug da ton tai.']]);
        }

        $pageId = (int) $this->database->connection()->lastInsertId();

        return ['id' => $pageId, 'title' => $title, 'slug' => $slug, 'status' => 'draft'];
    }

    /** @param array<string, mixed> $data */
    private function resolveParentId(array $data, int|string|null $siteId): ?int
    {
        if (!\array_key_exists('parent_id', $data) || $data['parent_id'] === null || $data['parent_id'] === '') {
            return null;
        }

        $parentId = (int) $data['parent_id'];
        $parent = $this->database->selectOne(
            'SELECT id FROM pages WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$parentId, $siteId]
        );

        if ($parent === null) {
            throw new PageValidationException('Parent page khong hop le.', ['parent_id' => ['Parent page khong hop le.']]);
        }

        return $parentId;
    }
}
