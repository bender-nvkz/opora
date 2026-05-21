<?php

declare(strict_types=1);

namespace Opora\Core\Tests\Unit\Module;

use Opora\Core\Module\InstallContext;
use Opora\Core\Module\ModuleInstallerInterface;
use Opora\Core\Module\ModuleRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Specification для ModuleRegistry.
 *
 * ModuleRegistry читает config/opora-modules.php и собирает установщики
 * из DI-контейнера по тегу 'opora.module.installer'.
 *
 * @note ContainerInterface мокается, т.к. yiisoft/di\Container — единственная
 *       реализация, поддерживающая TagReference. InMemoryContainer в yiisoft/di
 *       отсутствует. Мок оправдан: мы тестируем ModuleRegistry, а не DI.
 * @note ModuleInstallerInterface::getPosition() — static, поэтому мок невозможен.
 *       Используем именованный test double TestInstallerDummy.
 *
 * @see Opora\Core\Module\ModuleRegistry
 */
final class ModuleRegistryTest extends TestCase
{
    private const INSTALLER_TAG_ID = 'tag@opora.module.installer';

    private string $configPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configPath = \tempnam(\sys_get_temp_dir(), 'opora_modules_');
    }

    protected function tearDown(): void
    {
        if (\file_exists($this->configPath)) {
            \unlink($this->configPath);
        }

        parent::tearDown();
    }

    public function test_isEnabled_returns_true_for_enabled_module(): void
    {
        $this->writeConfig(['core' => true]);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        $registry = new ModuleRegistry($container, $this->configPath);

        self::assertTrue($registry->isEnabled('core'));
    }

    public function test_isEnabled_returns_false_for_disabled_module(): void
    {
        $this->writeConfig(['core' => true, 'identity' => false]);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        $registry = new ModuleRegistry($container, $this->configPath);

        self::assertFalse($registry->isEnabled('identity'));
    }

    public function test_isEnabled_returns_false_for_undefined_module(): void
    {
        $this->writeConfig(['core' => true]);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        $registry = new ModuleRegistry($container, $this->configPath);

        self::assertFalse($registry->isEnabled('nonexistent'));
    }

    public function test_getEnabled_returns_modules_sorted_by_position(): void
    {
        // getEnabled() = enabled_modules ∩ modules_with_installers, sorted by position
        // catalog enabled in config but has no installer → excluded
        // schema has installer but not in config → excluded
        // identity enabled AND has installer → included (position 3)
        // core enabled AND has installer → included (position 1)
        $this->writeConfig([
            'catalog' => true,
            'core' => true,
            'identity' => true,
        ]);

        $container = $this->createContainerWithInstallers([
            new TestInstallerDummy('schema', 2),
            new TestInstallerDummy('core', 1),
            new TestInstallerDummy('identity', 3),
        ]);

        $registry = new ModuleRegistry($container, $this->configPath);
        $enabled = $registry->getEnabled();

        self::assertCount(2, $enabled);
        self::assertSame('core', $enabled[0]->getModuleName());
        self::assertSame('identity', $enabled[1]->getModuleName());
    }

    public function test_getEnabled_returns_only_enabled_modules(): void
    {
        $this->writeConfig([
            'core' => true,
            'identity' => false,
            'catalog' => true,
        ]);

        $container = $this->createContainerWithInstallers([
            new TestInstallerDummy('core', 1),
            new TestInstallerDummy('catalog', 4),
        ]);

        $registry = new ModuleRegistry($container, $this->configPath);
        $enabled = $registry->getEnabled();

        self::assertCount(2, $enabled);
        self::assertSame('core', $enabled[0]->getModuleName());
        self::assertSame('catalog', $enabled[1]->getModuleName());
    }

    public function test_hasInstaller_returns_true_when_installer_registered(): void
    {
        $this->writeConfig(['core' => true]);

        $container = $this->createContainerWithInstallers([
            new TestInstallerDummy('core', 1),
        ]);

        $registry = new ModuleRegistry($container, $this->configPath);

        self::assertTrue($registry->hasInstaller('core'));
    }

    public function test_hasInstaller_returns_false_when_no_installer(): void
    {
        $this->writeConfig(['core' => true, 'schema' => true]);

        $container = $this->createContainerWithInstallers([]);

        $registry = new ModuleRegistry($container, $this->configPath);

        self::assertFalse($registry->hasInstaller('schema'));
    }

    public function test_getInstaller_returns_specific_installer(): void
    {
        $this->writeConfig(['core' => true]);

        $coreInstaller = new TestInstallerDummy('core', 1);

        $container = $this->createContainerWithInstallers([$coreInstaller]);

        $registry = new ModuleRegistry($container, $this->configPath);

        self::assertSame($coreInstaller, $registry->getInstaller('core'));
    }

    public function test_getInstaller_throws_when_not_found(): void
    {
        $this->writeConfig(['core' => true]);

        $container = $this->createContainerWithInstallers([]);

        $registry = new ModuleRegistry($container, $this->configPath);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Installer for module "core" not found');
        $registry->getInstaller('core');
    }

    public function test_config_with_core_disabled_throws(): void
    {
        $this->writeConfig(['core' => false]);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Module "core" must be enabled');
        new ModuleRegistry($container, $this->configPath);
    }

    /**
     * @param array<string, bool> $modules
     */
    private function writeConfig(array $modules): void
    {
        $content = '<?php return ' . \var_export($modules, true) . ';';
        \file_put_contents($this->configPath, $content);
    }

    /**
     * @param array<int, TestInstallerDummy> $installers
     *
     * @return ContainerInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createContainerWithInstallers(array $installers): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            [self::INSTALLER_TAG_ID, true],
        ]);
        $container->method('get')->willReturnMap([
            [self::INSTALLER_TAG_ID, $installers],
        ]);

        return $container;
    }
}

/**
 * Test double для ModuleInstallerInterface.
 *
 * @note ModuleInstallerInterface::getPosition() — instance method,
 *       поэтому мок через createMock() возможен, но test double
 *       предпочтительнее для читаемости и переиспользования.
 */
final readonly class TestInstallerDummy implements ModuleInstallerInterface
{
    public function __construct(
        private string $moduleName,
        private int $position,
    ) {
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getModuleName(): string
    {
        return $this->moduleName;
    }

    public function getPackageName(): string
    {
        return 'test/' . $this->moduleName;
    }

    public function install(InstallContext $ctx): void
    {
    }

    public function update(InstallContext $ctx): void
    {
    }

    public function getMigrationDirectory(): string
    {
        return \sys_get_temp_dir() . '/opora_migrations';
    }

    public function getMigrationNamespace(): string
    {
        return 'Opora\\Core\\Tests\\Migration';
    }
}
