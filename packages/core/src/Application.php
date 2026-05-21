<?php

declare(strict_types=1);

namespace Opora\Core;

use Cycle\Database\Config\DatabaseConfig;
use Cycle\Database\Config\Postgres\DsnConnectionConfig;
use Cycle\Database\Config\PostgresDriverConfig;
use Cycle\Database\DatabaseManager;
use Cycle\Database\DatabaseProviderInterface;
use Dotenv\Dotenv;
use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\ServerRequest;
use HttpSoft\Message\ServerRequestFactory;
use HttpSoft\Message\Stream;
use HttpSoft\Message\StreamFactory;
use HttpSoft\Message\UriFactory;
use Opora\Core\Command\InstallCommand;
use Opora\Core\Config\AppConfig;
use Opora\Core\Config\CorsConfig;
use Opora\Core\Config\SecurityHeadersConfig;
use Opora\Core\Http\MiddlewarePipeline;
use Opora\Core\Module\CoreModuleInstaller;
use Opora\Core\Module\ModuleMigrationRunner;
use Opora\Core\Module\ModuleRegistry;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Yiisoft\Definitions\Reference;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Router\RouteCollectorInterface;

/**
 * Точка входа: читает ENV, строит DI-контейнер, запускает HTTP или Console runner.
 *
 * HTTP-путь (production):
 *   $app = Application::create();
 *   $app->run();
 *
 * HTTP-путь (тесты):
 *   $app = Application::withContainer($mockContainer);
 *   $result = $app->handleRequest($request);
 *
 * Console-путь:
 *   $app = new Application(); // только для обратной совместимости
 *   exit($app->start());
 *
 * @api
 */
final class Application
{
    private bool $bootstrapped = false;

    /**
     * Приватный конструктор — используй статические фабрики.
     *
     * @param ContainerInterface|null $container null только в production (строится лениво).
     *                                           ContainerInterface — в тестах через mock.
     */
    private function __construct(private null|ContainerInterface $container = null)
    {
    }

    /**
     * Создать Application для production.
     *
     * Контейнер строится лениво — при первом вызове handleRequest().
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Создать Application с готовым контейнером (для тестов).
     */
    public static function withContainer(ContainerInterface $container): self
    {
        return new self($container);
    }

    /**
     * HTTP entrypoint (production).
     *
     * Собирает ServerRequest из суперглобалов, обрабатывает через middleware-стек,
     * отправляет ответ в SAPI.
     *
     * @param ServerRequestInterface|null $request если передан — используется напрямую (для тестов).
     */
    public function run(null|ServerRequestInterface $request = null): void
    {
        $request ??= $this->createServerRequestFromGlobals();
        $response = $this->handleRequest($request);
        $this->emitResponse($response);
    }

    /**
     * HTTP entrypoint (core).
     *
     * Пропускает ServerRequest через MiddlewarePipeline и возвращает ResponseInterface.
     * Не отправляет ответ в SAPI — вызывающий сам решает что делать с Response.
     *
     * @param ServerRequestInterface $serverRequest Входящий HTTP-запрос.
     *
     * @return ResponseInterface Ответ от middleware-стека.
     */
    public function handleRequest(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $container = $this->container ?? $this->buildHttpContainer();
        $this->container = $container;

        $this->registerRoutes($container);

        /** @var MiddlewarePipeline $pipeline */
        $pipeline = $container->get(MiddlewarePipeline::class);

        return $pipeline->handle($serverRequest);
    }

    /**
     * Console entrypoint.
     *
     * Загружает .env, конфигурирует DatabaseManager, строит DI-контейнер
     * и запускает Symfony Console Application с зарегистрированными командами.
     *
     * @return int Exit code (0 = success).
     */
    public function start(): int
    {
        $this->bootstrap();

        $databaseManager = $this->createDatabaseManager();
        $nullLogger = new NullLogger();

        $container = $this->createContainer($databaseManager, $nullLogger);

        $application = new SymfonyApplication('Opora', '0.1.0');
        $application->add($container->get(InstallCommand::class));

        return $application->run(new ArgvInput(), new ConsoleOutput());
    }

    /**
     * Разобрать HTTP-заголовки из $_SERVER.
     *
     * Ключи HTTP_* преобразуются в имена заголовков.
     * CONTENT_TYPE и CONTENT_LENGTH обрабатываются отдельно.
     *
     * @param array<array-key, mixed> $server $_SERVER или его аналог.
     *
     * @return array<non-empty-string, list<string>>
     */
    private function parseServerHeaders(array $server): array
    {
        /** @var array<non-empty-string, list<string>> $headers */
        $headers = [];

        foreach ($server as $key => $value) {
            if (!\is_string($key)) {
                continue;
            }
            if (!\is_string($value)) {
                continue;
            }
            if (\str_starts_with($key, 'HTTP_')) {
                /** @var non-empty-string $name */
                $name = \str_replace('_', '-', \substr($key, 5));

                if ($name === '') {
                    continue;
                }

                $name = \ucwords(\strtolower($name), '-');
                $headers[$name] = [$value];
            }
        }

        if (isset($server['CONTENT_TYPE']) && \is_string($server['CONTENT_TYPE'])) {
            $headers['Content-Type'] = [$server['CONTENT_TYPE']];
        }

        if (isset($server['CONTENT_LENGTH']) && \is_string($server['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = [$server['CONTENT_LENGTH']];
        }

        return $headers;
    }

    /**
     * Однократная загрузка .env.
     */
    private function bootstrap(): void
    {
        if ($this->bootstrapped) {
            return;
        }

        $this->bootstrapped = true;
        $this->loadEnvironment();
    }

    /**
     * Построить HTTP DI-контейнер.
     *
     * Загружает конфиги:
     *   - config/common/container.php  (общие сервисы)
     *   - config/web/container.php     (web-specific сервисы)
     *   - config/web/middleware.php    (middleware с тегами)
     *
     * Добавляет базовые сервисы (PSR-17 фабрики, конфиги).
     */
    private function buildHttpContainer(): ContainerInterface
    {
        $this->bootstrap();

        $databaseManager = $this->createDatabaseManager();
        $nullLogger = new NullLogger();

        $commonDefinitions = require \dirname(__DIR__, 3) . '/config/common/container.php';
        $webDefinitions = require \dirname(__DIR__, 3) . '/config/web/container.php';
        $middlewareDefinitions = require \dirname(__DIR__, 3) . '/config/web/middleware.php';

        $definitions = \array_merge(
            $commonDefinitions,
            $webDefinitions,
            $middlewareDefinitions,
            [
                // Database + Logger
                DatabaseProviderInterface::class => $databaseManager,
                LoggerInterface::class => $nullLogger,

                // PSR-17 фабрики
                ResponseFactoryInterface::class => ResponseFactory::class,
                ServerRequestFactoryInterface::class => ServerRequestFactory::class,
                UriFactoryInterface::class => UriFactory::class,
                StreamFactoryInterface::class => StreamFactory::class,

                // Конфиги приложения
                AppConfig::class => AppConfig::fromEnv(),
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
        );

        $containerConfig = ContainerConfig::create()
            ->withDefinitions($definitions);

        return new Container($containerConfig);
    }

    /**
     * Зарегистрировать маршруты из config/web/routes.php.
     *
     * Каждый элемент массива — callable (RouteCollectorInterface): void.
     */
    private function registerRoutes(ContainerInterface $container): void
    {
        /** @var array<callable> $routeRegistrars */
        $routeRegistrars = require \dirname(__DIR__, 3) . '/config/web/routes.php';

        $routeCollector = $container->get(RouteCollectorInterface::class);

        foreach ($routeRegistrars as $routeRegistrar) {
            $routeRegistrar($routeCollector);
        }
    }

    /**
     * Собрать ServerRequest из суперглобалов PHP.
     *
     * В httpsoft/http-message v1.1 нет ServerRequestFactory::fromGlobals(),
     * поэтому сборка выполняется вручную через PSR-17 фабрики.
     */
    private function createServerRequestFromGlobals(): ServerRequestInterface
    {
        $method = \is_string($_SERVER['REQUEST_METHOD'] ?? null)
            ? \strtoupper($_SERVER['REQUEST_METHOD'])
            : 'GET';

        $scheme = \is_string($_SERVER['REQUEST_SCHEME'] ?? null)
            ? $_SERVER['REQUEST_SCHEME']
            : 'http';

        $host = \is_string($_SERVER['HTTP_HOST'] ?? null)
            ? $_SERVER['HTTP_HOST']
            : 'localhost';

        $uriPath = \is_string($_SERVER['REQUEST_URI'] ?? null)
            ? $_SERVER['REQUEST_URI']
            : '/';

        $uri = new UriFactory()->createUri("{$scheme}://{$host}{$uriPath}");

        $bodyStream = new Stream('php://input');

        /** @var array<non-empty-string, list<string>> $headers */
        $headers = $this->parseServerHeaders($_SERVER);

        return new ServerRequest(
            serverParams: $_SERVER,
            uploadedFiles: $_FILES,
            cookieParams: $_COOKIE,
            queryParams: $_GET,
            parsedBody: $_POST,
            method: $method,
            uri: $uri,
            headers: $headers,
            body: $bodyStream,
        );
    }

    /**
     * Отправить PSR-7 Response в SAPI.
     *
     * Устанавливает HTTP-код, заголовки и выводит тело.
     */
    private function emitResponse(ResponseInterface $response): void
    {
        \http_response_code($response->getStatusCode());

        foreach ($response->getHeaders() as $name => $values) {
            $first = true;
            foreach ($values as $value) {
                \header("{$name}: {$value}", replace: $first);
                $first = false;
            }
        }

        $stream = $response->getBody();
        $stream->rewind();

        echo $stream->getContents();
    }

    /**
     * Загрузить .env файл если существует.
     */
    private function loadEnvironment(): void
    {
        $root = \dirname(__DIR__, 3);
        $envPath = $root . '/.env';

        if (\file_exists($envPath)) {
            $dotenv = Dotenv::createImmutable($root);
            $dotenv->load();
        }
    }

    /**
     * Создать DatabaseManager из ENV-переменных.
     */
    private function createDatabaseManager(): DatabaseManager
    {
        $databaseConfig = new DatabaseConfig([
            'default' => 'default',
            'databases' => [
                'default' => ['connection' => 'postgres'],
            ],
            'connections' => [
                'postgres' => new PostgresDriverConfig(
                    connection: new DsnConnectionConfig(
                        dsn: $_ENV['DB_DSN'] ?? 'pgsql:host=postgres;dbname=opora',
                        user: $_ENV['DB_USERNAME'] ?? 'opora',
                        password: $_ENV['DB_PASSWORD'] ?? 'opora',
                    ),
                ),
            ],
        ]);

        return new DatabaseManager($databaseConfig);
    }

    /**
     * Собрать DI-контейнер с сервисами для console.
     */
    #[\Deprecated(message: 'Будет заменён на buildHttpContainer() в будущем.')]
    private function createContainer(
        DatabaseProviderInterface $databaseProvider,
        LoggerInterface $logger,
    ): Container {
        $containerConfig = ContainerConfig::create()
            ->withDefinitions([
                // Интерфейсы → реализации
                DatabaseProviderInterface::class => $databaseProvider,
                LoggerInterface::class => $logger,

                // Module services
                ModuleRegistry::class => [
                    'class' => ModuleRegistry::class,
                    '__construct()' => [
                        'container' => Reference::to(ContainerInterface::class),
                        'configPath' => \dirname(__DIR__, 3) . '/config/opora-modules.php',
                    ],
                ],

                ModuleMigrationRunner::class => new ModuleMigrationRunner($databaseProvider, $logger),

                CoreModuleInstaller::class => [
                    'class' => CoreModuleInstaller::class,
                    '__construct()' => [
                        'dbal' => Reference::to(DatabaseProviderInterface::class),
                        'logger' => Reference::to(LoggerInterface::class),
                    ],
                    'tags' => ['opora.module.installer'],
                ],

                // Commands
                InstallCommand::class => [
                    'class' => InstallCommand::class,
                    '__construct()' => [
                        'registry' => Reference::to(ModuleRegistry::class),
                        'migrationRunner' => Reference::to(ModuleMigrationRunner::class),
                        'container' => Reference::to(ContainerInterface::class),
                        'dbal' => Reference::to(DatabaseProviderInterface::class),
                        'logger' => Reference::to(LoggerInterface::class),
                    ],
                ],
            ]);

        return new Container($containerConfig);
    }
}
