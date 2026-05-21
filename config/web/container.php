<?php

declare(strict_types=1);

use Opora\Core\Http\MiddlewarePipeline;
use Opora\Core\Http\NotFoundHandler;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Definitions\Reference;
use Yiisoft\Di\Reference\TagReference;
use Yiisoft\Middleware\Dispatcher\MiddlewareFactory;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\FastRoute\UrlMatcher;
use Yiisoft\Router\Middleware\Router;
use Yiisoft\Router\RouteCollector;
use Yiisoft\Router\RouteCollectorInterface;
use Yiisoft\Router\RouteCollection;
use Yiisoft\Router\RouteCollectionInterface;
use Yiisoft\Router\UrlMatcherInterface;

/**
 * DI-контейнер: web-specific сервисы.
 *
 * MiddlewarePipeline собирает все middleware, зарегистрированные с тегом
 * 'opora.middleware', сортирует по priority() и строит PSR-15 цепочку.
 *
 * NotFoundHandler — финальный handler, возвращает 404 JSON когда
 * ни один middleware не сформировал ответ.
 *
 * === yiisoft/router wiring ===
 * RouteCollector → RouteCollection → UrlMatcher → Yiisoft Router Middleware.
 * Маршруты регистрируются в config/routes/api.php (Шаг 9: HealthController).
 * До этого момента роутер возвращает 404 для всех запросов —
 * обрабатывается NotFoundHandler.
 */
return [
    NotFoundHandler::class => [
        'class' => NotFoundHandler::class,
        '__construct()' => [
            'responseFactory' => Reference::to(ResponseFactoryInterface::class),
        ],
    ],

    // Алиас: NotFoundHandler используется как финальный handler по умолчанию
    RequestHandlerInterface::class => Reference::to(NotFoundHandler::class),

    MiddlewarePipeline::class => [
        'class' => MiddlewarePipeline::class,
        '__construct()' => [
            'middlewares' => TagReference::to('opora.middleware'),
            'finalHandler' => Reference::to(RequestHandlerInterface::class),
        ],
    ],

    // === yiisoft/router: цепочка зависимостей ===

    // Route collector — хранит Route/Group объекты (мутабельный контейнер)
    RouteCollector::class => [
        'class' => RouteCollector::class,
    ],
    RouteCollectorInterface::class => Reference::to(RouteCollector::class),

    // Route collection — обёртка над collector с ленивой загрузкой
    RouteCollection::class => [
        'class' => RouteCollection::class,
        '__construct()' => [
            'collector' => Reference::to(RouteCollectorInterface::class),
        ],
    ],
    RouteCollectionInterface::class => Reference::to(RouteCollection::class),

    // URL matcher — FastRoute-based resolution
    UrlMatcherInterface::class => [
        'class' => UrlMatcher::class,
        '__construct()' => [
            'routeCollection' => Reference::to(RouteCollectionInterface::class),
        ],
    ],

    // Current route — хранит информацию о текущем совпавшем маршруте
    CurrentRoute::class => [
        'class' => CurrentRoute::class,
    ],

    // Middleware factory — разрешает экшены контроллеров через DI-контейнер
    MiddlewareFactory::class => [
        'class' => MiddlewareFactory::class,
        '__construct()' => [
            'container' => Reference::to(ContainerInterface::class),
        ],
    ],

    // Yiisoft Router PSR-15 middleware — match → dispatch → response
    Router::class => [
        'class' => Router::class,
        '__construct()' => [
            'matcher' => Reference::to(UrlMatcherInterface::class),
            'responseFactory' => Reference::to(ResponseFactoryInterface::class),
            'middlewareFactory' => Reference::to(MiddlewareFactory::class),
            'currentRoute' => Reference::to(CurrentRoute::class),
        ],
    ],
];
