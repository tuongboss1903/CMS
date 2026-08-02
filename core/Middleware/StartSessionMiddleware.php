<?php

declare(strict_types=1);

namespace Core\Middleware;

use Closure;
use Core\Http\Request;
use Core\Http\Response;
use Core\Session;

/**
 * Goi Session::start() truoc khi Controller/Csrf/Auth/Authorization doc-ghi du lieu session.
 * Session thiet ke Lazy Start (CMS-007, xem Session.php) - ban dau du tinh 1 "SessionMiddleware"
 * se lam viec nay (ghi ro trong docblock Session.php) nhung chua tung duoc trien khai, khien moi
 * route dung Session that (Admin/Auth/JSON API qua Authorization::can()) nem SessionException khi
 * chay qua HTTP that (khong bi lo trong test vi test luon tu goi $session->start() thu cong o
 * setUp()). Middleware nay lap dung khoang trong con thieu cua thiet ke goc - khong doi API
 * Session, khong doi hanh vi Lazy Start.
 */
final class StartSessionMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Session $session)
    {
    }

    public function process(Request $request, Closure $next): Response
    {
        $this->session->start();

        return $next($request);
    }
}
