<?php

declare(strict_types=1);

namespace Opora\Core\Tests\Unit\Http\Middleware;

use Opora\Core\Config\SecurityHeadersConfig;
use Opora\Core\Http\Middleware\SecurityHeadersMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SecurityHeadersMiddlewareTest extends TestCase
{
    public function test_priority_is_50(): void
    {
        self::assertSame(50, SecurityHeadersMiddleware::priority());
    }

    public function test_process_adds_all_security_headers(): void
    {
        $securityHeadersConfig = SecurityHeadersConfig::defaults();
        $securityHeadersMiddleware = new SecurityHeadersMiddleware($securityHeadersConfig);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $expectedHeaders = $securityHeadersConfig->toHeaderArray();

        $response
            ->expects(self::exactly(5))
            ->method('withHeader')
            ->willReturnCallback(function (string $name, string $value) use ($expectedHeaders, $response): ResponseInterface {
                self::assertArrayHasKey($name, $expectedHeaders);
                self::assertSame($expectedHeaders[$name], $value);
                return $response;
            });

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler
            ->expects(self::once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        $securityHeadersMiddleware->process($request, $handler);
    }
}
