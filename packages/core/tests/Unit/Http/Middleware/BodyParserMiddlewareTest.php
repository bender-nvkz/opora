<?php

declare(strict_types=1);

namespace Opora\Core\Tests\Unit\Http\Middleware;

use Opora\Core\Http\Exception\HttpException;
use Opora\Core\Http\Middleware\BodyParserMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class BodyParserMiddlewareTest extends TestCase
{
    public function test_priority_is_60(): void
    {
        self::assertSame(60, BodyParserMiddleware::priority());
    }

    public function test_process_parses_valid_json(): void
    {
        $bodyParserMiddleware = new BodyParserMiddleware();

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn('{"key": "value", "num": 42}');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::any())->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $request->method('getBody')->willReturn($stream);
        $request
            ->expects(self::once())
            ->method('withParsedBody')
            ->with(['key' => 'value', 'num' => 42])
            ->willReturnSelf();

        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $bodyParserMiddleware->process($request, $handler);
    }

    public function test_process_skips_empty_body(): void
    {
        $bodyParserMiddleware = new BodyParserMiddleware();

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn('');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::any())->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $request->method('getBody')->willReturn($stream);
        $request->expects(self::never())->method('withParsedBody');

        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $bodyParserMiddleware->process($request, $handler);
    }

    public function test_process_skips_unsupported_content_type(): void
    {
        $bodyParserMiddleware = new BodyParserMiddleware();

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::any())->method('getHeaderLine')->with('Content-Type')->willReturn('text/plain');
        $request->expects(self::never())->method('getBody');
        $request->expects(self::never())->method('withParsedBody');

        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $bodyParserMiddleware->process($request, $handler);
    }

    public function test_process_throws_400_for_invalid_json(): void
    {
        $bodyParserMiddleware = new BodyParserMiddleware();

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn('{invalid json}');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::any())->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $request->method('getBody')->willReturn($stream);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('Invalid JSON');

        $bodyParserMiddleware->process($request, $handler);
    }

    public function test_process_handles_json_with_charset(): void
    {
        $bodyParserMiddleware = new BodyParserMiddleware();

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn('{"key": "value"}');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::any())->method('getHeaderLine')->with('Content-Type')->willReturn('application/json; charset=utf-8');
        $request->method('getBody')->willReturn($stream);
        $request
            ->expects(self::once())
            ->method('withParsedBody')
            ->with(['key' => 'value'])
            ->willReturnSelf();

        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $bodyParserMiddleware->process($request, $handler);
    }
}
