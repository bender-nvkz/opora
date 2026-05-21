<?php

declare(strict_types=1);

namespace Opora\Core\Tests\Unit\Config;

use Opora\Core\Config\CorsConfig;
use PHPUnit\Framework\TestCase;

/**
 * Specification для CorsConfig.
 *
 * @see Opora\Core\Config\CorsConfig
 */
final class CorsConfigTest extends TestCase
{
    public function test_constructor_stores_allowed_origins(): void
    {
        $corsConfig = new CorsConfig(
            allowedOrigins: ['https://example.com'],
            allowedMethods: ['GET'],
            allowedHeaders: ['X-Custom'],
            maxAge: 3600,
            allowCredentials: false,
        );

        self::assertSame(['https://example.com'], $corsConfig->allowedOrigins);
    }

    public function test_constructor_stores_allowed_methods(): void
    {
        $corsConfig = new CorsConfig(
            allowedOrigins: ['*'],
            allowedMethods: ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'],
            allowedHeaders: ['Content-Type', 'Authorization'],
            maxAge: 7200,
            allowCredentials: false,
        );

        self::assertSame(
            ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'],
            $corsConfig->allowedMethods,
        );
    }

    public function test_constructor_stores_allowed_headers(): void
    {
        $corsConfig = new CorsConfig(
            allowedOrigins: ['*'],
            allowedMethods: ['GET'],
            allowedHeaders: ['Content-Type', 'Authorization', 'X-Request-Id'],
            maxAge: 3600,
            allowCredentials: false,
        );

        self::assertSame(
            ['Content-Type', 'Authorization', 'X-Request-Id'],
            $corsConfig->allowedHeaders,
        );
    }

    public function test_constructor_stores_max_age(): void
    {
        $corsConfig = new CorsConfig(
            allowedOrigins: ['*'],
            allowedMethods: ['GET'],
            allowedHeaders: [],
            maxAge: 86400,
            allowCredentials: false,
        );

        self::assertSame(86400, $corsConfig->maxAge);
    }

    public function test_constructor_stores_allow_credentials(): void
    {
        $corsConfig = new CorsConfig(
            allowedOrigins: ['https://app.example.com'],
            allowedMethods: ['GET'],
            allowedHeaders: [],
            maxAge: 3600,
            allowCredentials: true,
        );

        self::assertTrue($corsConfig->allowCredentials);
    }

    public function test_default_methods_are_restful_defaults(): void
    {
        $corsConfig = new CorsConfig(
            allowedOrigins: ['*'],
            allowedMethods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            allowedHeaders: ['Content-Type', 'Authorization', 'X-Request-Id'],
            maxAge: 7200,
            allowCredentials: false,
        );

        self::assertContains('GET', $corsConfig->allowedMethods);
        self::assertContains('POST', $corsConfig->allowedMethods);
        self::assertContains('OPTIONS', $corsConfig->allowedMethods);
    }

    public function test_allow_credentials_false_by_default(): void
    {
        $corsConfig = new CorsConfig(
            allowedOrigins: ['*'],
            allowedMethods: ['GET'],
            allowedHeaders: [],
            maxAge: 3600,
            allowCredentials: false,
        );

        self::assertFalse($corsConfig->allowCredentials);
    }

    public function test_allows_wildcard_origin(): void
    {
        $corsConfig = new CorsConfig(
            allowedOrigins: ['*'],
            allowedMethods: ['GET'],
            allowedHeaders: [],
            maxAge: 3600,
            allowCredentials: false,
        );

        self::assertContains('*', $corsConfig->allowedOrigins);
    }

    public function test_empty_allowed_origins_is_valid(): void
    {
        $corsConfig = new CorsConfig(
            allowedOrigins: [],
            allowedMethods: ['GET'],
            allowedHeaders: [],
            maxAge: 0,
            allowCredentials: false,
        );

        self::assertEmpty($corsConfig->allowedOrigins);
    }
}
