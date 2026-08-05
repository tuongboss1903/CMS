<?php

declare(strict_types=1);

/**
 * Nguon du lieu path SVG DUY NHAT cho toan bo icon Admin - dung `require __DIR__ . '/_icon_paths.php'`
 * (include PHP thuan, KHONG qua $this->include()/View::resolvePath() vi day khong phai template
 * render duoc, chi tra ve mang du lieu) tu ca admin.partials.icon (emit <use>) lan
 * admin.partials.icon_sprite (emit dinh nghia <symbol>, 1 lan/trang) - tranh trung lap 1 mang
 * ~35 icon o 2 noi. Ten file bat dau "_" theo dung quy uoc partial noi bo da co (vd
 * pages/blocks/_builder.php), khong phai template Router/View resolve truc tiep.
 */
return [
    'dashboard' => '<path d="M3 13h8V3H3v10Zm10 8h8V11h-8v10ZM3 21h8v-6H3v6ZM13 9h8V3h-8v6Z" />',
    'notification' => '<path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9" />',
    'users' => '<path d="M17 20v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1M15 9a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm5 11v-1a4 4 0 0 0-3-3.87M15 5a4 4 0 0 1 0 8" />',
    'roles' => '<path d="M12 3 4 6v6c0 5 3.4 8.5 8 9 4.6-.5 8-4 8-9V6l-8-3Z" /><path d="m9 12 2 2 4-4" />',
    'pages' => '<path d="M8 3h6l5 5v11a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" /><path d="M14 3v5h5M9 13h6M9 17h6M9 9h2" />',
    'media' => '<path d="M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" /><circle cx="9" cy="10" r="2" /><path d="m4 17 5-5 4 4 3-3 4 4" />',
    'menu' => '<path d="M4 6h16M4 12h10M4 18h16" />',
    'seo' => '<circle cx="11" cy="11" r="7" /><path d="m20 20-4.3-4.3" />',
    'comments' => '<path d="M4 5h16v11H8l-4 4V5Z" />',
    'settings' => '<circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V21a2 2 0 1 1-4 0v-.2a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.6-1H3a2 2 0 1 1 0-4h.2a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.6V3a2 2 0 1 1 4 0v.2a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9c.2.6.7 1 1.6 1H21a2 2 0 1 1 0 4h-.2a1.7 1.7 0 0 0-1.4 1Z" />',
    'system-settings' => '<path d="M4 4h16v4H4V4Zm0 6h16v10H4V10Z" /><path d="M8 14h.01M8 18h.01" />',
    'audit-log' => '<path d="M12 8v4l3 2" /><circle cx="12" cy="12" r="9" />',
    'plugins' => '<path d="M14 3h-4v4H7a2 2 0 0 0-2 2v3H1v4h4v3a2 2 0 0 0 2 2h3v-4h4v4h3a2 2 0 0 0 2-2v-3h4v-4h-4V9a2 2 0 0 0-2-2h-3V3Z" />',
    'ecommerce' => '<circle cx="9" cy="20" r="1" /><circle cx="18" cy="20" r="1" /><path d="M3 4h2l2.4 12.2a2 2 0 0 0 2 1.6h7.2a2 2 0 0 0 2-1.6L21 8H6" />',
    'plus' => '<path d="M12 5v14M5 12h14" />',
    'edit' => '<path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />',
    'trash' => '<path d="M3 6h18" /><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6h16Z" />',
    'lock' => '<rect x="4" y="11" width="16" height="9" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" />',
    'unlock' => '<rect x="4" y="11" width="16" height="9" rx="2" /><path d="M8 11V7a4 4 0 0 1 7.4-2" />',
    'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><path d="M16 17l5-5-5-5" /><path d="M21 12H9" />',
    'search' => '<circle cx="11" cy="11" r="7" /><path d="m20 20-4.3-4.3" />',
    'sun' => '<circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />',
    'moon' => '<path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z" />',
    'check' => '<path d="M20 6 9 17l-5-5" />',
    'x' => '<path d="M18 6 6 18M6 6l12 12" />',
    'upload' => '<path d="M12 3v12" /><path d="m7 8 5-5 5 5" /><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" />',
    'filter' => '<path d="M4 4h16l-6 8v6l-4 2v-8Z" />',
    'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z" />',
    'assign' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 11h-6M19 8v6" />',
    'server' => '<rect x="3" y="4" width="18" height="7" rx="1.5" /><rect x="3" y="13" width="18" height="7" rx="1.5" /><path d="M7 7.5h.01M7 16.5h.01" />',
    'palette' => '<path d="M12 2a10 10 0 1 0 0 20c1.1 0 2-1 2-2 0-.5-.2-1-.5-1.4-.3-.4-.5-.8-.5-1.3 0-1 .8-1.8 1.8-1.8H17a3 3 0 0 0 3-3c0-5-3.6-9-8-9Z" /><circle cx="7.5" cy="10.5" r="1" /><circle cx="10.5" cy="7" r="1" /><circle cx="15" cy="8" r="1" />',
    'billing' => '<rect x="2" y="5" width="20" height="14" rx="2" /><path d="M2 10h20M6 15h4" />',
];
