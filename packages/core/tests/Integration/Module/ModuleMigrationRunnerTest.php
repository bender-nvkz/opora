<?php

declare(strict_types=1);

namespace Opora\Core\Tests\Integration\Module;

use Cycle\Database\Config\DatabaseConfig;
use Cycle\Database\Config\Postgres\DsnConnectionConfig;
use Cycle\Database\Config\PostgresDriverConfig;
use Cycle\Database\DatabaseManager;
use Opora\Core\Module\ModuleMigrationRunner;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Интеграционный тест для ModuleMigrationRunner.
 *
 * Проверяет, что per-module миграции применяются через Cycle Migrations:
 * - миграция выполняется и создаёт таблицу
 * - миграция записывается в трекер opora_migration
 * - повторный запуск не вызывает ошибок (idempotent)
 *
 * @requires extension pdo_pgsql
 *
 * @internal
 */
final class ModuleMigrationRunnerTest extends TestCase
{
    private string $migrationsDir;

    private DatabaseManager $databaseManager;

    private string $testTable;

    private string $migrationClassName;

    private int $migrationId;

    protected function setUp(): void
    {
        /** @var non-empty-string $dsn */
        $dsn = 'pgsql:host=postgres;port=5432;dbname=opora';

        $this->databaseManager = new DatabaseManager(new DatabaseConfig([
            'default' => 'default',
            'databases' => [
                'default' => ['connection' => 'postgres'],
            ],
            'connections' => [
                'postgres' => new PostgresDriverConfig(
                    connection: new DsnConnectionConfig(
                        dsn: $dsn,
                        user: 'opora',
                        password: 'opora_dev_password',
                    ),
                ),
            ],
        ]));

        $this->migrationsDir = \sys_get_temp_dir() . '/opora_test_migrations_' . \uniqid();
        \mkdir($this->migrationsDir, 0o777, true);

        $this->testTable = 'opora_test_migration_' . \bin2hex(\random_bytes(4));
        $this->migrationClassName = 'Migration_' . \bin2hex(\random_bytes(4));
        $this->migrationId = \random_int(1, 9999);
    }

    protected function tearDown(): void
    {
        try {
            $db = $this->databaseManager->database();
            $db->execute('DROP TABLE IF EXISTS "' . $this->testTable . '" CASCADE');
            $db->execute('DROP TABLE IF EXISTS "opora_migration" CASCADE');
        } catch (\Throwable) {
            // Ошибки очистки не влияют на результат теста
        }

        $this->removeDirectory($this->migrationsDir);
    }

    public function test_run_creates_migration_table(): void
    {
        // Arrange
        $this->createTestMigration();

        $moduleMigrationRunner = new ModuleMigrationRunner($this->databaseManager, new NullLogger());

        // Act
        $moduleMigrationRunner->run('test', $this->migrationsDir, 'Migration');

        // Assert — проверяем через direct SQL
        $statement = $this->databaseManager->database()->query(
            'SELECT table_name FROM information_schema.tables WHERE table_name = ?',
            ['opora_migration'],
        );

        $rows = \iterator_to_array($statement);
        $this->assertCount(1, $rows, 'ModuleMigrationRunner должен создать таблицу трекинга opora_migration');
    }

    public function test_run_creates_test_table_via_migration(): void
    {
        // Arrange
        $this->createTestMigration();

        $moduleMigrationRunner = new ModuleMigrationRunner($this->databaseManager, new NullLogger());

        // Act
        $moduleMigrationRunner->run('test', $this->migrationsDir, 'Migration');

        // Assert — проверяем что миграция создала таблицу
        $statement = $this->databaseManager->database()->query(
            'SELECT table_name FROM information_schema.tables WHERE table_name = ?',
            [$this->testTable],
        );

        $rows = \iterator_to_array($statement);
        $this->assertCount(1, $rows, 'Миграция должна создать тестовую таблицу');
    }

    public function test_run_records_migration_in_tracker(): void
    {
        // Arrange
        $this->createTestMigration();

        $moduleMigrationRunner = new ModuleMigrationRunner($this->databaseManager, new NullLogger());

        // Act
        $moduleMigrationRunner->run('test', $this->migrationsDir, 'Migration');

        // Assert — проверяем что миграция записана в трекер
        $statement = $this->databaseManager->database()->query(
            'SELECT migration, time_executed FROM opora_migration WHERE migration = ?',
            [$this->migrationClassName],
        );

        $rows = \iterator_to_array($statement);
        $this->assertCount(1, $rows, 'Миграция должна быть записана в трекер');
        $this->assertNotNull($rows[0]['time_executed'], 'time_executed должен быть установлен');
    }

    /**
     * Создаёт файл миграции во временной директории.
     *
     * Формат имени: {timestamp}_{chunk}_{className}.php
     * (формат Cycle Migrations FileRepository).
     */
    private function createTestMigration(): void
    {
        $timestamp = \date('Ymd.His');
        $filename = \sprintf(
            '%s_%d_%s.php',
            $timestamp,
            $this->migrationId,
            $this->migrationClassName,
        );

        $table = $this->testTable;
        $class = $this->migrationClassName;
        $id = $this->migrationId;

        $content = <<<PHP
            <?php

            declare(strict_types=1);

            namespace Migration;

            use Cycle\Migrations\Migration;

            class {$class} extends Migration
            {
                protected int \$id = {$id};

                public function up(): void
                {
                    \$this->table('{$table}')
                        ->addColumn('id', 'primary', ['nullable' => false])
                        ->addColumn('name', 'string', ['nullable' => false, 'size' => 255])
                        ->create();
                }

                public function down(): void
                {
                    \$this->table('{$table}')->drop();
                }
            }
            PHP;

        \file_put_contents($this->migrationsDir . '/' . $filename, $content);
    }

    private function removeDirectory(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $files = \array_diff(\scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;

            if (\is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                \unlink($path);
            }
        }

        \rmdir($dir);
    }
}
