<?php

declare(strict_types=1);

namespace Opora\Core\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Композитор middleware-стека: собирает несортированный список MiddlewareInterface,
 * сортирует по priority() (ascending), строит PSR-15 цепочку вызовов.
 *
 * Последний middleware в цепочке делегирует $finalHandler.
 * Каждый новый модуль добавляет свой middleware через DI-тег 'opora.middleware',
 * pipeline автоматически размещает его на правильной позиции.
 *
 * @api
 */
final readonly class MiddlewarePipeline implements RequestHandlerInterface
{
    /** @var array<MiddlewareInterface> */
    private array $middlewares;

    /**
     * @param iterable<MiddlewareInterface> $middlewares  Несортированный список middleware
     * @param RequestHandlerInterface       $finalHandler Финальный handler (router или 404)
     */
    public function __construct(
        iterable $middlewares,
        private RequestHandlerInterface $finalHandler,
    ) {
        $this->middlewares = \is_array($middlewares) ? $middlewares : \array_values(\iterator_to_array($middlewares));
    }

    /**
     * Принимает iterable (в т.ч. Yii3 TaggedIterator) и конвертирует в array.
     *
     * @param iterable<MiddlewareInterface> $middlewares
     *
     * @internal
     */
    public static function fromIterable(iterable $middlewares, RequestHandlerInterface $finalHandler): self
    {
        return new self(middlewares: $middlewares, finalHandler: $finalHandler);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $chain = $this->buildChain();

        return $chain->handle($request);
    }

    /**
     * Построить цепочку middleware, отсортированную по priority().
     *
     * Первый middleware (с наименьшим priority) выполняется первым,
     * последний делегирует $finalHandler.
     */
    private function buildChain(): RequestHandlerInterface
    {
        $sorted = $this->getSorted();
        $handler = $this->finalHandler;

        /** @var MiddlewareInterface $middleware */
        foreach (\array_reverse($sorted) as $middleware) {
            $handler = new MiddlewareChainLink($middleware, $handler);
        }

        return $handler;
    }

    /**
     * Отсортировать middleware по priority() (ascending).
     *
     * @return list<MiddlewareInterface>
     */
    private function getSorted(): array
    {
        $sorted = $this->middlewares;
        \usort(
            $sorted,
            static fn (MiddlewareInterface $a, MiddlewareInterface $b): int => $a::priority() <=> $b::priority(),
        );

        return $sorted;
    }
}

/**
 * Внутреннее звено цепочки: вызывает MiddlewareInterface::process() с
 * переданным handler, который в свою очередь вызывает следующее звено.
 *
 * @internal
 */
final readonly class MiddlewareChainLink implements RequestHandlerInterface
{
    public function __construct(
        private MiddlewareInterface $middleware,
        private RequestHandlerInterface $next,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->middleware->process($request, $this->next);
    }
}
