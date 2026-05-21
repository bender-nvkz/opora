<?php

declare(strict_types=1);

namespace Opora\Core\Tests\Integration\Command;

use Cycle\Database\Config\DatabaseConfig;
use Cycle\Database\Config\Postgres\DsnConnectionConfig;
use Cycle\Database\Config\PostgresDriverConfig;
use Cycle\Database\DatabaseManager;
use Opora\Core\Command\InstallCommand;
use Opora\Core\Module\CoreModuleInstaller;
use Opora\Core\Module\ModuleMigrationRunner;
use Opora\Core\Module\ModuleRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Интеграционный тест InstallCommand с реальной БД.
 *
 * Проверяет done-критерий Среза A:
 * - bin/opora install --modules=core создаёт все 5 таблиц
 * - запись в opora_installation создаётся
 *
 * @api
 */
final class InstallCommandIntegrationTest extends TestCase
{
    private const string INSTALLER_TAG_ID = 'tag@opora.module.installer';

    private DatabaseManager $databaseManager;

    private string $testConfigPath;

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

        // Создать временный конфиг модулей с включённым core
        $this->testConfigPath = \tempnam(\sys_get_temp_dir(), 'opora_modules_');
        \file_put_contents($this->testConfigPath, '<?php return ["core" => true];');

        $this->cleanDatabase();
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();

        if (\file_exists($this->testConfigPath)) {
            @\unlink($this->testConfigPath);
        }

        unset($this->databaseManager);
    }

    /**
     * Установка модуля core создаёт все таблицы и запись в opora_installation.
     */
    public function test_install_creates_tables_and_records_installation(): void
    {
        $container = $this->createContainerWithInstaller();
        $moduleRegistry = new ModuleRegistry($container, $this->testConfigPath);
        $moduleMigrationRunner = new ModuleMigrationRunner($this->databaseManager, $this->createMock(LoggerInterface::class));

        $installCommand = new InstallCommand(
            $moduleRegistry,
            $moduleMigrationRunner,
            $container,
            $this->databaseManager,
            $this->createMock(LoggerInterface::class),
        );

        $commandTester = new CommandTester($installCommand);
        $exitCode = $commandTester->execute([
            '--modules' => 'core',
            '--admin-email' => 'admin@opora.local',
            '--admin-password' => 'secret',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('installed successfully', $commandTester->getDisplay());

        // Проверить, что все таблицы созданы
        $database = $this->databaseManager->database();
        self::assertTrue($database->hasTable('opora_migration'), 'opora_migration table must exist');
        self::assertTrue($database->hasTable('opora_users'), 'opora_users table must exist');
        self::assertTrue($database->hasTable('opora_user_tokens'), 'opora_user_tokens table must exist');
        self::assertTrue($database->hasTable('opora_folders'), 'opora_folders table must exist');
        self::assertTrue($database->hasTable('opora_installation'), 'opora_installation table must exist');

        // Проверить запись в opora_installation
        $statement = $database->query(
            'SELECT module_name, version FROM opora_installation WHERE module_name = ?',
            ['core'],
        );
        $row = $statement->fetch();
        self::assertNotNull($row, 'Installation record for core must exist');
        self::assertSame('core', $row['module_name']);
        self::assertSame('0.1.0', $row['version']);
    }

    /**
     * Повторная установка core не вызывает ошибок — пропускается.
     */
    public function test_install_is_idempotent(): void
    {
        $container = $this->createContainerWithInstaller();
        $moduleRegistry = new ModuleRegistry($container, $this->testConfigPath);
        $moduleMigrationRunner = new ModuleMigrationRunner($this->databaseManager, $this->createMock(LoggerInterface::class));

        $installCommand = new InstallCommand(
            $moduleRegistry,
            $moduleMigrationRunner,
            $container,
            $this->databaseManager,
            $this->createMock(LoggerInterface::class),
        );

        $commandTester = new CommandTester($installCommand);

        // Первая установка
        $firstExitCode = $commandTester->execute([
            '--modules' => 'core',
            '--admin-email' => 'admin@opora.local',
            '--admin-password' => 'secret',
        ]);
        self::assertSame(Command::SUCCESS, $firstExitCode);

        // Вторая установка — idempotent, без ошибок
        $secondExitCode = $commandTester->execute([
            '--modules' => 'core',
            '--admin-email' => 'admin@opora.local',
            '--admin-password' => 'secret',
        ]);
        self::assertSame(Command::SUCCESS, $secondExitCode);
        self::assertStringContainsString('already installed', $commandTester->getDisplay());

        // Проверить, что в opora_installation одна запись
        $database = $this->databaseManager->database();
        $statement = $database->query(
            'SELECT COUNT(*) as cnt FROM opora_installation WHERE module_name = ?',
            ['core'],
        );
        $row = $statement->fetch();
        self::assertSame('1', (string) $row['cnt']);
    }

    /**
     * Удаляет все таблицы, созданные установкой core.
     */
    private function cleanDatabase(): void
    {
        $database = $this->databaseManager->database();

        foreach (['opora_folders', 'opora_user_tokens', 'opora_users', 'opora_installation', 'opora_extensions', 'opora_migration'] as $table) {
            if ($database->hasTable($table)) {
                $database->execute("DROP TABLE IF EXISTS {$table} CASCADE");
            }
        }
    }

    /**
     * Создаёт контейнер, возвращающий CoreModuleInstaller через DI-тег.
     */
    private function createContainerWithInstaller(): ContainerInterface&MockObject
    {
        $coreModuleInstaller = new CoreModuleInstaller($this->databaseManager, $this->createMock(LoggerInterface::class));

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->method('has')
            ->willReturnMap([
                [self::INSTALLER_TAG_ID, true],
            ]);
        $container
            ->method('get')
            ->willReturnMap([
                [self::INSTALLER_TAG_ID, [$coreModuleInstaller]],
            ]);

        return $container;
    }
}
