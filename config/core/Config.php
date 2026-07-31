<?php

namespace Core;

class Config
{
    /**
     * Mảng lưu trữ tất cả các configuration đã được load
     */
    protected static array $items = [];

    /**
     * Đường dẫn tới thư mục config
     */
    protected static string $configPath = '';

    /**
     * Khởi tạo đường dẫn thư mục config
     */
    public static function init(string $path): void
    {
        self::$configPath = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Lấy giá trị cấu hình theo key (Dùng cú pháp dấu chấm, ví dụ: 'app.name')
     */
    public static function get(string $key, $default = null)
    {
        $parts = explode('.', $key);
        $file = $parts[0];

        // Nếu file chưa được nạp, tiến hành nạp file config đó
        if (!isset(self::$items[$file])) {
            self::load($file);
        }

        // Lược bỏ tên file, chỉ giữ lại các sub-key
        array_shift($parts);

        // Duyệt qua mảng để lấy giá trị theo key
        $array = self::$items[$file] ?? [];
        foreach ($parts as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }

    /**
     * Nạp file cấu hình từ ổ đĩa
     */
    protected static function load(string $file): void
    {
        $filePath = self::$configPath . $file . '.php';

        if (file_exists($filePath)) {
            self::$items[$file] = require $filePath;
        } else {
            self::$items[$file] = [];
        }
    }
}