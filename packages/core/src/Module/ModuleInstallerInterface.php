<?php

declare(strict_types=1);

namespace Opora\Core\Module;

/**
 * Контракт жизненного цикла модуля.
 *
 * Один класс на модуль. Регистрируется в DI через тег 'opora.module.installer'.
 *
 * @api
 */
interface ModuleInstallerInterface
{
    /**
     * Порядок установки модуля (позиция в pipeline).
     *
     * - core=1, schema=2, identity=3, catalog=4, search=5, api=6, io=7
     * - Внешние модули: >= 100.
     *
     * Instance method (не static), т.к. ModuleRegistry получает инсталляторы
     * из контейнера как объекты и сортирует их по этому значению.
     */
    public function getPosition(): int;

    /** Машинное имя модуля (ключ в config/opora-modules.php) */
    public function getModuleName(): string;

    /** Composer package name (e.g. 'opora/core', 'acme/opora-telegram') */
    public function getPackageName(): string;

    /**
     * Установка: вызывается после применения всех миграций модуля.
     *
     * - schema:sync (для schema-as-code)
     * - seed-данные (root-folder, admin-user)
     * - создание маркера установки
     */
    public function install(InstallContext $installContext): void;

    /**
     * Обновление: вызывается после применения НОВЫХ миграций при bin/opora update.
     *
     * - schema:sync обновлённых определений
     * - data-migrations (ON CONFLICT DO NOTHING)
     * - индексы, триггеры
     */
    public function update(InstallContext $installContext): void;

    /**
     * Абсолютный путь к директории с файлами миграций.
     *
     * Обычно: \dirname(__DIR__) . '/migrations'
     *
     * @api
     */
    public function getMigrationDirectory(): string;

    /**
     * PHP namespace классов миграций.
     *
     * Обычно: 'Opora\\Core\\Migration'
     *
     * @api
     */
    public function getMigrationNamespace(): string;
}
