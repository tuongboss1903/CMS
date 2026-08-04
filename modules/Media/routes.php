<?php

declare(strict_types=1);

/** @var \Core\Router $router */
$router->get('/media', [\Modules\Media\ListMediaController::class, 'handle']);
$router->get('/media/{filename}', [\Modules\Media\MediaServeController::class, 'handle']);

$router->group(['middleware' => [\Core\Middleware\CsrfMiddleware::class]], function (\Core\Router $router): void {
    $router->post('/media', [\Modules\Media\UploadMediaController::class, 'handle']);
    $router->patch('/media/{id}', [\Modules\Media\UpdateMediaController::class, 'handle']);
    $router->delete('/media/{id}', [\Modules\Media\DeleteMediaController::class, 'handle']);
});
