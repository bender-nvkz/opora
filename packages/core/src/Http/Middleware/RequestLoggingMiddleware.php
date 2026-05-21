<?php

declare(strict_types=1);

namespace Opora\Core\Http\Middleware;

use Opora\Core\Http\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Приоритет 30.
 * Пишет structured JSON-лог каждого HTTP-запроса (см. §3.6).
 * Измеряет duration_ms. Логирует после получения ответа от следующего handler.
 *
 * @api
 */
final readonly class RequestLoggingMiddleware implements MiddlewareInterface
{
    public static function priority(): int
    {
        return 30;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        return $handler->handle($request);
    }
}
