<?php

declare(strict_types=1);

namespace Opora\Core\Module;

use Psr\Container\ContainerInterface;
use Yiisoft\Di\Reference\TagReference;

/**
 * Реестр модулей — читает config/opora-modules.php и собирает установщики
 * из DI-контейнера по тегу 'opora.module.installer'.
 *
 * Используется в InstallCommand для итерации по модулям в порядке установки.
 *
 * @api
 */
final class ModuleRegistry
{
    private const INSTALLER_TAG = 'opora.module.installer';

    /** @var array<string, bool> module_name => enabled */
    private array $modules;

    /** @var array<string, ModuleInstallerInterface>|null ленивый кэш */
    private null|array $installers = null;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string $configPath,
    ) {
        $this->modules = $this->loadConfig();

        if (!isset($this->modules['core']) || $this->modules['core'] !== true) {
            throw new \RuntimeException('Module "core" must be enabled');
        }
    }

    /**
     * Проверяет, включён ли модуль в config/opora-modules.php.
     *
     * @param string $name Машинное имя модуля (ключ в конфиге).
     *
     * @return bool true если модуль есть в конфиге и его значение true.
     */
    public function isEnabled(string $name): bool
    {
        return isset($this->modules[$name]) && $this->modules[$name] === true;
    }

    /**
     * Возвращает установщики для всех включённых модулей, у которых
     * зарегистрирован инсталлятор в DI, отсортированные по position.
     *
     * @return list<ModuleInstallerInterface>
     */
    public function getEnabled(): array
    {
        $installers = $this->getInstallers();
        $result = [];

        foreach ($this->modules as $name => $enabled) {
            if ($enabled !== true) {
                continue;
            }

            if (isset($installers[$name])) {
                $result[] = $installers[$name];
            }
        }

        \usort($result, static function (ModuleInstallerInterface $a, ModuleInstallerInterface $b): int {
            return $a->getPosition() <=> $b->getPosition();
        });

        return $result;
    }

    /**
     * Проверяет, зарегистрирован ли установщик для указанного модуля.
     *
     * @param string $name Машинное имя модуля.
     *
     * @return bool true если установщик найден в DI-контейнере.
     */
    public function hasInstaller(string $name): bool
    {
        return isset($this->getInstallers()[$name]);
    }

    /**
     * Возвращает установщик для указанного модуля.
     *
     * @param string $name Машинное имя модуля.
     *
     * @throws \RuntimeException если установщик не найден.
     */
    public function getInstaller(string $name): ModuleInstallerInterface
    {
        $installers = $this->getInstallers();

        return $installers[$name]
            ?? throw new \RuntimeException('Installer for module "' . $name . '" not found');
    }

    /**
     * Загружает конфиг из PHP-файла.
     *
     * Поддерживает два формата:
     * - Flat bool: ['core' => true, 'identity' => false]
     * - Nested metadata: ['opora-modules' => ['core' => ['enabled' => true, 'position' => 1], ...]]
     *
     * @return array<string, bool> module_name => enabled
     */
    private function loadConfig(): array
    {
        /** @var mixed $modules */
        $modules = require $this->configPath;

        if (!\is_array($modules)) {
            return [];
        }

        // Nested metadata format: ключ 'opora-modules' содержит массив модулей с метаданными
        if (isset($modules['opora-modules']) && \is_array($modules['opora-modules'])) {
            $modules = $modules['opora-modules'];
        }

        $result = [];
        foreach ($modules as $name => $entry) {
            $result[(string) $name] = \is_bool($entry) ? $entry : (bool) ($entry['enabled'] ?? false);
        }

        return $result;
    }

    /**
     * Получает все установщики из DI-контейнера по тегу.
     *
     * @return array<string, ModuleInstallerInterface> name => installer
     */
    private function discoverInstallers(): array
    {
        $tagId = TagReference::id(self::INSTALLER_TAG);

        if (!$this->container->has($tagId)) {
            return [];
        }

        /** @var mixed $tagged */
        $tagged = $this->container->get($tagId);
        $list = \is_array($tagged) ? $tagged : [];

        $map = [];
        foreach ($list as $installer) {
            \assert($installer instanceof ModuleInstallerInterface);
            $map[$installer->getModuleName()] = $installer;
        }

        return $map;
    }

    /**
     * Ленивая инициализация кэша установщиков.
     *
     * @return array<string, ModuleInstallerInterface> name => installer
     */
    private function getInstallers(): array
    {
        if ($this->installers === null) {
            $this->installers = $this->discoverInstallers();
        }

        return $this->installers;
    }
}
