<?php

declare(strict_types=1);

namespace Opora\Core;

use Cycle\Database\Config\DatabaseConfig;
use Cycle\Database\Config\Postgres\DsnConnectionConfig;
use Cycle\Database\Config\PostgresDriverConfig;
use Cycle\Database\DatabaseManager;
use Cycle\Database\DatabaseProviderInterface;
use Opora\Core\Command\InstallCommand;
use Opora\Core\Module\CoreModuleInstaller;
use Opora\Core\Module\ModuleMigrationRunner;
use Opora\Core\Module\ModuleRegistry;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Yiisoft\Definitions\Reference;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;

/**
 * Точка входа: читает ENV, строит DI-контейнер, запускает Console runner.
 *
 * @api
 *
 * @todo Полная интеграция с Yii3 config-plugin, middleware pipeline и роутером — Slice B.
 *       Сейчас — минимальный bootstrap для консольных команд.
 */
final class Application
{
    /**
     * HTTP entrypoint.
     *
     * @todo Реализовать middleware pipeline в Slice B.
     */
    public function run(): void
    {
        echo 'OK';
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
        $this->loadEnvironment();

        $params = $this->loadParams();
        $dbal = $this->createDatabaseManager();
        $logger = new NullLogger();

        $container = $this->createContainer($params, $dbal, $logger);

        $console = new SymfonyApplication('Opora', '0.1.0');
        $console->add($container->get(InstallCommand::class));

        return $console->run(new ArgvInput(), new ConsoleOutput());
    }

    /**
     * Загрузить .env файл если существует.
     */
    private function loadEnvironment(): void
    {
        $root = \dirname(__DIR__, 3);
        $envPath = $root . '/.env';

        if (\file_exists($envPath)) {
            $dotenv = \Dotenv\Dotenv::createImmutable($root);
            $dotenv->load();
        }
    }

    /**
     * Загрузить params из config-файлов.
     *
     * @return array{opora-modules: array<non-empty-string, array{position: int, enabled: bool, description: non-empty-string}>}
     */
    private function loadParams(): array
    {
        return [
            'opora-modules' => require \dirname(__DIR__, 3) . '/config/opora-modules.php',
        ];
    }

    /**
     * Создать DatabaseManager из ENV-переменных.
     */
    private function createDatabaseManager(): DatabaseManager
    {
        $dbConfig = new DatabaseConfig([
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

        return new DatabaseManager($dbConfig);
    }

    /**
     * Собрать DI-контейнер с сервисами модулей.
     *
     * @param array{opora-modules: array<non-empty-string, array{position: int, enabled: bool, description: non-empty-string}>} $params
     */
    private function createContainer(
        array $params,
        DatabaseProviderInterface $dbal,
        LoggerInterface $logger,
    ): Container {
        $config = ContainerConfig::create()
            ->withDefinitions([
                // Интерфейсы → реализации
                DatabaseProviderInterface::class => $dbal,
                LoggerInterface::class => $logger,

                // Module services
                ModuleRegistry::class => [
                    'class' => ModuleRegistry::class,
                    '__construct()' => [
                        'container' => Reference::to(ContainerInterface::class),
                        'configPath' => \dirname(__DIR__, 3) . '/config/opora-modules.php',
                    ],
                ],

                ModuleMigrationRunner::class => new ModuleMigrationRunner($dbal, $logger),

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

        return new Container($config);
    }
}
