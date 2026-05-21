<?php

declare(strict_types=1);

namespace Opora\Core\Tests\Unit\Command;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\Database\StatementInterface;
use Opora\Core\Command\InstallCommand;
use Opora\Core\Module\InstallContext;
use Opora\Core\Module\ModuleInstallerInterface;
use Opora\Core\Module\ModuleMigrationRunnerInterface;
use Opora\Core\Module\ModuleRegistry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit-тесты InstallCommand с моками.
 *
 * ModuleRegistry — реальный (легковесный, без БД).
 * ModuleMigrationRunnerInterface — мок (интерфейс).
 * ModuleInstallerInterface — мок (интерфейс).
 * DatabaseProviderInterface — мок (интерфейс).
 *
 * @api
 */
#[AllowMockObjectsWithoutExpectations]
final class InstallCommandUnitTest extends TestCase
{
    private const string INSTALLER_TAG_ID = 'tag@opora.module.installer';

    private ModuleRegistry $moduleRegistry;

    private ModuleMigrationRunnerInterface&MockObject $migrationRunner;

    private ContainerInterface&MockObject $container;

    private DatabaseProviderInterface&MockObject $dbal;

    private LoggerInterface&MockObject $logger;

    private DatabaseInterface&MockObject $database;

    private CommandTester $commandTester;

    private string $configPath;

    protected function setUp(): void
    {
        $this->configPath = \tempnam(\sys_get_temp_dir(), 'opora_modules_');

        $this->migrationRunner = $this->createMock(ModuleMigrationRunnerInterface::class);

        $this->container = $this->createMock(ContainerInterface::class);

        $this->dbal = $this->createMock(DatabaseProviderInterface::class);

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger
            ->method('emergency')
            ->willReturnCallback(static function (): void {
            });

        /** @disregard PHPStan false-positive — psalm bug in phpstan-phpunit */
        $this->database = $this->createMock(DatabaseInterface::class);

        $this->dbal
            ->method('database')
            ->willReturn($this->database);
    }

    protected function tearDown(): void
    {
        if (\file_exists($this->configPath)) {
            @\unlink($this->configPath);
        }
    }

    /**
     * --modules=core → модуль устанавливается: миграции, install(), запись.
     */
    public function test_execute_installs_module_from_option(): void
    {
        $installer = $this->createInstallerMock('core');
        $this->createRegistry(['core' => true], [$installer]);

        // isModuleInstalled: query бросает исключение → таблицы нет
        $this->database
            ->expects($this->once())
            ->method('query')
            ->with(
                $this->stringContains('opora_installation'),
                $this->identicalTo(['core']),
            )
            ->willThrowException(new \RuntimeException('relation "opora_installation" does not exist'));

        $this->migrationRunner
            ->expects($this->once())
            ->method('run')
            ->with('core', '/tmp/migrations/core', 'Opora\\Test\\Migration');

        $installer
            ->expects($this->once())
            ->method('install')
            ->with($this->isInstanceOf(InstallContext::class));

        $this->database
            ->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('INSERT INTO opora_installation'),
                $this->callback(fn (array $params): bool => $params[0] === 'core'),
            );

        $exitCode = $this->commandTester->execute(['--modules' => 'core']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('installed successfully', $this->commandTester->getDisplay());
    }

    /**
     * --modules=core → модуль уже установлен → пропуск, без миграций.
     */
    public function test_execute_skips_already_installed_module(): void
    {
        $installer = $this->createInstallerMock('core');
        $this->createRegistry(['core' => true], [$installer]);

        // Возвращаем итерируемый результат с одной строкой → модуль установлен
        $statement = new class ([['1' => '1']]) extends \ArrayIterator implements StatementInterface {
            public function getQueryString(): string
            {
                return 'SELECT 1';
            }

            public function fetch(int $mode = 2): mixed
            {
                return $this->current();
            }

            public function fetchColumn(null|int $columnNumber = null): mixed
            {
                return null;
            }

            /** @return array<array-key, mixed> */
            public function fetchAll(int $mode = 2): array
            {
                return $this->getArrayCopy();
            }

            public function columnCount(): int
            {
                return \count($this->getArrayCopy()[0] ?? []);
            }

            public function close(): void
            {
            }

            public function rowCount(): int
            {
                return \count($this->getArrayCopy());
            }
        };

        $this->database
            ->expects($this->once())
            ->method('query')
            ->with(
                $this->stringContains('opora_installation'),
                $this->identicalTo(['core']),
            )
            ->willReturn($statement);

        // Нижележащие шаги НЕ вызываются
        $this->migrationRunner
            ->expects($this->never())
            ->method('run');

        $installer
            ->expects($this->never())
            ->method('install');

        $this->database
            ->expects($this->never())
            ->method('execute');

        $exitCode = $this->commandTester->execute(['--modules' => 'core']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('already installed', $this->commandTester->getDisplay());
    }

    /**
     * --modules=unknown → модуль без installer → RuntimeException.
     */
    public function test_execute_throws_for_missing_installer(): void
    {
        $this->createRegistry(['core' => true]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unknown');

        $this->commandTester->execute(['--modules' => 'unknown']);
    }

    /**
     * Без --modules → используются enabled модули из реестра.
     */
    public function test_execute_uses_enabled_modules_by_default(): void
    {
        $installer = $this->createInstallerMock('core');
        $this->createRegistry(['core' => true], [$installer]);

        // isModuleInstalled: query бросает исключение → таблицы нет
        $this->database
            ->expects($this->once())
            ->method('query')
            ->with(
                $this->stringContains('opora_installation'),
                $this->identicalTo(['core']),
            )
            ->willThrowException(new \RuntimeException('relation "opora_installation" does not exist'));

        $this->migrationRunner
            ->expects($this->once())
            ->method('run')
            ->with('core', '/tmp/migrations/core', 'Opora\\Test\\Migration');

        $installer
            ->expects($this->once())
            ->method('install')
            ->with($this->isInstanceOf(InstallContext::class));

        $this->database
            ->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('INSERT INTO opora_installation'),
                $this->callback(fn (array $params): bool => $params[0] === 'core'),
            );

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('installed successfully', $this->commandTester->getDisplay());
    }

    /**
     * Без --modules, enabled модулей нет → warning, SUCCESS.
     */
    public function test_execute_no_modules_warns(): void
    {
        $this->createRegistry(['core' => true]);

        // getEnabled() возвращает пустой массив (installers не зарегистрированы в контейнере)
        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('No modules to install', $this->commandTester->getDisplay());
    }

    /**
     * Создаёт тестовый конфиг и реестр.
     *
     * @param array<string, bool>            $modules
     * @param list<ModuleInstallerInterface> $installers
     */
    private function createRegistry(array $modules, array $installers = []): void
    {
        \file_put_contents($this->configPath, '<?php return ' . \var_export($modules, true) . ';');

        $this->container
            ->method('has')
            ->willReturnCallback(fn (string $id): bool => match ($id) {
                self::INSTALLER_TAG_ID => $installers !== [],
                default => false,
            });

        if ($installers !== []) {
            $this->container
                ->method('get')
                ->willReturnMap([
                    [self::INSTALLER_TAG_ID, $installers],
                ]);
        }

        $this->moduleRegistry = new ModuleRegistry($this->container, $this->configPath);

        $installCommand = new InstallCommand(
            $this->moduleRegistry,
            $this->migrationRunner,
            $this->container,
            $this->dbal,
            $this->logger,
        );

        $this->commandTester = new CommandTester($installCommand);
    }

    private function createInstallerMock(string $name): ModuleInstallerInterface&MockObject
    {
        $installer = $this->createMock(ModuleInstallerInterface::class);
        $installer
            ->method('getModuleName')
            ->willReturn($name);
        $installer
            ->method('getMigrationDirectory')
            ->willReturn('/tmp/migrations/' . $name);
        $installer
            ->method('getMigrationNamespace')
            ->willReturn('Opora\\Test\\Migration');

        return $installer;
    }
}
