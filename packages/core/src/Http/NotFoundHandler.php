<?php

declare(strict_types=1);

namespace Opora\Core\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 fallback handler: возвращает 404 JSON, когда ни один middleware
 * не сформировал ответ (например, нет роутера или маршрут не найден).
 *
 * @api
 */
final readonly class NotFoundHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = \json_encode(
            ['error' => 'Not Found', 'code' => 404],
            \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES,
        );
        \assert($body !== false);

        $response = $this->responseFactory->createResponse(404);
        $response->getBody()->write($body);

        return $response->withHeader('Content-Type', 'application/json');
    }
}
