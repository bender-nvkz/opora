<?php

declare(strict_types=1);

namespace Opora\Core\Http\Middleware;

use Opora\Core\Config\AppConfig;
use Opora\Core\Http\Exception\HttpException;
use Opora\Core\Http\MiddlewareInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Приоритет 10. Первый в стеке.
 *
 * При любом необработанном исключении возвращает JSON-ответ.
 * В debug-режиме добавляет trace. В prod — только error + code.
 *
 * @api
 */
final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly AppConfig $config,
    ) {
    }

    public static function priority(): int
    {
        return 10;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        try {
            return $handler->handle($request);
        } catch (HttpException $e) {
            return $this->buildErrorResponse($e->getStatusCode(), $e->getMessage(), $e);
        } catch (\Throwable $e) {
            $this->logger->error('Unhandled exception: ' . $e->getMessage(), [
                'exception' => $e,
                'request_uri' => (string) $request->getUri(),
            ]);

            return $this->buildErrorResponse(500, $e->getMessage(), $e);
        }
    }

    /**
     * Построить JSON-ответ с ошибкой.
     */
    private function buildErrorResponse(int $statusCode, string $message, \Throwable $e): ResponseInterface
    {
        $payload = [
            'error' => $message,
            'code' => $statusCode,
        ];

        if ($this->config->debug) {
            $payload['trace'] = \explode("\n", $e->getTraceAsString());
        }

        $body = \json_encode($payload, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        \assert($body !== false);

        $response = $this->responseFactory->createResponse($statusCode);
        $response->getBody()->write($body);

        return $response
            ->withHeader('Content-Type', 'application/json');
    }
}
