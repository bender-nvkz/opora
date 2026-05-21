<?php

declare(strict_types=1);

namespace Opora\Core\Tests\Unit\Http;

use Opora\Core\Http\MiddlewareInterface;
use Opora\Core\Http\MiddlewarePipeline;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @covers \Opora\Core\Http\MiddlewarePipeline
 * @covers \Opora\Core\Http\MiddlewareChainLink
 */
final class MiddlewarePipelineTest extends TestCase
{
    public function test_handle_executes_middleware_in_priority_order(): void
    {
        $executionOrder = [];

        $middlewareSpyP10 = new MiddlewareSpyP10(static function (ServerRequestInterface $serverRequest, RequestHandlerInterface $requestHandler) use (&$executionOrder): ResponseInterface {
            $executionOrder[] = 10;

            return $requestHandler->handle($serverRequest);
        });

        $middlewareSpyP20 = new MiddlewareSpyP20(static function (ServerRequestInterface $serverRequest, RequestHandlerInterface $requestHandler) use (&$executionOrder): ResponseInterface {
            $executionOrder[] = 20;

            return $requestHandler->handle($serverRequest);
        });

        $middlewareSpyP5 = new MiddlewareSpyP5(static function (ServerRequestInterface $serverRequest, RequestHandlerInterface $requestHandler) use (&$executionOrder): ResponseInterface {
            $executionOrder[] = 5;

            return $requestHandler->handle($serverRequest);
        });

        $finalHandler = $this->createMock(RequestHandlerInterface::class);
        $finalHandler
            ->expects(self::once())
            ->method('handle')
            ->willReturnCallback(function () use (&$executionOrder): ResponseInterface {
                $executionOrder[] = 999;

                return $this->createMock(ResponseInterface::class);
            });

        $middlewarePipeline = new MiddlewarePipeline(
            middlewares: [$middlewareSpyP10, $middlewareSpyP20, $middlewareSpyP5],
            finalHandler: $finalHandler,
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $middlewarePipeline->handle($request);

        self::assertSame([5, 10, 20, 999], $executionOrder, 'Middleware должны выполняться по priority() asc');
    }

    public function test_handle_calls_final_handler_with_empty_middleware_list(): void
    {
        $finalHandler = $this->createMock(RequestHandlerInterface::class);
        $finalHandler
            ->expects(self::once())
            ->method('handle')
            ->willReturn($this->createMock(ResponseInterface::class));

        $middlewarePipeline = new MiddlewarePipeline(
            middlewares: [],
            finalHandler: $finalHandler,
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $middlewarePipeline->handle($request);

        self::assertNotNull($response); /** @phpstan-ignore staticMethod.alreadyNarrowedType */
    }

    public function test_handle_passes_request_through_chain_with_attributes(): void
    {
        $middlewareSpyP10 = new MiddlewareSpyP10(static function (ServerRequestInterface $serverRequest, RequestHandlerInterface $requestHandler): ResponseInterface {
            $serverRequest = $serverRequest->withAttribute('test', 'pass');

            return $requestHandler->handle($serverRequest);
        });

        $requestWithAttr = $this->createMock(ServerRequestInterface::class);
        $requestWithAttr
            ->method('getAttribute')
            ->with('test') /** @phpstan-ignore method.notFound */
            ->willReturn('pass');

        $request = $this->createMock(ServerRequestInterface::class);
        $request
            ->method('withAttribute')
            ->with('test', 'pass') /** @phpstan-ignore method.notFound */
            ->willReturn($requestWithAttr);

        $finalHandler = $this->createMock(RequestHandlerInterface::class);
        $finalHandler
            ->expects(self::once())
            ->method('handle')
            ->willReturnCallback(function (ServerRequestInterface $serverRequest): ResponseInterface {
                self::assertSame('pass', $serverRequest->getAttribute('test'), 'Request attribute должен пробрасываться через цепочку');

                return $this->createMock(ResponseInterface::class);
            });

        $middlewarePipeline = new MiddlewarePipeline(
            middlewares: [$middlewareSpyP10],
            finalHandler: $finalHandler,
        );

        $middlewarePipeline->handle($request);
    }

    public function test_handle_middleware_can_short_circuit(): void
    {
        $expectedResponse = $this->createMock(ResponseInterface::class);

        $middlewareSpyP10 = new MiddlewareSpyP10(static fn (): ResponseInterface => $expectedResponse);

        $finalHandler = $this->createMock(RequestHandlerInterface::class);
        $finalHandler
            ->expects(self::never())
            ->method('handle');

        $middlewarePipeline = new MiddlewarePipeline(
            middlewares: [$middlewareSpyP10],
            finalHandler: $finalHandler,
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $middlewarePipeline->handle($request);

        self::assertSame($expectedResponse, $response, 'Middleware должен вернуть ответ без вызова handler при short-circuit');
    }

    public function test_handle_does_not_sort_if_single_middleware(): void
    {
        $response = $this->createMock(ResponseInterface::class);

        $middlewareSpyP42 = new MiddlewareSpyP42(static fn (ServerRequestInterface $serverRequest, RequestHandlerInterface $requestHandler): ResponseInterface => $requestHandler->handle($serverRequest));

        $finalHandler = $this->createMock(RequestHandlerInterface::class);
        $finalHandler
            ->expects(self::once())
            ->method('handle')
            ->willReturn($response);

        $middlewarePipeline = new MiddlewarePipeline(
            middlewares: [$middlewareSpyP42],
            finalHandler: $finalHandler,
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $result = $middlewarePipeline->handle($request);

        self::assertSame($response, $result);
    }
}

/**
 * Тестовый middleware с приоритетом 5.
 *
 * @internal
 */
final readonly class MiddlewareSpyP5 implements MiddlewareInterface
{
    /**
     * @param callable(ServerRequestInterface, RequestHandlerInterface): ResponseInterface $process
     */
    public function __construct(
        private mixed $process,
    ) {
    }

    public static function priority(): int
    {
        return 5;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return ($this->process)($request, $handler);
    }
}

/**
 * Тестовый middleware с приоритетом 10.
 *
 * @internal
 */
final readonly class MiddlewareSpyP10 implements MiddlewareInterface
{
    /**
     * @param callable(ServerRequestInterface, RequestHandlerInterface): ResponseInterface $process
     */
    public function __construct(
        private mixed $process,
    ) {
    }

    public static function priority(): int
    {
        return 10;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return ($this->process)($request, $handler);
    }
}

/**
 * Тестовый middleware с приоритетом 20.
 *
 * @internal
 */
final readonly class MiddlewareSpyP20 implements MiddlewareInterface
{
    /**
     * @param callable(ServerRequestInterface, RequestHandlerInterface): ResponseInterface $process
     */
    public function __construct(
        private mixed $process,
    ) {
    }

    public static function priority(): int
    {
        return 20;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return ($this->process)($request, $handler);
    }
}

/**
 * Тестовый middleware с приоритетом 42.
 *
 * @internal
 */
final readonly class MiddlewareSpyP42 implements MiddlewareInterface
{
    /**
     * @param callable(ServerRequestInterface, RequestHandlerInterface): ResponseInterface $process
     */
    public function __construct(
        private mixed $process,
    ) {
    }

    public static function priority(): int
    {
        return 42;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return ($this->process)($request, $handler);
    }
}
