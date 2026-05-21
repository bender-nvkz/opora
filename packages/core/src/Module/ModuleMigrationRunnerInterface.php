<?php

declare(strict_types=1);

namespace Opora\Core\Module;

/**
 * Контракт для выполнения миграций модуля.
 *
 * Реализуется {@see ModuleMigrationRunner} и мокается в unit-тестах.
 *
 * @api
 */
interface ModuleMigrationRunnerInterface
{
    /**
     * Применить все pending миграции для указанного модуля.
     *
     * @param string $moduleName Идентификатор модуля (например, 'core', 'identity').
     * @param string $directory  Путь к директории с файлами миграций модуля.
     * @param string $namespace  Пространство имён классов миграций.
     */
    public function run(string $moduleName, string $directory, string $namespace): void;
}
