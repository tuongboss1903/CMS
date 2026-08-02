<?php

declare(strict_types=1);

/** @var \Core\Router $router */
$router->get('/media', [\Modules\Media\ListMediaController::class, 'handle']);
$router->post('/media', [\Modules\Media\UploadMediaController::class, 'handle']);
$router->patch('/media/{id}', [\Modules\Media\UpdateMediaController::class, 'handle']);
$router->delete('/media/{id}', [\Modules\Media\DeleteMediaController::class, 'handle']);
