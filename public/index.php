<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Core\Config;

// Khởi tạo đường dẫn thư mục config
Config::init(__DIR__ . '/../config');

// Lấy dữ liệu cấu hình
echo Config::get('app.name'); // In ra: My CMS Project
echo "\n";
echo Config::get('database.connections.mysql.host'); // In ra: 127.0.0.1