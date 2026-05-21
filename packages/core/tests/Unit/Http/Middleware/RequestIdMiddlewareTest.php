<?php

declare(strict_types=1);

namespace Opora\Core\Tests\Unit\Http\Middleware;

use Opora\Core\Http\Middleware\RequestIdMiddleware;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Specification для RequestIdMiddleware.
 *
 * @see Opora\Core\Http\Middleware\RequestIdMiddleware
 */
final class RequestIdMiddlewareTest extends TestCase
{
    private const string UUID_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    /**
     * Если X-Request-Id отсутствует — middleware генерирует UUID v4.
     */
    public function test_process_generates_uuid_when_header_missing(): void
    {
        $middleware = new RequestIdMiddleware();

        /** @var string|null $capturedAttribute */
        $capturedAttribute = null;
        $request = $this->createRequestMock(null, $capturedAttribute);

        /** @var string|null $capturedHeader */
        $capturedHeader = null;
        $response = $this->createResponseMock($capturedHeader);

        $handler = $this->createHandlerReturning($response);

        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
        self::assertNotNull($capturedAttribute);
        self::assertMatchesRegularExpression(self::UUID_REGEX, $capturedAttribute);
        self::assertNotNull($capturedHeader);
        self::assertMatchesRegularExpression(self::UUID_REGEX, $capturedHeader);
    }

    /**
     * Если X-Request-Id присутствует — middleware использует его.
     */
    public function test_process_uses_existing_header_when_present(): void
    {
        $middleware = new RequestIdMiddleware();

        $requestId = 'a1b2c3d4-e5f6-4789-abc1-2d3e4f5a6b7c';

        /** @var string|null $capturedAttribute */
        $capturedAttribute = null;
        $request = $this->createRequestMock($requestId, $capturedAttribute);

        /** @var string|null $capturedHeader */
        $capturedHeader = null;
        $response = $this->createResponseMock($capturedHeader);

        $handler = $this->createHandlerReturning($response);

        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
        self::assertSame($requestId, $capturedAttribute);
        self::assertSame($requestId, $capturedHeader);
    }

    /**
     * Request attribute 'request_id' устанавливается.
     */
    public function test_process_sets_request_attribute(): void
    {
        $middleware = new RequestIdMiddleware();

        /** @var string|null $capturedAttribute */
        $capturedAttribute = null;
        $request = $this->createRequestMock(null, $capturedAttribute);

        /** @var string|null $_capturedHeader */
        $_capturedHeader = null;
        $response = $this->createResponseMock($_capturedHeader);

        $handler = $this->createHandlerReturning($response);

        $middleware->process($request, $handler);

        self::assertNotNull($capturedAttribute);
        self::assertMatchesRegularExpression(self::UUID_REGEX, $capturedAttribute);
    }

    /**
     * Response получает X-Request-Id header.
     */
    public function test_process_sets_response_header(): void
    {
        $middleware = new RequestIdMiddleware();

        /** @var string|null $capturedAttribute */
        $capturedAttribute = null;
        $request = $this->createRequestMock(null, $capturedAttribute);

        /** @var string|null $capturedHeader */
        $capturedHeader = null;
        $response = $this->createResponseMock($capturedHeader);

        $handler = $this->createHandlerReturning($response);

        $middleware->process($request, $handler);

        self::assertNotNull($capturedHeader);
        self::assertMatchesRegularExpression(self::UUID_REGEX, $capturedHeader);
    }

    /**
     * Невалидный X-Request-Id санитизируется (удаляются недопустимые символы).
     */
    public function test_process_sanitizes_invalid_request_id(): void
    {
        $middleware = new RequestIdMiddleware();

        // client sends X-Request-Id с инъекцией заголовков
        $maliciousId = "valid-part\nX-Injected: malicious";

        /** @var string|null $capturedAttribute */
        $capturedAttribute = null;
        $request = $this->createRequestMock($maliciousId, $capturedAttribute);

        /** @var string|null $capturedHeader */
        $capturedHeader = null;
        $response = $this->createResponseMock($capturedHeader);

        $handler = $this->createHandlerReturning($response);

        $middleware->process($request, $handler);

        // санитизированное значение содержит только допустимые символы
        self::assertNotNull($capturedAttribute);
        self::assertNotNull($capturedHeader);
        self::assertStringNotContainsString("\n", $capturedAttribute);
        self::assertStringNotContainsString("\r", $capturedAttribute);
        self::assertStringNotContainsString("\n", $capturedHeader);
        self::assertStringNotContainsString("\r", $capturedHeader);
    }

    /**
     * Создать мок запроса с указанным X-Request-Id (null = нет заголовка).
     *
     * @param string|null $requestId         Значение X-Request-Id или null (нет заголовка)
     * @param string|null $capturedAttribute Сюда запишется значение атрибута request_id
     *
     * @return ServerRequestInterface&MockObject
     */
    private function createRequestMock(null|string $requestId, mixed &$capturedAttribute): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);

        if ($requestId === null) {
            $request->method('hasHeader')->willReturn(false);
        } else {
            $request->method('hasHeader')->willReturnCallback(
                static fn (string $name): bool => $name === 'X-Request-Id',
            );
            $request->method('getHeaderLine')->willReturn($requestId);
        }

        $request->method('withAttribute')->willReturnCallback(
            function (string $name, mixed $value) use (&$capturedAttribute, $request): ServerRequestInterface {
                $capturedAttribute = $value;

                return $request;
            },
        );

        return $request;
    }

    /**
     * Создать мок response, который захватывает значение X-Request-Id header.
     *
     * @param string|null $capturedHeader Сюда запишется значение переданного header
     *
     * @return ResponseInterface&MockObject
     */
    private function createResponseMock(mixed &$capturedHeader): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('withHeader')->willReturnCallback(
            function (string $name, mixed $value) use (&$capturedHeader, $response): ResponseInterface {
                $capturedHeader = $value;

                return $response;
            },
        );

        return $response;
    }

    /**
     * @return RequestHandlerInterface&MockObject
     */
    private function createHandlerReturning(ResponseInterface $response): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        return $handler;
    }
}
