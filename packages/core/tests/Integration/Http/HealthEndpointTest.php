<?php

declare(strict_types=1);

namespace Opora\Core\Tests\Integration\Http;

use Cycle\Database\Config\DatabaseConfig;
use Cycle\Database\Config\Postgres\DsnConnectionConfig;
use Cycle\Database\Config\PostgresDriverConfig;
use Cycle\Database\DatabaseManager;
use Cycle\Database\DatabaseProviderInterface;
use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\ServerRequest;
use HttpSoft\Message\ServerRequestFactory;
use HttpSoft\Message\StreamFactory;
use HttpSoft\Message\UriFactory;
use Opora\Core\Application;
use Opora\Core\Config\AppConfig;
use Opora\Core\Config\CorsConfig;
use Opora\Core\Config\SecurityHeadersConfig;
use Opora\Core\Health\HealthCheckInterface;
use Opora\Core\Health\HealthCheckResult;
use Opora\Core\Http\HealthController;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Yiisoft\Definitions\Reference;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;

/**
 * Интеграционный тест HTTP-endpoint GET /api/health.
 *
 * Проверяет полный round-trip:
 *   Application::handleRequest() → MiddlewarePipeline → RouterMiddleware
 *   → HealthController → DatabaseHealthCheck → Response
 *
 * @requires extension pdo_pgsql
 *
 * @api
 */
final class HealthEndpointTest extends TestCase
{
    private DatabaseManager $databaseManager;

    protected function setUp(): void
    {
        $this->databaseManager = new DatabaseManager(new DatabaseConfig([
            'default' => 'default',
            'databases' => [
                'default' => ['connection' => 'postgres'],
            ],
            'connections' => [
                'postgres' => new PostgresDriverConfig(
                    connection: new DsnConnectionConfig(
                        dsn: 'pgsql:host=postgres;port=5432;dbname=opora',
                        user: 'opora',
                        password: 'opora_dev_password',
                    ),
                ),
            ],
        ]));
    }

    protected function tearDown(): void
    {
        unset($this->databaseManager);
    }

    /**
     * GET /api/health → HTTP 200 + status:ok при доступной БД.
     */
    public function test_health_endpoint_returns_200_when_db_healthy(): void
    {
        $application = $this->createApplication();
        $response = $application->handleRequest($this->createRequest('GET', '/api/health'));

        self::assertSame(200, $response->getStatusCode());

        $body = \json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('ok', $body['status']);
        self::assertArrayHasKey('checks', $body);
    }

    /**
     * Ответ содержит X-Request-Id (UUID4).
     */
    public function test_health_endpoint_includes_x_request_id(): void
    {
        $application = $this->createApplication();
        $response = $application->handleRequest($this->createRequest('GET', '/api/health'));

        $requestId = $response->getHeaderLine('X-Request-Id');
        self::assertNotEmpty($requestId, 'X-Request-Id header must be present');

        // UUID4 regex
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $requestId,
            'X-Request-Id must be a valid UUID v4',
        );
    }

    /**
     * Ответ содержит checks.db со статусом ok.
     */
    public function test_health_endpoint_includes_db_check(): void
    {
        $application = $this->createApplication();
        $response = $application->handleRequest($this->createRequest('GET', '/api/health'));

        $body = \json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        // DatabaseHealthCheck::name() returns 'database'
        self::assertArrayHasKey('database', $body['checks'], 'Response must include database check');
        self::assertSame('ok', $body['checks']['database']['status']);
        self::assertArrayHasKey('latency_ms', $body['checks']['database']);
        self::assertIsFloat($body['checks']['database']['latency_ms']);
    }

    /**
     * GET /api/health → HTTP 503 + status:error при недоступной проверке.
     *
     * DatabaseHealthCheck подменяется на mock в DI-контейнере через
     * переопределение HealthController (тег opora.health.check не участвует).
     */
    public function test_health_endpoint_returns_503_when_db_unhealthy(): void
    {
        $unhealthyCheck = $this->createMock(HealthCheckInterface::class);
        $unhealthyCheck->method('name')->willReturn('db');
        $unhealthyCheck->method('check')->willReturn(
            new HealthCheckResult(
                status: 'error',
                message: 'Connection refused',
            ),
        );

        $application = $this->createApplicationWithChecks([$unhealthyCheck]);
        $response = $application->handleRequest($this->createRequest('GET', '/api/health'));

        self::assertSame(503, $response->getStatusCode());

        $body = \json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('error', $body['status']);
        self::assertSame('error', $body['checks']['db']['status']);
        self::assertSame('Connection refused', $body['checks']['db']['message']);
    }

    /**
     * Создать приложение с полным middleware-стеком и реальной БД.
     */
    private function createApplication(): Application
    {
        return Application::withContainer($this->buildContainer());
    }

    /**
     * Создать приложение с переопределённым списком health checks.
     *
     * @param iterable<HealthCheckInterface> $checks
     */
    private function createApplicationWithChecks(iterable $checks): Application
    {
        $container = $this->buildContainer([
            HealthController::class => [
                'class' => HealthController::class,
                '__construct()' => [
                    'checks' => $checks,
                    'responseFactory' => Reference::to(ResponseFactoryInterface::class),
                ],
            ],
        ]);

        return Application::withContainer($container);
    }

    /**
     * Построить DI-контейнер с полным middleware-стеком.
     *
     * Загружает конфиги middleware и web-сервисов, добавляет
     * базовые сервисы (БД, PSR-17, конфиги).
     *
     * @param array<string, mixed> $overrides Дополнительные/переопределённые определения
     */
    private function buildContainer(array $overrides = []): Container
    {
        $projectRoot = \dirname(__DIR__, 5);

        $middlewareDefinitions = require $projectRoot . '/config/web/middleware.php';
        $webDefinitions = require $projectRoot . '/config/web/container.php';

        $definitions = \array_merge(
            $middlewareDefinitions,
            $webDefinitions,
            [
                // Database + Logger
                DatabaseProviderInterface::class => $this->databaseManager,
                LoggerInterface::class => new NullLogger(),

                // PSR-17 фабрики
                ResponseFactoryInterface::class => ResponseFactory::class,
                ServerRequestFactoryInterface::class => ServerRequestFactory::class,
                UriFactoryInterface::class => UriFactory::class,
                StreamFactoryInterface::class => StreamFactory::class,

                // Конфиги приложения
                // Создаём AppConfig напрямую, без fromEnv() — тест не полагается на $_SERVER
                AppConfig::class => new AppConfig(
                    appEnv: 'test',
                    debug: true,
                    databaseUrl: 'pgsql:host=postgres;port=5432;dbname=opora',
                    corsAllowedOrigins: ['*'],
                ),
                CorsConfig::class => [
                    'class' => CorsConfig::class,
                    '__construct()' => [
                        'allowedOrigins' => ['*'],
                        'allowedMethods' => CorsConfig::DEFAULT_METHODS,
                        'allowedHeaders' => CorsConfig::DEFAULT_HEADERS,
                        'maxAge' => 86400,
                        'allowCredentials' => false,
                    ],
                ],
                SecurityHeadersConfig::class => SecurityHeadersConfig::defaults(
                    isDevelopment: \in_array($_ENV['APP_ENV'] ?? 'dev', ['dev', 'test'], true),
                ),
            ],
            $overrides,
        );

        return new Container(
            ContainerConfig::create()
                ->withDefinitions($definitions)
                ->withValidate(false),
        );
    }

    /**
     * Создать PSR-7 ServerRequest.
     */
    private function createRequest(string $method, string $uri): ServerRequestInterface
    {
        return new ServerRequest([], [], [], [], null, $method, $uri);
    }
}
