<?php

declare(strict_types=1);

namespace Opora\Core\Config;

/**
 * Value Object для CORS-конфигурации приложения.
 *
 * @api
 */
final readonly class CorsConfig
{
    /** @var list<string> REST-стандарт: методы для CRUD + OPTIONS */
    public const array DEFAULT_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    /** @var list<string> Заголовки, необходимые для работы API */
    public const array DEFAULT_HEADERS = ['Content-Type', 'Authorization', 'X-Request-Id'];

    /**
     * @param list<string> $allowedOrigins Список разрешённых origin. '*' для всех (только dev).
     * @param list<string> $allowedMethods Список разрешённых HTTP-методов.
     * @param list<string> $allowedHeaders Список разрешённых HTTP-заголовков.
     */
    public function __construct(
        public array $allowedOrigins,
        public array $allowedMethods,
        public array $allowedHeaders,
        public int $maxAge,
        public bool $allowCredentials,
    ) {
    }
}
