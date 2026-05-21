<?php

declare(strict_types=1);

namespace Opora\Core\Tests\Unit\Http;

use Opora\Core\Health\HealthCheckInterface;
use Opora\Core\Health\HealthCheckResult;
use Opora\Core\Http\HealthController;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

final class HealthControllerTest extends TestCase
{
    public function test_invoke_returns_200_when_all_checks_ok(): void
    {
        $healthCheck = $this->createHealthCheck('database', new HealthCheckResult('ok', latencyMs: 1.23));
        $cacheCheck = $this->createHealthCheck('cache', new HealthCheckResult('ok', latencyMs: 0.45));

        $healthController = $this->createController(
            [$healthCheck, $cacheCheck],
            200,
            '{"status":"ok","checks":{"database":{"status":"ok","latency_ms":1.23},"cache":{"status":"ok","latency_ms":0.45}}}',
        );

        $response = $healthController->__invoke();

        self::assertSame(200, $response->getStatusCode());
    }

    public function test_invoke_returns_503_when_any_check_fails(): void
    {
        $healthCheck = $this->createHealthCheck('database', new HealthCheckResult('ok', latencyMs: 1.23));
        $storageCheck = $this->createHealthCheck('storage', new HealthCheckResult(
            status: 'error',
            message: 'Disk full',
        ));

        $healthController = $this->createController(
            [$healthCheck, $storageCheck],
            503,
            '{"status":"error","checks":{"database":{"status":"ok","latency_ms":1.23},"storage":{"status":"error","message":"Disk full"}}}',
        );

        $response = $healthController->__invoke();

        self::assertSame(503, $response->getStatusCode());
    }

    public function test_invoke_returns_json_content_type(): void
    {
        $healthCheck = $this->createHealthCheck('test', new HealthCheckResult('ok'));

        $healthController = $this->createController(
            [$healthCheck],
            200,
            '{"status":"ok","checks":{"test":{"status":"ok"}}}',
        );

        $response = $healthController->__invoke();

        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function test_invoke_response_body_structure(): void
    {
        $healthCheck = $this->createHealthCheck('database', new HealthCheckResult('ok', latencyMs: 5.0));

        $healthController = $this->createController(
            [$healthCheck],
            200,
            '{"status":"ok","checks":{"database":{"status":"ok","latency_ms":5}}}',
        );

        $response = $healthController->__invoke();

        $body = (string) $response->getBody();
        $data = \json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('ok', $data['status']);
        self::assertArrayHasKey('checks', $data);
        self::assertArrayHasKey('database', $data['checks']);
        self::assertSame('ok', $data['checks']['database']['status']);
    }

    public function test_invoke_returns_empty_checks_when_no_services(): void
    {
        $healthController = $this->createController(
            [],
            200,
            '{"status":"ok","checks":{}}',
        );

        $response = $healthController->__invoke();

        self::assertSame(200, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = \json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('ok', $data['status']);
        self::assertEmpty($data['checks']);
    }

    private function createHealthCheck(string $name, HealthCheckResult $healthCheckResult): HealthCheckInterface
    {
        $check = $this->createMock(HealthCheckInterface::class);
        $check->method('name')->willReturn($name);
        $check->method('check')->willReturn($healthCheckResult);

        return $check;
    }

    /**
     * @param HealthCheckInterface[] $checks
     */
    private function createController(
        array $checks,
        int $expectedStatus,
        string $expectedBody,
    ): HealthController {
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects(self::once())
            ->method('write')
            ->with($expectedBody)
            ->willReturn(\strlen($expectedBody));
        $stream->method('__toString')
            ->willReturn($expectedBody);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($expectedStatus);
        $response->expects(self::any())
            ->method('getHeaderLine')
            ->with('Content-Type')
            ->willReturn('application/json');
        $response->method('getBody')->willReturn($stream);
        $response->expects(self::once())
            ->method('withHeader')
            ->with('Content-Type', 'application/json')
            ->willReturnSelf();

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects(self::once())
            ->method('createResponse')
            ->with($expectedStatus)
            ->willReturn($response);

        return new HealthController($checks, $responseFactory);
    }
}
