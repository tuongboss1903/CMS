<?php

declare(strict_types=1);

require __DIR__ . '/load_env.php';
require __DIR__ . '/../vendor/autoload.php';

use Core\Config;
use Core\Database;

/**
 * Tao du lieu Demo Enterprise cho 1 tenant cu the (Phase 7 - UI/UX Demo Polish). Nhan tham so
 * chon site (domain) + bo noi dung (content pack) - mac dinh domain = site dau tien (tuong thich
 * nguoc voi cach goi cu "php bin/seed_demo.php" khong tham so tu SETUP_LOCAL.md), pack mac dinh
 * "tech". Phai chay SAU bin/bootstrap.php (site 1) hoac bin/add_site.php (site 2+). Idempotent -
 * da co du lieu (kiem tra slug 'home') thi bo qua, khong loi.
 *
 * Fork B1 (Phase 7 Architecture Analysis, Owner Approved): mo rong script procedural co san,
 * khong tao pattern Seeder Class moi (database/seeders/).
 */
$basePath = \dirname(__DIR__);
$config = new Config($basePath . '/config');
$database = new Database($config);

$domainArg = $argv[1] ?? null;
$pack = $argv[2] ?? 'tech';

if (!\in_array($pack, ['tech', 'restaurant'], true)) {
    \fwrite(STDERR, "Content pack khong hop le. Dung 'tech' hoac 'restaurant'.\n");
    exit(1);
}

if ($domainArg !== null) {
    $site = $database->selectOne(
        'SELECT sites.id FROM sites INNER JOIN site_domains ON site_domains.site_id = sites.id WHERE site_domains.domain = ?',
        [$domainArg]
    );

    if ($site === null) {
        \fwrite(STDERR, "Khong tim thay Site nao voi domain '{$domainArg}'.\n");
        exit(1);
    }
} else {
    $site = $database->selectOne('SELECT id FROM sites ORDER BY id ASC LIMIT 1');

    if ($site === null) {
        \fwrite(STDERR, "Chua co Site nao. Hay chay 'php bin/bootstrap.php' truoc.\n");
        exit(1);
    }
}

$siteId = (int) $site['id'];

$admin = $database->selectOne(
    'SELECT users.id FROM users INNER JOIN user_site_roles ON user_site_roles.user_id = users.id WHERE user_site_roles.site_id = ? LIMIT 1',
    [$siteId]
);

if ($admin === null) {
    \fwrite(STDERR, "Chua co Admin User nao gan voi Site nay.\n");
    exit(1);
}

$adminId = (int) $admin['id'];

$existing = $database->selectOne('SELECT id FROM pages WHERE tenant_id = ? AND slug = ?', [$siteId, 'home']);

if ($existing !== null) {
    echo "Du lieu mau da ton tai (slug 'home' da co) cho site #{$siteId}, bo qua.\n";
    exit(0);
}

/** @return array{home_html: string, about_html: string, contact_html: string, blog_title: string, blog_html: string, seo_description: string} */
function contentPack(string $pack): array
{
    if ($pack === 'restaurant') {
        return [
            'home_html' => <<<'HTML'
<div class="hero">
    <h1>Green Gourmet Restaurant &amp; Cafe</h1>
    <p class="lead">Nguyen lieu tuoi moi ngay, thuc don thay doi theo mua. Dat ban truc tuyen chi trong 30 giay.</p>
    <div class="hero-cta">
        <a href="/contact" class="btn btn-primary">Dat ban ngay</a>
        <a href="/about" class="btn btn-secondary">Xem thuc don</a>
    </div>
    <div class="hero-mockup">
        <div style="aspect-ratio:16/9; display:flex; align-items:center; justify-content:center; background:var(--color-bg-elevated); border-radius:var(--radius-md); color:var(--color-text-muted); font-size:14px;">
            Anh khong gian nha hang (cap nhat qua Media Manager)
        </div>
    </div>
</div>
<div class="feature-grid">
    <div class="feature-card"><div class="feature-icon">1</div><h3>Nguyen lieu huu co</h3><p>100% rau cu tuoi tu nong trai lien ket, khong chat bao quan.</p></div>
    <div class="feature-card"><div class="feature-icon">2</div><h3>Khong gian ngoai troi</h3><p>San vuon xanh mat, phu hop tiec gia dinh va nhom ban.</p></div>
    <div class="feature-card"><div class="feature-icon">3</div><h3>Dat ban truc tuyen</h3><p>Giu cho chi trong vai buoc, xac nhan tuc thi qua email.</p></div>
    <div class="feature-card"><div class="feature-icon">4</div><h3>Thuc don theo mua</h3><p>Cap nhat moi quy, luon co mon moi de kham pha.</p></div>
    <div class="feature-card"><div class="feature-icon">5</div><h3>Dau bep nhieu kinh nghiem</h3><p>Doi ngu dau bep hon 10 nam kinh nghiem am thuc Au - A.</p></div>
    <div class="feature-card"><div class="feature-icon">6</div><h3>Uu dai thanh vien</h3><p>Tich diem moi lan ghe tham, doi qua tang hap dan.</p></div>
</div>
HTML,
            'about_html' => <<<'HTML'
<h1>Ve chung toi</h1>
<p>Green Gourmet duoc thanh lap voi mong muon mang den trai nghiem am thuc lanh manh, tuoi ngon giua long thanh pho. Moi mon an duoc che bien tu nguyen lieu huu co, thu mua truc tiep tu cac nong trai dia phuong moi ngay.</p>
<p>Khong gian nha hang ket hop giua phong cach mien que va hien dai, tao cam giac gan gui nhung van tinh te - phu hop cho ca bua an gia dinh lan tiec cong ty.</p>
HTML,
            'contact_html' => <<<'HTML'
<h1>Lien he &amp; Dat ban</h1>
<p>Hotline dat ban: <strong>1900 6868</strong> (7:00 - 22:00 hang ngay)</p>
<p>Dia chi: 45 Duong Xanh, Quan 2, TP. Ho Chi Minh</p>
<p>Email: booking@greengourmet.example</p>
HTML,
            'blog_title' => 'Thuc don mua he 2026: 5 mon an giai nhiet moi',
            'blog_html' => <<<'HTML'
<h1>Thuc don mua he 2026: 5 mon an giai nhiet moi</h1>
<p>Mua he nay, Green Gourmet gioi thieu 5 mon an moi lay cam hung tu trai cay nhiet doi va rau cu huu co theo mua - vua thanh mat vua tot cho suc khoe.</p>
<p>Tat ca deu co san tu tuan nay, dat ban truoc de duoc uu tien phuc vu vao khung gio cao diem cuoi tuan.</p>
HTML,
            'seo_description' => 'Green Gourmet Restaurant & Cafe - am thuc huu co tuoi ngon, dat ban truc tuyen nhanh chong.',
        ];
    }

    return [
        'home_html' => <<<'HTML'
<div class="hero">
    <h1>Nen tang CMS Da Website cho Doanh nghiep</h1>
    <p class="lead">Van hanh khong gioi han website tren cung 1 ha tang, cach ly du lieu tuyet doi theo tung khach hang, khong phu thuoc framework ben ngoai.</p>
    <div class="hero-cta">
        <a href="/admin/login" class="btn btn-primary">Vao trang quan tri</a>
        <a href="#features" class="btn btn-secondary">Kham pha tinh nang</a>
    </div>
    <div class="hero-mockup">
        <div style="aspect-ratio:16/9; display:flex; align-items:center; justify-content:center; background:var(--color-bg-elevated); border-radius:var(--radius-md); color:var(--color-text-muted); font-size:14px;">
            Xem truoc giao dien Admin Dashboard
        </div>
    </div>
</div>
<div class="feature-grid" id="features">
    <div class="feature-card"><div class="feature-icon">1</div><h3>Multi-tenancy that</h3><p>Moi khach hang 1 domain rieng, du lieu cach ly hoan toan qua tang Database - khong chia se nham lan.</p></div>
    <div class="feature-card"><div class="feature-icon">2</div><h3>SEO Automation</h3><p>Sitemap.xml, Robots.txt, Open Graph, Schema.org tu dong sinh theo tung trang, tung tenant.</p></div>
    <div class="feature-card"><div class="feature-icon">3</div><h3>Hieu nang cao</h3><p>Khong tang trung gian framework - moi request duoc xu ly toi gian, phan hoi nhanh.</p></div>
    <div class="feature-card"><div class="feature-icon">4</div><h3>Phan quyen chi tiet</h3><p>Kiem soat toi tung hanh dong (xem/tao/sua/xoa/xuat ban) cho tung vai tro quan tri.</p></div>
    <div class="feature-card"><div class="feature-icon">5</div><h3>Giao dien tuy bien</h3><p>He thong Theme rieng biet cho tung website, khong anh huong lan nhau.</p></div>
    <div class="feature-card"><div class="feature-icon">6</div><h3>Toi uu toc do</h3><p>Cache-Control/ETag cho tai nguyen tinh, san sang mo rong khi luu luong tang.</p></div>
</div>
<div class="showcase-block">
    <div class="showcase-copy">
        <h2>Quan tri toan bo noi dung tu 1 Dashboard duy nhat</h2>
        <p>Tao trang, quan ly Media, dung menu keo-tha, cau hinh SEO - tat ca trong 1 giao dien quan tri thong nhat, khong can hoc nhieu cong cu.</p>
        <ul>
            <li>Rich Text Editor cho noi dung trang</li>
            <li>Thu vien Media dang Grid, keo-tha upload</li>
            <li>Menu Builder keo-tha truc quan</li>
            <li>Cau hinh SEO rieng cho tung trang</li>
        </ul>
    </div>
    <div class="showcase-media">
        <div style="aspect-ratio:4/3; display:flex; align-items:center; justify-content:center; background:var(--color-bg-elevated); border-radius:var(--radius-md); color:var(--color-text-muted); font-size:14px;">
            Anh chup man hinh Trang quan tri
        </div>
    </div>
</div>
<div class="cta-footer">
    <h2>San sang trien khai website doanh nghiep tiep theo?</h2>
    <p>Dang nhap trang quan tri de bat dau tao trang dau tien cua ban ngay hom nay.</p>
    <a href="/admin/login" class="btn btn-primary">Bat dau ngay</a>
</div>
HTML,
        'about_html' => <<<'HTML'
<h1>Ve chung toi</h1>
<p>SaaS CMS Technology Co. phat trien nen tang quan tri noi dung da website (multi-tenant) danh cho doanh nghiep can van hanh nhieu website tren cung 1 ha tang, voi yeu cau cach ly du lieu nghiem ngat va toan quyen kiem soat ma nguon.</p>
<p>He thong duoc xay dung hoan toan tu code PHP thuan, khong phu thuoc framework ben ngoai - toi da hoa kha nang tuy bien va giam thieu rui ro phu thuoc dai han.</p>
HTML,
        'contact_html' => <<<'HTML'
<h1>Lien he</h1>
<p>Email kinh doanh: sales@saascms.example</p>
<p>Ho tro ky thuat: support@saascms.example</p>
<p>Gio lam viec: 8:00 - 18:00, Thu Hai - Thu Sau</p>
HTML,
        'blog_title' => 'Vi sao doanh nghiep nen chon kien truc Multi-tenant cho he thong Website',
        'blog_html' => <<<'HTML'
<h1>Vi sao doanh nghiep nen chon kien truc Multi-tenant cho he thong Website</h1>
<p>Kien truc Multi-tenant cho phep van hanh nhieu website tren cung 1 ha tang duy nhat, giam dang ke chi phi server va cong suc bao tri so voi mo hinh 1 website - 1 ha tang rieng le.</p>
<p>Diem mau chot la co che cach ly du lieu: moi tenant chi truy cap duoc dung du lieu cua minh, du dung chung Database vat ly - dam bao vua tiet kiem chi phi, vua an toan.</p>
<p>Doi voi doanh nghiep quan ly nhieu thuong hieu hoac chi nhanh, day la lua chon toi uu ca ve chi phi van hanh lan toc do trien khai website moi.</p>
HTML,
        'seo_description' => 'Nen tang CMS Da Website multi-tenant cho doanh nghiep - tu code PHP thuan, khong phu thuoc framework.',
    ];
}

$data = contentPack($pack);
$homeTitle = 'Trang chu';
$blogSlug = $pack === 'restaurant' ? 'thuc-don-mua-he-2026' : 'multi-tenant-la-gi';

try {
    $database->transaction(static function (Database $db) use ($siteId, $adminId, $data, $homeTitle, $blogSlug): void {
        $pages = [
            ['title' => $homeTitle, 'slug' => 'home', 'html' => $data['home_html'], 'is_homepage' => 1],
            ['title' => 'Gioi thieu', 'slug' => 'about', 'html' => $data['about_html'], 'is_homepage' => 0],
            ['title' => 'Lien he', 'slug' => 'contact', 'html' => $data['contact_html'], 'is_homepage' => 0],
            ['title' => $data['blog_title'], 'slug' => $blogSlug, 'html' => $data['blog_html'], 'is_homepage' => 0],
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
                    \json_encode(['html' => $page['html']]),
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
            ['label' => $data['blog_title'], 'slug' => $blogSlug, 'sort_order' => 2],
            ['label' => 'Lien he', 'slug' => 'contact', 'sort_order' => 3],
        ];

        foreach ($items as $item) {
            $db->insert(
                'INSERT INTO menu_items (menu_id, label, type, reference_id, sort_order) VALUES (?, ?, ?, ?, ?)',
                [$menuId, $item['label'], 'page', $pageIds[$item['slug']], $item['sort_order']]
            );
        }

        $db->insert(
            'INSERT INTO seo_meta (tenant_id, entity_type, entity_id, title, description) VALUES (?, ?, ?, ?, ?)',
            [$siteId, 'page', $pageIds['home'], $homeTitle, $data['seo_description']]
        );

        $db->insert(
            'INSERT INTO seo_meta (tenant_id, entity_type, entity_id, title, description) VALUES (?, ?, ?, ?, ?)',
            [$siteId, 'page', $pageIds[$blogSlug], $data['blog_title'], $data['seo_description']]
        );
    });
} catch (Throwable $exception) {
    \fwrite(STDERR, 'Loi: ' . $exception->getMessage() . "\n");
    exit(1);
}

echo "Da tao du lieu Demo Enterprise (pack '{$pack}') cho site #{$siteId}: Home/Gioi thieu/Lien he/Blog + Main Menu (header) + SEO cho Home va Blog.\n";
