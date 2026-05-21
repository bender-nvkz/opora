<?php

declare(strict_types=1);

namespace Opora\Core\Tests\Unit\Http\Middleware;

use Opora\Core\Http\Middleware\RouterMiddleware;
use PHPUnit\Framework\TestCase;

final class RouterMiddlewareTest extends TestCase
{
    public function test_priority_is_200(): void
    {
        self::assertSame(200, RouterMiddleware::priority());
    }
}
