<?php

declare(strict_types=1);

namespace Opora\Core\Event;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Реализация EventBusInterface — тонкая обёртка над PSR-14 EventDispatcherInterface.
 *
 * @api
 */
final readonly class EventBus implements EventBusInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Делегирует dispatch PSR-14 диспатчеру и возвращает событие для chaining.
     */
    public function dispatch(object $event): object
    {
        $this->eventDispatcher->dispatch($event);

        return $event;
    }
}
