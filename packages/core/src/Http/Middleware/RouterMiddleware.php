<?php

declare(strict_types=1);

namespace Opora\Core\Http\Middleware;

use Opora\Core\Http\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Router\Middleware\Router;

/**
 * Приоритет 200. Последний в middleware-стеке.
 *
 * Тонкая обёртка над {@see Router} из yiisoft/router,
 * добавляющая приоритет для сортировки в MiddlewarePipeline.
 *
 * Вся логика маршрутизации делегируется yiisoft/router:
 * - {@see UrlMatcherInterface::match()} — поиск маршрута
 * - Успех → диспатч middleware-стека маршрута через MiddlewareDispatcher
 * - Метод не разрешён → 405 с заголовком Allow
 * - OPTIONS при MethodFailure → 204
 * - Маршрут не найден → передаёт в следующий handler (NotFoundHandler → 404)
 *
 * @api
 */
final readonly class RouterMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Router $router,
    ) {
    }

    public static function priority(): int
    {
        return 200;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        return $this->router->process($request, $handler);
    }
}
