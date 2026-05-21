<?php

declare(strict_types=1);

use Opora\Core\Http\MiddlewarePipeline;
use Opora\Core\Http\NotFoundHandler;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Definitions\Reference;
use Yiisoft\Di\Reference\TagReference;

/**
 * DI-контейнер: web-specific сервисы.
 *
 * MiddlewarePipeline собирает все middleware, зарегистрированные с тегом
 * 'opora.middleware', сортирует по priority() и строит PSR-15 цепочку.
 *
 * NotFoundHandler — финальный handler, возвращает 404 JSON когда
 * ни один middleware не сформировал ответ.
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
];
