<?php

declare(strict_types=1);

namespace Opora\Core\Health;

/**
 * Контракт для единичной health check-процедуры.
 *
 * Каждая проверка возвращает {@see HealthCheckResult} с статусом 'ok' или 'error'.
 * Множественные проверки собираются {@see HealthController} через DI tag 'opora.health.check'.
 *
 * @api
 */
interface HealthCheckInterface
{
    /**
     * Уникальное имя проверки.
     *
     * @return non-empty-string Например 'database', 'cache', 'storage'.
     */
    public function name(): string;

    /**
     * Выполнить проверку.
     */
    public function check(): HealthCheckResult;
}
