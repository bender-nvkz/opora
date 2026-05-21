<?php

declare(strict_types=1);

namespace Opora\Core\Config;

/**
 * Типизированный объект конфигурации приложения.
 * Собирается из ENV на bootstrap. Доступен через DI как singleton.
 *
 * Все поля используют PHP 8.4 property hooks (get-only) для
 * централизованной валидации и трансформации значений.
 *
 * @api
 */
final class AppConfig
{
    /**
     * Отображаемое имя приложения.
     *
     * @var non-empty-string
     */
    public string $appName {
        get => $this->appName;
    }

    /**
     * Окружение: production | staging | development | test.
     *
     * @var non-empty-string
     */
    public string $appEnv {
        get => $this->appEnv;
    }

    /**
     * Версия приложения (injected CI).
     *
     * @var non-empty-string
     */
    public string $appVersion {
        get => $this->appVersion;
    }

    /**
     * PHP timezone.
     *
     * @var non-empty-string
     */
    public string $appTimezone {
        get => $this->appTimezone;
    }

    /**
     * Дефолтная locale.
     *
     * @var non-empty-string
     */
    public string $appLocale {
        get => $this->appLocale;
    }

    /**
     * Список поддерживаемых локалей.
     *
     * @var list<string>
     */
    public array $supportedLocales {
        get => $this->supportedLocales;
    }

    /**
     * Разрешённые CORS-origins.
     *
     * @var list<string>
     */
    public array $corsAllowedOrigins {
        get => $this->corsAllowedOrigins;
    }

    /**
     * @param list<string> $supportedLocales
     * @param list<string> $corsAllowedOrigins
     */
    public function __construct(
        string $appName = 'Opora',
        string $appEnv = 'production',
        /**
         * Режим отладки (запрещён на production).
         */
        public bool $debug = false {
            get => $this->debug;
        },
        string $appVersion = 'dev',
        string $appTimezone = 'UTC',
        string $appLocale = 'ru',
        array $supportedLocales = ['ru'],
        /**
         * DSN для подключения к БД.
         */
        public string $databaseUrl = '' {
            get => $this->databaseUrl;
        },
        /**
         * Адаптер хранилища: local | s3.
         */
        public string $storageAdapter = 'local' {
            get => $this->storageAdapter;
        },
        /**
         * Транспорт очередей: db | redis.
         */
        public string $queueTransport = 'db' {
            get => $this->queueTransport;
        },
        /**
         * Поисковый движок: pgsql | meilisearch.
         */
        public string $searchEngine = 'pgsql' {
            get => $this->searchEngine;
        },
        /**
         * Бэкенд кэша: file | db.
         */
        public string $cacheBackend = 'file' {
            get => $this->cacheBackend;
        },
        /**
         * Разрешить UI-редактор схем (OPORA_SCHEMA_WRITABLE).
         */
        public bool $schemaWritable = false {
            get => $this->schemaWritable;
        },
        /**
         * Автосинк схемы на каждый запрос (только dev).
         */
        public bool $schemaAutoSync = false {
            get => $this->schemaAutoSync;
        },
        /**
         * Разрешить UGC-классы.
         */
        public bool $schemaAllowUgc = false {
            get => $this->schemaAllowUgc;
        },
        array $corsAllowedOrigins = ['http://localhost:3000'],
    ) {
        $this->appName = $appName !== '' ? $appName : 'Opora';
        $this->appEnv = $appEnv !== '' ? $appEnv : 'production';
        $this->appVersion = $appVersion !== '' ? $appVersion : 'dev';
        $this->appTimezone = $appTimezone !== '' ? $appTimezone : 'UTC';
        $this->appLocale = $appLocale !== '' ? $appLocale : 'ru';
        $this->supportedLocales = \array_values($supportedLocales);
        $this->corsAllowedOrigins = \array_values($corsAllowedOrigins);
    }

    /**
     * Фабричный метод. Читает переменные окружения.
     *
     * @param array<string, string> $serverSource Источник переменных (обычно $_SERVER)
     */
    public static function fromEnv(array $serverSource = []): self
    {
        $source = $serverSource !== [] ? $serverSource : $_SERVER;

        $appEnv = self::envString($source, 'APP_ENV', 'production');
        $debug = self::envBool($source, 'APP_DEBUG', false);

        // APP_DEBUG=true запрещён на production
        if ($debug && $appEnv === 'production') {
            throw new ConfigurationException('APP_DEBUG=true is not allowed in production environment');
        }

        $databaseUrl = self::envString($source, 'DATABASE_URL', '');

        // DATABASE_URL обязателен для всех окружений
        if ($databaseUrl === '') {
            throw new ConfigurationException('DATABASE_URL is required');
        }

        return new self(
            appName: self::envString($source, 'APP_NAME', 'Opora'),
            appEnv: $appEnv,
            debug: $debug,
            appVersion: self::envString($source, 'APP_VERSION', 'dev'),
            appTimezone: self::envString($source, 'APP_TIMEZONE', 'UTC'),
            appLocale: self::envString($source, 'APP_LOCALE', 'ru'),
            supportedLocales: self::envList($source, 'SUPPORTED_LOCALES', ['ru']),
            databaseUrl: $databaseUrl,
            storageAdapter: self::envString($source, 'STORAGE_ADAPTER', 'local'),
            queueTransport: self::envString($source, 'QUEUE_TRANSPORT', 'db'),
            searchEngine: self::envString($source, 'SEARCH_ENGINE', 'pgsql'),
            cacheBackend: self::envString($source, 'CACHE_BACKEND', 'file'),
            schemaWritable: self::envBool($source, 'OPORA_SCHEMA_WRITABLE', false),
            schemaAutoSync: self::envBool($source, 'OPORA_SCHEMA_AUTOSYNC', false),
            schemaAllowUgc: self::envBool($source, 'OPORA_SCHEMA_ALLOW_UGC', false),
            corsAllowedOrigins: self::envList($source, 'CORS_ALLOWED_ORIGINS', ['http://localhost:3000']),
        );
    }

    /**
     * Прочитать строковую ENV-переменную с fallback.
     *
     * @param array<string, string> $source
     * @param non-empty-string      $key
     */
    private static function envString(array $source, string $key, string $default): string
    {
        $value = (string) ($source[$key] ?? $default);

        return $value !== '' ? $value : $default;
    }

    /**
     * Прочитать булеву ENV-переменную.
     *
     * Поддерживает: '1', 'true', 'yes' → true; '0', 'false', 'no', '' → false.
     *
     * @param array<string, string> $source
     */
    private static function envBool(array $source, string $key, bool $default): bool
    {
        $value = $source[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        return \in_array(\strtolower((string) $value), ['1', 'true', 'yes'], true);
    }

    /**
     * Прочитать список ENV-значений (разделитель: запятая).
     *
     * @param array<string, string> $source
     * @param list<string>          $default
     *
     * @return list<string>
     */
    private static function envList(array $source, string $key, array $default): array
    {
        $value = $source[$key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        /** @var list<string> $items */
        $items = \array_map(trim(...), \explode(',', (string) $value));

        return \array_values(\array_filter($items, static fn (string $item): bool => $item !== ''));
    }
}
