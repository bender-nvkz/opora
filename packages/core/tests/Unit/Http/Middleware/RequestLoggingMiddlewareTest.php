<?php

declare(strict_types=1);

namespace Opora\Core\Tests\Unit\Http\Middleware;

use Opora\Core\Http\Middleware\RequestLoggingMiddleware;
use PHPUnit\Framework\TestCase;

final class RequestLoggingMiddlewareTest extends TestCase
{
    public function test_priority_is_30(): void
    {
        self::assertSame(30, RequestLoggingMiddleware::priority());
    }
}
