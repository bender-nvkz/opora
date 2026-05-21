<?php

declare(strict_types=1);

use Opora\Core\Config\AppConfig;
use Opora\Core\Config\CorsConfig;
use Opora\Core\Config\SecurityHeadersConfig;
use Opora\Core\Http\Middleware\BodyParserMiddleware;
use Opora\Core\Http\Middleware\CorsMiddleware;
use Opora\Core\Http\Middleware\ErrorHandlerMiddleware;
use Opora\Core\Http\Middleware\RequestIdMiddleware;
use Opora\Core\Http\Middleware\RouterMiddleware;
use Opora\Core\Http\Middleware\SecurityHeadersMiddleware;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Definitions\Reference;
use Yiisoft\Router\Middleware\Router;

/**
 * Middleware-стек: каждый middleware регистрируется с тегом 'opora.middleware'.
 *
 * MiddlewarePipeline автоматически собирает все middleware по этому тегу
 * и сортирует по priority() (ascending) в рантайме.
 *
 * @see \Opora\Core\Http\MiddlewarePipeline
 */
return [
    ErrorHandlerMiddleware::class => [
        'class' => ErrorHandlerMiddleware::class,
        '__construct()' => [
            'logger' => Reference::to(LoggerInterface::class),
            'responseFactory' => Reference::to(ResponseFactoryInterface::class),
            'appConfig' => Reference::to(AppConfig::class),
        ],
        'tags' => ['opora.middleware'],
    ],

    RequestIdMiddleware::class => [
        'class' => RequestIdMiddleware::class,
        'tags' => ['opora.middleware'],
    ],

    CorsMiddleware::class => [
        'class' => CorsMiddleware::class,
        '__construct()' => [
            'config' => Reference::to(CorsConfig::class),
        ],
        'tags' => ['opora.middleware'],
    ],

    SecurityHeadersMiddleware::class => [
        'class' => SecurityHeadersMiddleware::class,
        '__construct()' => [
            'config' => Reference::to(SecurityHeadersConfig::class),
        ],
        'tags' => ['opora.middleware'],
    ],

    BodyParserMiddleware::class => [
        'class' => BodyParserMiddleware::class,
        'tags' => ['opora.middleware'],
    ],

    RouterMiddleware::class => [
        'class' => RouterMiddleware::class,
        '__construct()' => [
            'router' => Reference::to(Router::class),
        ],
        'tags' => ['opora.middleware'],
    ],
];
