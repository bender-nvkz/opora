<?php

declare(strict_types=1);

namespace Opora\Core\Config;

/**
 * Типизированный объект конфигурации приложения.
 * Собирается из ENV на bootstrap. Доступен через DI как singleton.
 *
 * @api
 *
 * @todo Добавить PHP 8.4 property hooks для всех полей в Stage 1.
 *       Добавить поля: appTimezone, appLocale, supportedLocales, databaseDsn,
 *       storageAdapter, queueTransport, searchEngine, cacheBackend,
 *       schemaWritable, schemaAutoSync, schemaAllowUgc.
 */
final readonly class AppConfig
{
    /**
     * @param non-empty-string $appEnv Окружение: production | staging | development | test
     * @param bool             $debug  Режим отладки (запрещён на production)
     */
    public function __construct(
        public string $appEnv = 'production',
        public bool $debug = false,
    ) {
    }

    /**
     * Фабричный метод. Читает переменные окружения.
     *
     * @param array<string, string> $serverSource Источник переменных (обычно $_SERVER)
     */
    public static function fromEnv(array $serverSource = []): self
    {
        $source = $serverSource !== [] ? $serverSource : $_SERVER;
        $appEnv = (string) ($source['APP_ENV'] ?? 'production');

        return new self(
            appEnv: $appEnv !== '' ? $appEnv : 'production',
            debug: (bool) ($source['APP_DEBUG'] ?? false),
        );
    }
}
