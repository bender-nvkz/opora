<?php

declare(strict_types=1);

namespace Opora\Core\Config;

/**
 * Выбрасывается при невалидной конфигурации приложения.
 *
 * @api
 */
final class ConfigurationException extends \RuntimeException
{
    public function __construct(string $message, int $code = 0, null|\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
