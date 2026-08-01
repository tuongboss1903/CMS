<?php

declare(strict_types=1);

namespace Core;

use Core\Http\Response;
use Core\Router\MethodNotAllowedException;
use Core\Router\RouteNotFoundException;
use Throwable;

/**
 * Map Throwable -> Response. Thuan tuy: khong Config, khong Container, khong logging (Application
 * tu goi logException() rieng, dua tren status code cua Response tra ve - xem Application::handle()).
 * Mapping tinh, khong registry/extend() (dung pham vi CMS-019 da chot).
 */
final class ExceptionHandler
{
    public function handle(Throwable $exception, bool $debug): Response
    {
        if ($exception instanceof RouteNotFoundException) {
            return $this->response(404, 'Not Found');
        }

        if ($exception instanceof MethodNotAllowedException) {
            return $this->response(405, 'Method Not Allowed');
        }

        $message = $debug ? $exception->getMessage() : 'Internal Server Error';
        $body = $this->envelope($message);

        if ($debug) {
            $body['debug'] = [
                'exception' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => \explode("\n", $exception->getTraceAsString()),
            ];
        }

        return Response::json($body, 500);
    }

    private function response(int $status, string $message): Response
    {
        return Response::json($this->envelope($message), $status);
    }

    /** @return array{success: bool, data: null, message: string, errors: array<never, never>} */
    private function envelope(string $message): array
    {
        return [
            'success' => false,
            'data' => null,
            'message' => $message,
            'errors' => [],
        ];
    }
}
