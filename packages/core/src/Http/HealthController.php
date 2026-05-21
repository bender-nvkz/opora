<?php

declare(strict_types=1);

namespace Opora\Core\Http;

use Opora\Core\Health\HealthCheckInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Invokable-контроллер для GET /api/health.
 *
 * Собирает все сервисы, зарегистрированные с тегом 'opora.health.check',
 * выполняет каждую проверку и возвращает JSON-ответ.
 *
 * HTTP 200 — все проверки успешны.
 * HTTP 503 — хотя бы одна проверка вернула 'error'.
 *
 * @api
 */
final readonly class HealthController
{
    /**
     * @param iterable<HealthCheckInterface> $checks Все health check-сервисы
     */
    public function __construct(
        private iterable $checks,
        private ResponseFactoryInterface $responseFactory,
    ) {
    }

    /**
     * Handle GET /api/health.
     */
    public function __invoke(): ResponseInterface
    {
        $results = [];
        $overallStatus = 'ok';
        foreach ($this->checks as $check) {
            $result = $check->check();
            $name = $check->name();

            $checkData = [
                'status' => $result->status,
            ];

            if ($result->message !== null) {
                $checkData['message'] = $result->message;
            }

            if ($result->latencyMs !== null) {
                $checkData['latency_ms'] = \round($result->latencyMs, 2);
            }

            $results[$name] = $checkData;

            if ($result->status === 'error') {
                $overallStatus = 'error';
            }
        }
        $responseBody = [
            'status' => $overallStatus,
            'checks' => $results === [] ? new \stdClass() : $results,
        ];
        $statusCode = $overallStatus === 'ok' ? 200 : 503;
        $response = $this->responseFactory->createResponse($statusCode);
        $response->getBody()->write(
            \json_encode($responseBody, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        );
        return $response->withHeader('Content-Type', 'application/json');
    }
}
