<?php

declare(strict_types=1);

use Opora\Core\Http\HealthController;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteCollectorInterface;

/**
 * Маршруты приложения.
 *
 * Каждый элемент массива — callable, принимающий RouteCollectorInterface.
 * Обрабатываются в Application::run() после сборки DI-контейнера.
 *
 * @see \Opora\Core\Application::run()
 */
return [
    static function (RouteCollectorInterface $routeCollector): void {
        $routeCollector->addRoute(
            Route::get('/api/health')
                ->action([HealthController::class, '__invoke'])
                ->name('health.check'),
        );
    },
];
