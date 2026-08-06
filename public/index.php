<?php

declare(strict_types=1);

// Chi ap dung khi chay qua "php -S" (PHP built-in dev server, xem SETUP_LOCAL.md) - server nay
// KHONG tu phuc vu file tinh (CSS/JS/anh) khi duoc chi dinh 1 router script nhu file nay, ma day
// TOAN BO request vao router - can tu kiem tra file tinh co ton tai that de tra "false" (bao PHP
// built-in server tu phuc vu file, khong chay Application::run()). Khong anh huong Apache/Nginx/
// PHP-FPM production - PHP_SAPI khac 'cli-server', webserver that tu phuc vu file tinh truoc khi
// request cham toi PHP.
if (PHP_SAPI === 'cli-server') {
    $requestPath = (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $staticFile = __DIR__ . $requestPath;

    if ($requestPath !== '/' && is_file($staticFile)) {
        return false;
    }
}

require_once __DIR__ . '/../bin/load_env.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Core\Application;

Application::bootstrap(dirname(__DIR__))->run();
