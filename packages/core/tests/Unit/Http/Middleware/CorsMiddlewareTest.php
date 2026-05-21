<?php

declare(strict_types=1);

namespace Opora\Core\Tests\Unit\Http\Middleware;

use Opora\Core\Config\CorsConfig;
use Opora\Core\Http\Middleware\CorsMiddleware;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Specification для CorsMiddleware.
 *
 * @see Opora\Core\Http\Middleware\CorsMiddleware
 */
final class CorsMiddlewareTest extends TestCase
{
    public function test_priority_is_40(): void
    {
        self::assertSame(40, CorsMiddleware::priority());
    }

    public function test_preflight_returns_204_without_calling_handler(): void
    {
        $corsConfig = new CorsConfig(
            allowedOrigins: ['https://example.com'],
            allowedMethods: ['GET', 'POST'],
            allowedHeaders: ['Content-Type'],
            maxAge: 7200,
            allowCredentials: false,
        );

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('withHeader')->willReturnCallback(
            fn (string $name, string $value): ResponseInterface => $response,
        );
        $response->method('getStatusCode')->willReturn(204);

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturn($response);

        $request = $this->createRequestMock('OPTIONS', 'https://example.com');

        $corsMiddleware = new CorsMiddleware($corsConfig, $responseFactory);
        $result = $corsMiddleware->process($request, $handler);

        self::assertSame(204, $result->getStatusCode());
    }

    public function test_sets_acao_header_for_allowed_origin(): void
    {
        $corsConfig = new CorsConfig(
            allowedOrigins: ['https://example.com'],
            allowedMethods: ['GET'],
            allowedHeaders: [],
            maxAge: 0,
            allowCredentials: false,
        );

        /** @var list<array{string, string}> $setHeaders */
        $setHeaders = [];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('withHeader')->willReturnCallback(
            function (string $name, string $value) use ($response, &$setHeaders): ResponseInterface {
                $setHeaders[] = [$name, $value];

                return $response;
            },
        );

        $requestHandler = $this->createHandlerReturning($response);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);

        $request = $this->createRequestMock('GET', 'https://example.com');

        $corsMiddleware = new CorsMiddleware($corsConfig, $responseFactory);
        $corsMiddleware->process($request, $requestHandler);

        self::assertContains(['Access-Control-Allow-Origin', 'https://example.com'], $setHeaders);
    }

    public function test_does_not_set_acao_header_for_disallowed_origin(): void
    {
        $corsConfig = new CorsConfig(
            allowedOrigins: ['https://trusted.com'],
            allowedMethods: ['GET'],
            allowedHeaders: [],
            maxAge: 0,
            allowCredentials: false,
        );

        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::never())->method('withHeader')->with(
            self::stringStartsWith('Access-Control-'),
            self::anything(),
        );

        $requestHandler = $this->createHandlerReturning($response);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);

        $request = $this->createRequestMock('GET', 'https://evil.com');

        $corsMiddleware = new CorsMiddleware($corsConfig, $responseFactory);
        $corsMiddleware->process($request, $requestHandler);
    }

    public function test_sets_allow_credentials_header(): void
    {
        $corsConfig = new CorsConfig(
            allowedOrigins: ['https://example.com'],
            allowedMethods: ['GET'],
            allowedHeaders: [],
            maxAge: 0,
            allowCredentials: true,
        );

        /** @var list<array{string, string}> $setHeaders */
        $setHeaders = [];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('withHeader')->willReturnCallback(
            function (string $name, string $value) use ($response, &$setHeaders): ResponseInterface {
                $setHeaders[] = [$name, $value];

                return $response;
            },
        );

        $requestHandler = $this->createHandlerReturning($response);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);

        $request = $this->createRequestMock('GET', 'https://example.com');

        $corsMiddleware = new CorsMiddleware($corsConfig, $responseFactory);
        $corsMiddleware->process($request, $requestHandler);

        self::assertContains(
            ['Access-Control-Allow-Credentials', 'true'],
            $setHeaders,
        );
    }

    public function test_allow_credentials_with_wildcard_origin_throws(): void
    {
        $corsConfig = new CorsConfig(
            allowedOrigins: ['*'],
            allowedMethods: ['GET'],
            allowedHeaders: [],
            maxAge: 0,
            allowCredentials: true,
        );

        $handler = $this->createMock(RequestHandlerInterface::class);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);

        $request = $this->createRequestMock('GET', 'https://example.com');

        $corsMiddleware = new CorsMiddleware($corsConfig, $responseFactory);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot use wildcard origin with credentials');

        $corsMiddleware->process($request, $handler);
    }

    public function test_sets_max_age_header(): void
    {
        $corsConfig = new CorsConfig(
            allowedOrigins: ['https://example.com'],
            allowedMethods: ['GET'],
            allowedHeaders: [],
            maxAge: 86400,
            allowCredentials: false,
        );

        /** @var list<array{string, string}> $setHeaders */
        $setHeaders = [];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('withHeader')->willReturnCallback(
            function (string $name, string $value) use ($response, &$setHeaders): ResponseInterface {
                $setHeaders[] = [$name, $value];

                return $response;
            },
        );

        $requestHandler = $this->createHandlerReturning($response);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);

        $request = $this->createRequestMock('GET', 'https://example.com');

        $corsMiddleware = new CorsMiddleware($corsConfig, $responseFactory);
        $corsMiddleware->process($request, $requestHandler);

        self::assertContains(
            ['Access-Control-Max-Age', '86400'],
            $setHeaders,
        );
    }

    public function test_sets_allowed_methods_on_preflight(): void
    {
        $corsConfig = new CorsConfig(
            allowedOrigins: ['https://example.com'],
            allowedMethods: ['GET', 'POST', 'DELETE'],
            allowedHeaders: [],
            maxAge: 3600,
            allowCredentials: false,
        );

        /** @var list<array{string, string}> $setHeaders */
        $setHeaders = [];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('withHeader')->willReturnCallback(
            function (string $name, string $value) use ($response, &$setHeaders): ResponseInterface {
                $setHeaders[] = [$name, $value];

                return $response;
            },
        );
        $response->method('getStatusCode')->willReturn(204);

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturn($response);

        $request = $this->createRequestMock('OPTIONS', 'https://example.com');

        $corsMiddleware = new CorsMiddleware($corsConfig, $responseFactory);
        $corsMiddleware->process($request, $handler = $this->createMock(RequestHandlerInterface::class));

        self::assertContains(
            ['Access-Control-Allow-Methods', 'GET, POST, DELETE'],
            $setHeaders,
        );
    }

    public function test_sets_allowed_headers_on_preflight(): void
    {
        $corsConfig = new CorsConfig(
            allowedOrigins: ['https://example.com'],
            allowedMethods: ['GET'],
            allowedHeaders: ['Content-Type', 'Authorization', 'X-Request-Id'],
            maxAge: 3600,
            allowCredentials: false,
        );

        /** @var list<array{string, string}> $setHeaders */
        $setHeaders = [];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('withHeader')->willReturnCallback(
            function (string $name, string $value) use ($response, &$setHeaders): ResponseInterface {
                $setHeaders[] = [$name, $value];

                return $response;
            },
        );
        $response->method('getStatusCode')->willReturn(204);

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->method('createResponse')->willReturn($response);

        $request = $this->createRequestMock('OPTIONS', 'https://example.com');

        $corsMiddleware = new CorsMiddleware($corsConfig, $responseFactory);
        $corsMiddleware->process($request, $handler = $this->createMock(RequestHandlerInterface::class));

        self::assertContains(
            ['Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Request-Id'],
            $setHeaders,
        );
    }

    public function test_passes_request_without_origin_header(): void
    {
        $corsConfig = new CorsConfig(
            allowedOrigins: ['*'],
            allowedMethods: ['GET'],
            allowedHeaders: [],
            maxAge: 0,
            allowCredentials: false,
        );

        $innerResponse = $this->createMock(ResponseInterface::class);
        $innerResponse->expects(self::never())->method('withHeader')->with(
            self::stringStartsWith('Access-Control-'),
            self::anything(),
        );

        $requestHandler = $this->createHandlerReturning($innerResponse);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getHeaderLine')->willReturn('');
        $request->method('withAttribute')->willReturnSelf();

        $corsMiddleware = new CorsMiddleware($corsConfig, $responseFactory);
        $response = $corsMiddleware->process($request, $requestHandler);

        self::assertSame($innerResponse, $response);
    }

    /**
     * @return ServerRequestInterface&MockObject
     */
    private function createRequestMock(string $method, string $origin): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getHeaderLine')->willReturn($origin);
        $request->method('withAttribute')->willReturnSelf();

        return $request;
    }

    private function createHandlerReturning(ResponseInterface $response): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        return $handler;
    }
}
