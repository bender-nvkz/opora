<?php

declare(strict_types=1);

namespace Opora\Core\Module;

use Cycle\Database\DatabaseProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Инсталлятор модуля core.
 *
 * @api
 */
final readonly class CoreModuleInstaller implements ModuleInstallerInterface
{
    public function __construct(
        private DatabaseProviderInterface $databaseProvider,
        private LoggerInterface $logger,
    ) {
    }

    public function getPosition(): int
    {
        return 1;
    }

    public function getModuleName(): string
    {
        return 'core';
    }

    public function getPackageName(): string
    {
        return 'opora/core';
    }

    public function getMigrationDirectory(): string
    {
        return \dirname(__DIR__, 2) . '/migrations';
    }

    public function getMigrationNamespace(): string
    {
        return 'Opora\\Core\\Migration';
    }

    public function install(InstallContext $installContext): void
    {
        $this->seedAdminUser($installContext);
        $this->seedRootFolder();
    }

    public function update(InstallContext $installContext): void
    {
        // TODO: data-migrations при обновлении core
    }

    /**
     * Создать admin-пользователя.
     *
     * Использует --admin-email и --admin-password из InstallContext.
     * ON CONFLICT DO NOTHING — идемпотентность.
     */
    private function seedAdminUser(InstallContext $installContext): void
    {
        /** @var string|null $email */
        $email = $installContext->input->getOption('admin-email');
        /** @var string|null $password */
        $password = $installContext->input->getOption('admin-password');

        if ($email === null || $password === null) {
            $this->logger->warning('CoreModuleInstaller: admin-email or admin-password not provided, skipping admin user creation.');

            return;
        }

        $database = $this->databaseProvider->database();

        $database->execute(
            'INSERT INTO opora_users (email, password_hash, display_name, is_active, created_at) VALUES (?, ?, ?, ?, ?) ON CONFLICT (email) DO NOTHING',
            [
                $email,
                \password_hash($password, \PASSWORD_BCRYPT),
                'Admin',
                true,
                new \DateTimeImmutable()->format('Y-m-d H:i:s'),
            ],
        );

        $this->logger->info('CoreModuleInstaller: admin user created', ['email' => $email]);
    }

    /**
     * Создать корневую папку каталога.
     *
     * ltree path '1' — корневой узел.
     * owner_id = id admin-пользователя (первый в opora_users).
     * Проверка существования через SELECT — идемпотентность без UNIQUE constraint.
     */
    private function seedRootFolder(): void
    {
        $database = $this->databaseProvider->database();

        // Получить ID admin-пользователя
        $statement = $database->query(
            'SELECT id FROM opora_users ORDER BY created_at ASC LIMIT 1',
        );

        $adminId = null;
        foreach ($statement as $row) {
            $adminId = $row['id'];
        }

        if ($adminId === null) {
            $this->logger->warning('CoreModuleInstaller: no admin user found, skipping root folder creation.');

            return;
        }

        // Проверить, не создана ли уже корневая папка
        $exists = $database->query(
            "SELECT 1 FROM opora_folders WHERE path = '1'",
        );

        foreach ($exists as $exist) {
            $this->logger->info('CoreModuleInstaller: root folder already exists, skipping.');

            return;
        }

        $database->execute(
            'INSERT INTO opora_folders (name, slug, owner_id, path, position, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [
                'Root',
                'root',
                $adminId,
                '1',
                0,
                new \DateTimeImmutable()->format('Y-m-d H:i:s'),
            ],
        );

        $this->logger->info('CoreModuleInstaller: root folder created');
    }
}
