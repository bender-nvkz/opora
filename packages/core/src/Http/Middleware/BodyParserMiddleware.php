<?php

declare(strict_types=1);

namespace Opora\Core\Http\Middleware;

use Opora\Core\Http\Exception\HttpException;
use Opora\Core\Http\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware для парсинга тела запроса в зависимости от Content-Type.
 *
 * - application/json → json_decode → withParsedBody()
 * - Если тело пустое или Content-Type не поддерживается — пропускает
 * - Если JSON невалидный — выбрасывает 400 Bad Request
 * - Не обрабатывает multipart/form-data (PHP уже заполняет $_POST)
 *
 * @api
 */
final readonly class BodyParserMiddleware implements MiddlewareInterface
{
    public static function priority(): int
    {
        return 60;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $contentType = $request->getHeaderLine('Content-Type');

        if (!$this->isJsonContentType($contentType)) {
            return $handler->handle($request);
        }

        $body = (string) $request->getBody();

        if ($body === '') {
            return $handler->handle($request);
        }

        /** @var array<array-key, mixed>|null $parsed */
        $parsed = \json_decode($body, true);

        if ($parsed === null && \json_last_error() !== \JSON_ERROR_NONE) {
            throw new HttpException(400, 'Invalid JSON');
        }

        return $handler->handle($request->withParsedBody($parsed));
    }

    /**
     * Проверяет, является ли Content-Type application/json.
     * Игнорирует charset и регистр: "application/json; charset=utf-8" → true.
     */
    private function isJsonContentType(string $contentType): bool
    {
        $normalized = \strtolower(\trim($contentType));

        if ($normalized === 'application/json') {
            return true;
        }

        return \str_starts_with($normalized, 'application/json;');
    }
}
