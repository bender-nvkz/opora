<?php

declare(strict_types=1);

namespace Opora\Core\Http\Middleware;

use Opora\Core\Config\SecurityHeadersConfig;
use Opora\Core\Http\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Устанавливает security-заголовки ответа.
 *
 * Приоритет 50 — выполняется после CorsMiddleware (40) и до BodyParserMiddleware (60).
 * Конфигурируется через SecurityHeadersConfig.
 * Даёт A+ на securityheaders.com при defaults().
 *
 * @api
 */
final readonly class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SecurityHeadersConfig $securityHeadersConfig,
    ) {
    }

    public static function priority(): int
    {
        return 50;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $response = $handler->handle($request);

        foreach ($this->securityHeadersConfig->toHeaderArray() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
