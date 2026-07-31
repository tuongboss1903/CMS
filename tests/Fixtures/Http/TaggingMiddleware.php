<?php

declare(strict_types=1);

namespace Tests\Fixtures\Http;

use Closure;
use Core\Http\Request;
use Core\Http\Response;
use Core\Middleware\MiddlewareInterface;

/**
 * Boc body Response bang tag truoc/sau - ket qua long nhau phan anh dung thu tu Onion: middleware
 * dang ky truoc se boc NGOAI CUNG (tag cua no nam ngoai cung chuoi ket qua). Khong nhan tham so
 * constructor (Container auto-wire truc tiep qua ten class, khong the truyen scalar khac nhau cho
 * nhieu instance cung luc) - subclass tu dinh nghia $tag rieng.
 */
abstract class TaggingMiddleware implements MiddlewareInterface
{
    abstract protected function tag(): string;

    public function process(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $tag = $this->tag();

        return new Response(
            "{$tag}(before)" . $response->getBody() . "{$tag}(after)",
            $response->getStatusCode(),
            $response->getHeaders()
        );
    }
}
