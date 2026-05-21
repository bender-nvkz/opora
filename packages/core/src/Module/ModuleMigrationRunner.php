<?php

declare(strict_types=1);

namespace Opora\Core\Module;

use Cycle\Database\DatabaseProviderInterface;
use Cycle\Migrations\Config\MigrationConfig;
use Cycle\Migrations\FileRepository;
use Cycle\Migrations\MigrationInterface;
use Cycle\Migrations\Migrator;
use Psr\Log\LoggerInterface;

/**
 * Обёртка над Cycle Migrations для per-module миграций.
 *
 * Каждый модуль хранит свои миграции в собственной директории.
 * Этот класс создаёт изолированный Migrator для указанного модуля,
 * настраивает таблицу трекинга {@see self::MIGRATION_TABLE} и выполняет все pending миграции.
 *
 * @api
 */
final readonly class ModuleMigrationRunner implements ModuleMigrationRunnerInterface
{
    /**
     * Таблица для отслеживания выполненных миграций.
     * Единая для всех модулей, с префиксом opora_ (см. ADR-004).
     */
    private const string MIGRATION_TABLE = 'opora_migration';

    public function __construct(
        private DatabaseProviderInterface $databaseProvider,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Применить все pending миграции для указанного модуля.
     *
     * @param string $moduleName Идентификатор модуля (например, 'core', 'identity').
     * @param string $directory  Путь к директории с файлами миграций модуля.
     * @param string $namespace  Пространство имён классов миграций.
     */
    public function run(string $moduleName, string $directory, string $namespace): void
    {
        $migrationConfig = new MigrationConfig([
            'directory' => $directory,
            'namespace' => $namespace,
            'table' => self::MIGRATION_TABLE,
            'safe' => true,
        ]);

        $migrator = new Migrator(
            $migrationConfig,
            $this->databaseProvider,
            new FileRepository($migrationConfig),
        );

        $migrator->configure();

        while ($migrator->run() instanceof MigrationInterface) {
            // Цикл выполняется пока есть pending миграции
        }

        $this->logger->info(
            'Module {module}: migrations applied',
            ['module' => $moduleName],
        );
    }
}
