<?php

declare(strict_types=1);

require __DIR__ . '/load_env.php';
require __DIR__ . '/../vendor/autoload.php';

use Core\Config;
use Core\Database;

/**
 * Tao du lieu mau cho Local Demo: Home/About/Contact Page + Main Menu (location_key='header',
 * dung dung location co dinh HomeController/PublicPageController da hardcode - xem Public
 * Website Polish) + 1 ban ghi SEO mau cho Homepage. Phai chay SAU bin/bootstrap.php (can Site +
 * Admin User da ton tai). Idempotent - da co du lieu (kiem tra slug 'home') thi bo qua, khong loi.
 * Script rieng biet voi bin/bootstrap.php - khong sua logic bootstrap da duoc test.
 */
$basePath = \dirname(__DIR__);
$config = new Config($basePath . '/config');
$database = new Database($config);

$site = $database->selectOne('SELECT id FROM sites ORDER BY id ASC LIMIT 1');

if ($site === null) {
    \fwrite(STDERR, "Chua co Site nao. Hay chay 'php bin/bootstrap.php' truoc.\n");
    exit(1);
}

$siteId = (int) $site['id'];

$admin = $database->selectOne('SELECT id FROM users ORDER BY id ASC LIMIT 1');

if ($admin === null) {
    \fwrite(STDERR, "Chua co Admin User nao. Hay chay 'php bin/bootstrap.php' truoc.\n");
    exit(1);
}

$adminId = (int) $admin['id'];

$existing = $database->selectOne('SELECT id FROM pages WHERE tenant_id = ? AND slug = ?', [$siteId, 'home']);

if ($existing !== null) {
    echo "Du lieu mau da ton tai (slug 'home' da co), bo qua.\n";
    exit(0);
}

try {
    $database->transaction(static function (Database $db) use ($siteId, $adminId): void {
        $pages = [
            ['title' => 'Trang chu', 'slug' => 'home', 'text' => 'Chao mung den voi CMS Demo.', 'is_homepage' => 1],
            ['title' => 'Gioi thieu', 'slug' => 'about', 'text' => 'Day la trang gioi thieu mau.', 'is_homepage' => 0],
            ['title' => 'Lien he', 'slug' => 'contact', 'text' => 'Day la trang lien he mau.', 'is_homepage' => 0],
        ];

        $pageIds = [];

        foreach ($pages as $page) {
            $db->insert(
                'INSERT INTO pages (tenant_id, title, slug, content, status, published_at, is_homepage, created_by)
                 VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?)',
                [
                    $siteId,
                    $page['title'],
                    $page['slug'],
                    \json_encode(['text' => $page['text']]),
                    'published',
                    $page['is_homepage'],
                    $adminId,
                ]
            );
            $pageIds[$page['slug']] = (int) $db->connection()->lastInsertId();
        }

        $db->insert(
            'INSERT INTO menus (tenant_id, name, location_key) VALUES (?, ?, ?)',
            [$siteId, 'Main Menu', 'header']
        );
        $menuId = (int) $db->connection()->lastInsertId();

        $items = [
            ['label' => 'Trang chu', 'slug' => 'home', 'sort_order' => 0],
            ['label' => 'Gioi thieu', 'slug' => 'about', 'sort_order' => 1],
            ['label' => 'Lien he', 'slug' => 'contact', 'sort_order' => 2],
        ];

        foreach ($items as $item) {
            $db->insert(
                'INSERT INTO menu_items (menu_id, label, type, reference_id, sort_order) VALUES (?, ?, ?, ?, ?)',
                [$menuId, $item['label'], 'page', $pageIds[$item['slug']], $item['sort_order']]
            );
        }

        $db->insert(
            'INSERT INTO seo_meta (tenant_id, entity_type, entity_id, title, description) VALUES (?, ?, ?, ?, ?)',
            [
                $siteId,
                'page',
                $pageIds['home'],
                'Trang chu - CMS Demo',
                'Website demo xay dung boi CMS da website tu code, khong dung framework.',
            ]
        );
    });
} catch (Throwable $exception) {
    \fwrite(STDERR, 'Loi: ' . $exception->getMessage() . "\n");
    exit(1);
}

echo "Da tao du lieu mau: Trang chu/Gioi thieu/Lien he + Main Menu (header) + SEO cho Trang chu.\n";
