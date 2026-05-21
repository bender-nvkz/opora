<?php

declare(strict_types=1);

namespace Opora\Core\Http\Middleware;

use Opora\Core\Config\CorsConfig;
use Opora\Core\Http\MiddlewareInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CORS-посредник: обработка preflight-запросов и установка CORS-заголовков.
 *
 * - OPTIONS (preflight) → 204 без вызова handler
 * - Разрешённый Origin → Access-Control-Allow-Origin и сопутствующие заголовки
 * - Неразрешённый Origin → без CORS-заголовков (браузер блокирует запрос)
 * - allowCredentials=true + wildcard origin → RuntimeException (security)
 *
 * @api
 */
final readonly class CorsMiddleware implements MiddlewareInterface
{
    private const string WILDCARD = '*';

    public function __construct(
        private CorsConfig $corsConfig,
        private ResponseFactoryInterface $responseFactory,
    ) {
    }

    public static function priority(): int
    {
        return 40;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $origin = $request->getHeaderLine('Origin');

        // Нет Origin — не CORS-запрос, пропускаем без изменений
        if ($origin === '') {
            return $handler->handle($request);
        }

        // Security: allowCredentials=true + wildcard origin запрещён
        $this->validateSecurity();

        // Preflight: OPTIONS → 204 без вызова handler
        if (\strtoupper($request->getMethod()) === 'OPTIONS') {
            return $this->handlePreflight($origin);
        }

        // Обычный запрос: сначала handler, потом CORS-заголовки
        $response = $handler->handle($request);

        return $this->addCorsHeaders($response, $origin);
    }

    /**
     * Проверка безопасности: credentials + wildcard — запрещённая комбинация.
     */
    private function validateSecurity(): void
    {
        if ($this->corsConfig->allowCredentials && \in_array(self::WILDCARD, $this->corsConfig->allowedOrigins, true)) {
            throw new \RuntimeException(
                'Cannot use wildcard origin with credentials enabled. '
                . 'Set CORS_ALLOWED_ORIGINS to explicit origins when CORS_ALLOW_CREDENTIALS=true.',
            );
        }
    }

    private function handlePreflight(string $origin): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(204);

        $response = $this->addCorsHeaders($response, $origin);

        /** @var list<string> $methods */
        $methods = $this->corsConfig->allowedMethods;
        if ($methods !== []) {
            $response = $response->withHeader(
                'Access-Control-Allow-Methods',
                \implode(', ', $methods),
            );
        }

        /** @var list<string> $headers */
        $headers = $this->corsConfig->allowedHeaders;
        if ($headers !== []) {
            return $response->withHeader(
                'Access-Control-Allow-Headers',
                \implode(', ', $headers),
            );
        }

        return $response;
    }

    private function addCorsHeaders(ResponseInterface $response, string $origin): ResponseInterface
    {
        // Проверяем, разрешён ли origin
        $isAllowed = \in_array(self::WILDCARD, $this->corsConfig->allowedOrigins, true)
            || \in_array($origin, $this->corsConfig->allowedOrigins, true);

        if (!$isAllowed) {
            return $response;
        }

        // Устанавливаем заголовки для разрешённого origin
        $response = $response->withHeader(
            'Access-Control-Allow-Origin',
            $origin,
        );

        if ($this->corsConfig->allowCredentials) {
            $response = $response->withHeader(
                'Access-Control-Allow-Credentials',
                'true',
            );
        }

        if ($this->corsConfig->maxAge > 0) {
            return $response->withHeader(
                'Access-Control-Max-Age',
                (string) $this->corsConfig->maxAge,
            );
        }

        return $response;
    }
}
