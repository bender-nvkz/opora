<?php

declare(strict_types=1);

namespace Opora\Core\Tests\Unit\Config;

use Opora\Core\Config\AppConfig;
use Opora\Core\Config\ConfigurationException;
use PHPUnit\Framework\TestCase;

/**
 * Specification для AppConfig.
 *
 * @see Opora\Core\Config\AppConfig
 */
final class AppConfigTest extends TestCase
{
    /**
     * Валидная production-конфигурация.
     */
    public function test_fromEnv_creates_valid_production_config(): void
    {
        $appConfig = AppConfig::fromEnv([
            'APP_ENV' => 'production',
            'APP_DEBUG' => '0',
            'APP_NAME' => 'Opora',
            'APP_VERSION' => '1.0.0',
            'APP_TIMEZONE' => 'Europe/Moscow',
            'APP_LOCALE' => 'ru',
            'DATABASE_URL' => 'pgsql://user:pass@localhost:5432/db',
            'OPORA_SCHEMA_WRITABLE' => '0',
        ]);

        self::assertSame('production', $appConfig->appEnv);
        self::assertFalse($appConfig->debug);
        self::assertSame('Opora', $appConfig->appName);
        self::assertSame('1.0.0', $appConfig->appVersion);
        self::assertSame('Europe/Moscow', $appConfig->appTimezone);
        self::assertSame('ru', $appConfig->appLocale);
        self::assertSame('pgsql://user:pass@localhost:5432/db', $appConfig->databaseUrl);
        self::assertFalse($appConfig->schemaWritable);
    }

    /**
     * APP_DEBUG=true запрещён на production.
     */
    public function test_fromEnv_throws_when_debug_enabled_in_production(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('APP_DEBUG=true is not allowed in production environment');

        AppConfig::fromEnv([
            'APP_ENV' => 'production',
            'APP_DEBUG' => '1',
            'DATABASE_URL' => 'pgsql://user:pass@localhost:5432/db',
        ]);
    }

    /**
     * DATABASE_URL обязателен для всех окружений.
     */
    public function test_fromEnv_throws_when_database_url_missing(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('DATABASE_URL is required');

        AppConfig::fromEnv([
            'APP_ENV' => 'development',
            'APP_DEBUG' => '1',
        ]);
    }

    /**
     * Пустой APP_ENV = production (default).
     */
    public function test_fromEnv_defaults_to_production_when_env_empty(): void
    {
        $appConfig = AppConfig::fromEnv([
            'APP_ENV' => '',
            'DATABASE_URL' => 'pgsql://user:pass@localhost:5432/db',
        ]);

        self::assertSame('production', $appConfig->appEnv);
    }

    /**
     * debug=true разрешён на development.
     */
    public function test_fromEnv_allows_debug_in_development(): void
    {
        $appConfig = AppConfig::fromEnv([
            'APP_ENV' => 'development',
            'APP_DEBUG' => '1',
            'DATABASE_URL' => 'pgsql://user:pass@localhost:5432/db',
        ]);

        self::assertTrue($appConfig->debug);
        self::assertSame('development', $appConfig->appEnv);
    }

    /**
     * Парсинг булевых значений: '1', 'true', 'yes' → true; '0', 'false', 'no', '' → false.
     */
    public function test_fromEnv_parses_boolean_values_correctly(): void
    {
        $appConfig = AppConfig::fromEnv([
            'APP_ENV' => 'development',
            'APP_DEBUG' => '1',
            'DATABASE_URL' => 'pgsql://user:pass@localhost:5432/db',
            'OPORA_SCHEMA_WRITABLE' => 'true',
            'OPORA_SCHEMA_AUTOSYNC' => 'yes',
            'OPORA_SCHEMA_ALLOW_UGC' => '0',
        ]);

        self::assertTrue($appConfig->debug);
        self::assertTrue($appConfig->schemaWritable);
        self::assertTrue($appConfig->schemaAutoSync);
        self::assertFalse($appConfig->schemaAllowUgc);
    }

    /**
     * Парсинг списков через запятую.
     */
    public function test_fromEnv_parses_list_values_correctly(): void
    {
        $appConfig = AppConfig::fromEnv([
            'APP_ENV' => 'development',
            'APP_DEBUG' => '1',
            'DATABASE_URL' => 'pgsql://user:pass@localhost:5432/db',
            'CORS_ALLOWED_ORIGINS' => 'https://app.example.com,https://admin.example.com',
            'SUPPORTED_LOCALES' => 'ru,en,de',
        ]);

        self::assertSame(['https://app.example.com', 'https://admin.example.com'], $appConfig->corsAllowedOrigins);
        self::assertSame(['ru', 'en', 'de'], $appConfig->supportedLocales);
    }

    /**
     * Значения по умолчанию для всех опциональных полей.
     */
    public function test_fromEnv_uses_defaults_for_optional_fields(): void
    {
        $appConfig = AppConfig::fromEnv([
            'APP_ENV' => 'development',
            'APP_DEBUG' => '1',
            'DATABASE_URL' => 'pgsql://user:pass@localhost:5432/db',
        ]);

        self::assertSame('Opora', $appConfig->appName);
        self::assertSame('dev', $appConfig->appVersion);
        self::assertSame('UTC', $appConfig->appTimezone);
        self::assertSame('ru', $appConfig->appLocale);
        self::assertSame(['ru'], $appConfig->supportedLocales);
        self::assertSame('local', $appConfig->storageAdapter);
        self::assertSame('db', $appConfig->queueTransport);
        self::assertSame('pgsql', $appConfig->searchEngine);
        self::assertSame('file', $appConfig->cacheBackend);
        self::assertFalse($appConfig->schemaWritable);
        self::assertFalse($appConfig->schemaAutoSync);
        self::assertFalse($appConfig->schemaAllowUgc);
        self::assertSame(['http://localhost:3000'], $appConfig->corsAllowedOrigins);
    }
}
