<?php

declare(strict_types=1);

namespace Opora\Core\Event;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Тонкая обёртка над PSR-14 EventDispatcherInterface.
 * В рамках одного процесса — синхронный dispatch.
 * Для async — модуль opora/queue подписывается на события и ставит job в очередь.
 *
 * @api
 */
interface EventBusInterface extends EventDispatcherInterface
{
    /**
     * dispatch() возвращает переданное событие (для цепочки).
     */
    public function dispatch(object $event): object;
}
