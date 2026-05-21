<?php

declare(strict_types=1);

namespace Opora\Core\Http;

/**
 * Метка для регистрации в middleware-стеке с явным приоритетом.
 *
 * Расширяет PSR-15 MiddlewareInterface, добавляя приоритет для сортировки.
 * Чем ниже число — тем раньше выполняется (ближе к HTTP-соединению).
 *
 * @api
 */
interface MiddlewareInterface extends \Psr\Http\Server\MiddlewareInterface
{
    /**
     * Приоритет выполнения middleware.
     *
     * @return int Чем меньше, тем раньше выполняется (ErrorHandler=10, Router=200)
     */
    public static function priority(): int;
}
