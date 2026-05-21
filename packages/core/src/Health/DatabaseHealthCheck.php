<?php

declare(strict_types=1);

namespace Opora\Core\Health;

use Cycle\Database\DatabaseProviderInterface;

/**
 * Health check: проверка доступности БД.
 *
 * Пытается выполнить SELECT 1 через Cycle DatabaseProvider.
 * При неудаче возвращает статус 'error' с сообщением исключения.
 *
 * @api
 */
final readonly class DatabaseHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private DatabaseProviderInterface $databaseProvider,
    ) {
    }

    public function name(): string
    {
        return 'database';
    }

    public function check(): HealthCheckResult
    {
        $start = \microtime(true);

        try {
            $db = $this->databaseProvider->database();
            $db->query('SELECT 1')->fetchAll();

            $latency = (\microtime(true) - $start) * 1000.0;

            return new HealthCheckResult(
                status: 'ok',
                latencyMs: $latency,
            );
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            return new HealthCheckResult(
                status: 'error',
                message: $message !== '' ? $message : null,
            );
        }
    }
}
