<?php

declare(strict_types=1);

namespace Opora\Core;

/**
 * Точка входа: читает ENV, строит DI-контейнер, регистрирует middleware-стек,
 * запускает HTTP или Console runner.
 *
 * @api
 *
 * @todo Реализовать полную интеграцию с Yii3 DI, middleware pipeline и роутером в Stage 1.
 *       Сейчас — минимальный stub для валидации Docker-инфраструктуры.
 */
final class Application
{
    /**
     * HTTP entrypoint.
     * В Stage 1 будет принимать ServerRequestInterface, прогонять через middleware pipeline
     * и возвращать ResponseInterface через SapiEmitter.
     */
    public function run(): void
    {
        echo 'OK';
    }

    /**
     * Console entrypoint.
     * В Stage 1 будет загружать Yii3 Console Application и запускать команду.
     *
     * @return int Exit code (0 = success).
     */
    public function start(): int
    {
        echo 'Opora CLI' . \PHP_EOL;

        return 0;
    }
}
