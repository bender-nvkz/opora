<?php

declare(strict_types=1);

namespace Opora\Core\Http\Middleware;

use Opora\Core\Http\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Приоритет 20. Второй в стеке после ErrorHandler.
 *
 * Гарантирует наличие X-Request-Id на каждом запросе:
 * - Если клиент прислал X-Request-Id — использует его (с санитизацией)
 * - Если нет — генерирует UUID v4
 * - Сохраняет ID в Request attribute 'request_id' для нижележащих middleware
 * - Добавляет X-Request-Id header в Response
 *
 * @api
 */
final class RequestIdMiddleware implements MiddlewareInterface
{
    /**
     * Разрешённые символы в X-Request-Id (защита от header injection).
     */
    private const string ALLOWED_PATTERN = '/[^a-zA-Z0-9\\-._~\\/]/';

    public static function priority(): int
    {
        return 20;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $requestId = $this->resolveRequestId($request);

        // Сохраняем ID в атрибуте запроса для нижележащих middleware
        $request = $request->withAttribute('request_id', $requestId);

        $response = $handler->handle($request);

        return $response->withHeader('X-Request-Id', $requestId);
    }

    /**
     * Определить request ID: из заголовка или новый UUID v4.
     */
    private function resolveRequestId(ServerRequestInterface $serverRequest): string
    {
        if ($serverRequest->hasHeader('X-Request-Id')) {
            $headerValue = $serverRequest->getHeaderLine('X-Request-Id');

            return $this->sanitize($headerValue);
        }

        return Uuid::v4()->toRfc4122();
    }

    /**
     * Санитизация: удаляем символы, которые могут быть использованы
     * для header injection (CR, LF и другие управляющие символы).
     */
    private function sanitize(string $value): string
    {
        $sanitized = \preg_replace(self::ALLOWED_PATTERN, '', $value);

        // preg_replace возвращает null при ошибке
        if ($sanitized === null) {
            return Uuid::v4()->toRfc4122();
        }

        return $sanitized !== '' ? $sanitized : Uuid::v4()->toRfc4122();
    }
}
