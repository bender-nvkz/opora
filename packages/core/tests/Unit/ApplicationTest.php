<?php

declare(strict_types=1);

namespace Opora\Core\Tests\Unit;

use HttpSoft\Message\ServerRequest;
use Opora\Core\Application;
use Opora\Core\Http\MiddlewarePipeline;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Router\RouteCollectorInterface;

/**
 * @covers \Opora\Core\Application
 *
 * @see Application::handleRequest()
 */
final class ApplicationTest extends TestCase
{
    public function test_handleRequest_does_not_throw(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $application = $this->createApplicationWithMocks($response);

        $result = $application->handleRequest(new ServerRequest([], [], [], [], null, 'GET', '/'));

        $this->assertSame($response, $result);
    }

    public function test_handleRequest_passes_request_to_pipeline(): void
    {
        $serverRequest = new ServerRequest([], [], [], [], null, 'POST', '/api/data');
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($serverRequest))
            ->willReturn($response);

        $application = $this->createApplicationWithHandler($handler);

        $result = $application->handleRequest($serverRequest);

        $this->assertSame($response, $result);
    }

    public function test_handleRequest_returns_response_from_pipeline(): void
    {
        $expectedResponse = $this->createMock(ResponseInterface::class);
        $serverRequest = new ServerRequest([], [], [], [], null, 'GET', '/');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($expectedResponse);

        $application = $this->createApplicationWithHandler($handler);

        $response = $application->handleRequest($serverRequest);

        $this->assertSame($expectedResponse, $response);
    }

    public function test_handleRequest_uses_injected_container(): void
    {
        $serverRequest = new ServerRequest([], [], [], [], null, 'GET', '/');
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $routeCollector = $this->createMock(RouteCollectorInterface::class);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            static fn (string $id): object => match ($id) {
                RouteCollectorInterface::class => $routeCollector,
                MiddlewarePipeline::class => $handler,
                default => throw new \RuntimeException("Unexpected container request: {$id}"),
            },
        );

        $application = Application::withContainer($container);
        $result = $application->handleRequest($serverRequest);

        $this->assertSame($response, $result);
    }

    /**
     * Создать Application с mock-контейнером, где MiddlewarePipeline возвращает заданный response.
     */
    private function createApplicationWithMocks(ResponseInterface $response): Application
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        return $this->createApplicationWithHandler($handler);
    }

    /**
     * Создать Application с mock-контейнером и заданным handler.
     */
    private function createApplicationWithHandler(RequestHandlerInterface $requestHandler): Application
    {
        $routeCollector = $this->createMock(RouteCollectorInterface::class);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            static fn (string $id): object => match ($id) {
                RouteCollectorInterface::class => $routeCollector,
                MiddlewarePipeline::class => $requestHandler,
                default => throw new \RuntimeException("Unexpected container request: {$id}"),
            },
        );

        return Application::withContainer($container);
    }
}
