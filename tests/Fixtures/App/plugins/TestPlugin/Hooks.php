<?php

declare(strict_types=1);

/** @var \Core\Hook $hook */
$hook->action('plugin.routes.register', static function (\Core\Router $router): void {
    require __DIR__ . '/routes.php';
});
