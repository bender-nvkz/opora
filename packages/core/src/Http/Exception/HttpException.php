<?php

declare(strict_types=1);

namespace Opora\Core\Http\Exception;

/**
 * HTTP-исключение с кодом статуса.
 *
 * Используется в middleware для возврата структурированных HTTP-ошибок.
 *
 * @api
 */
final class HttpException extends \RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        null|\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
