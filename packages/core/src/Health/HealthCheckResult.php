<?php

declare(strict_types=1);

namespace Opora\Core\Health;

/**
 * Результат единичной health check-процедуры.
 *
 * Содержит статус проверки, опциональное сообщение и время выполнения.
 * Используется как возвращаемое значение {@see HealthCheckInterface::check()}.
 *
 * @api
 */
final readonly class HealthCheckResult
{
    /**
     * @param 'ok'|'error'          $status    Результат проверки.
     * @param non-empty-string|null $message   Описание ошибки или контекст.
     * @param float|null            $latencyMs Время выполнения в миллисекундах.
     */
    public function __construct(
        public string $status,
        public null|string $message = null,
        public null|float $latencyMs = null,
    ) {
    }
}
