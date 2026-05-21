<?php

declare(strict_types=1);

namespace Opora\Core\Tests\Unit\Event;

use Opora\Core\Event\EventBus;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

final class EventBusTest extends TestCase
{
    public function test_dispatch_delegates_to_psr14_dispatcher(): void
    {
        $event = new \stdClass();
        $event->foo = 'bar';

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with($event);

        $eventBus = new EventBus($dispatcher);
        $result = $eventBus->dispatch($event);

        self::assertSame($event, $result);
    }

    public function test_dispatch_returns_same_event_instance(): void
    {
        $event = new \stdClass();

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(
            static fn (object $e): object => $e,
        );

        $eventBus = new EventBus($dispatcher);

        self::assertSame($event, $eventBus->dispatch($event));
    }
}
