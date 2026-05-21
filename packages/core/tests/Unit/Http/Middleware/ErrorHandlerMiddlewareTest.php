<?php

declare(strict_types=1);

namespace Opora\Core\Tests\Unit\Http\Middleware;

use Opora\Core\Config\AppConfig;
use Opora\Core\Http\Exception\HttpException;
use Opora\Core\Http\Middleware\ErrorHandlerMiddleware;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Specification для ErrorHandlerMiddleware.
 *
 * @see Opora\Core\Http\Middleware\ErrorHandlerMiddleware
 */
final class ErrorHandlerMiddlewareTest extends TestCase
{
    private AppConfig $prodConfig;

    private AppConfig $devConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prodConfig = AppConfig::fromEnv([
            'APP_ENV' => 'production',
            'APP_DEBUG' => '0',
            'DB_DSN' => 'pgsql://user:pass@localhost:5432/db',
        ]);

        $this->devConfig = AppConfig::fromEnv([
            'APP_ENV' => 'development',
            'APP_DEBUG' => '1',
            'DB_DSN' => 'pgsql://user:pass@localhost:5432/db',
        ]);
    }

    /**
     * RuntimeException → HTTP 500 + JSON error body.
     */
    public function test_process_returns_500_for_runtime_exception(): void
    {
        $errorHandlerMiddleware = new ErrorHandlerMiddleware(
            $this->createLoggerMock(),
            $this->createResponseFactoryMock(500),
            $this->prodConfig,
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createHandlerThatThrows(new \RuntimeException('Internal error'));

        $response = $errorHandlerMiddleware->process($request, $handler);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        $payload = \json_decode($body, true);

        self::assertIsArray($payload);
        self::assertSame('Internal error', $payload['error'] ?? '');
        self::assertSame(500, $payload['code'] ?? 0);
    }

    /**
     * HttpException с кодом 404 → HTTP 404.
     */
    public function test_process_returns_correct_status_for_http_exception(): void
    {
        $errorHandlerMiddleware = new ErrorHandlerMiddleware(
            $this->createLoggerMock(),
            $this->createResponseFactoryMock(404),
            $this->prodConfig,
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createHandlerThatThrows(new HttpException(404, 'Not Found'));

        $response = $errorHandlerMiddleware->process($request, $handler);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        $payload = \json_decode($body, true);

        self::assertIsArray($payload);
        self::assertSame('Not Found', $payload['error'] ?? '');
        self::assertSame(404, $payload['code'] ?? 0);
    }

    /**
     * HttpException с кодом 403 → HTTP 403.
     */
    public function test_process_returns_403_for_forbidden_exception(): void
    {
        $errorHandlerMiddleware = new ErrorHandlerMiddleware(
            $this->createLoggerMock(),
            $this->createResponseFactoryMock(403),
            $this->prodConfig,
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createHandlerThatThrows(new HttpException(403, 'Forbidden'));

        $response = $errorHandlerMiddleware->process($request, $handler);

        self::assertSame(403, $response->getStatusCode());
    }

    /**
     * В debug-режиме JSON содержит trace.
     */
    public function test_process_includes_trace_in_debug_mode(): void
    {
        $errorHandlerMiddleware = new ErrorHandlerMiddleware(
            $this->createLoggerMock(),
            $this->createResponseFactoryMock(500),
            $this->devConfig,
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createHandlerThatThrows(new \RuntimeException('Test error'));

        $response = $errorHandlerMiddleware->process($request, $handler);
        $body = (string) $response->getBody();
        $payload = \json_decode($body, true);

        self::assertIsArray($payload);
        self::assertArrayHasKey('trace', $payload);
        self::assertNotEmpty($payload['trace']);
    }

    /**
     * В production-режиме JSON HE содержит trace.
     */
    public function test_process_does_not_include_trace_in_production(): void
    {
        $errorHandlerMiddleware = new ErrorHandlerMiddleware(
            $this->createLoggerMock(),
            $this->createResponseFactoryMock(500),
            $this->prodConfig,
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createHandlerThatThrows(new \RuntimeException('Test error'));

        $response = $errorHandlerMiddleware->process($request, $handler);
        $body = (string) $response->getBody();
        $payload = \json_decode($body, true);

        self::assertIsArray($payload);
        self::assertArrayNotHasKey('trace', $payload);
    }

    /**
     * Успешный запрос — middleware пробрасывает оригинальный response.
     */
    public function test_process_passes_through_response_on_success(): void
    {
        $errorHandlerMiddleware = new ErrorHandlerMiddleware(
            $this->createLoggerMock(),
            $this->createResponseFactoryMock(200),
            $this->prodConfig,
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $originalResponse = $this->createMock(ResponseInterface::class);
        $originalResponse->method('getStatusCode')->willReturn(200);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($originalResponse);

        $response = $errorHandlerMiddleware->process($request, $handler);

        self::assertSame($originalResponse, $response);
    }

    /**
     * Middleware логирует ошибку.
     */
    public function test_process_logs_exception(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(self::stringContains('Unhandled exception'));

        $errorHandlerMiddleware = new ErrorHandlerMiddleware(
            $logger,
            $this->createResponseFactoryMock(500),
            $this->prodConfig,
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createHandlerThatThrows(new \RuntimeException('Log me'));

        $errorHandlerMiddleware->process($request, $handler);
    }

    /**
     * @return RequestHandlerInterface&MockObject
     */
    private function createHandlerThatThrows(\Throwable $throwable): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException($throwable);

        return $handler;
    }

    /**
     * @return LoggerInterface&MockObject
     */
    private function createLoggerMock(): LoggerInterface
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(static function (): void {
        });

        return $logger;
    }

    /**
     * @return ResponseFactoryInterface&MockObject
     */
    private function createResponseFactoryMock(int $expectedStatus): ResponseFactoryInterface
    {
        $writtenContent = '';
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('write')->willReturnCallback(
            static function (string $data) use (&$writtenContent): int {
                $writtenContent .= $data;

                return \strlen($data);
            },
        );
        $stream->method('__toString')->willReturnCallback(
            static function () use (&$writtenContent): string {
                return $writtenContent;
            },
        );

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($expectedStatus);
        $response->method('getHeaderLine')->willReturn('application/json');
        $response->method('getBody')->willReturn($stream);
        $response->method('withHeader')->willReturnCallback(
            static fn (string $name, mixed $value): ResponseInterface => $response,
        );

        $factory = $this->createMock(ResponseFactoryInterface::class);
        $factory->method('createResponse')->willReturn($response);

        return $factory;
    }
}
