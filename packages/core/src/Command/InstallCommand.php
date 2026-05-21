<?php

declare(strict_types=1);

namespace Opora\Core\Command;

use Cycle\Database\DatabaseProviderInterface;
use Opora\Core\Module\InstallContext;
use Opora\Core\Module\ModuleInstallerInterface;
use Opora\Core\Module\ModuleMigrationRunnerInterface;
use Opora\Core\Module\ModuleRegistry;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Установка модулей платформы Опора.
 *
 * Применяет миграции, выполняет seed-данные и регистрирует установку
 * в таблице opora_installation.
 *
 * @see ADR-005 §6
 *
 * @api
 */
#[AsCommand(
    name: 'install',
    description: 'Install Opora platform modules',
)]
final class InstallCommand extends Command
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ModuleMigrationRunnerInterface $migrationRunner,
        private readonly ContainerInterface $container,
        private readonly DatabaseProviderInterface $dbal,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'modules',
            null,
            InputOption::VALUE_OPTIONAL,
            'Comma-separated list of module names to install',
        );
        $this->addOption(
            'admin-email',
            null,
            InputOption::VALUE_REQUIRED,
            'Admin email for initial user (core module)',
        );
        $this->addOption(
            'admin-password',
            null,
            InputOption::VALUE_REQUIRED,
            'Admin password for initial user (core module)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $modules = $this->resolveModules($input);

        if ($modules === []) {
            $io->warning('No modules to install.');
            return Command::SUCCESS;
        }

        foreach ($modules as $name) {
            if ($this->isModuleInstalled($name)) {
                $io->writeln(\sprintf(
                    'Module <info>%s</info> already installed. Skipping.',
                    $name,
                ));
                continue;
            }

            $installer = $this->registry->getInstaller($name);

            // Применить миграции модуля
            $this->migrationRunner->run(
                $name,
                $installer->getMigrationDirectory(),
                $installer->getMigrationNamespace(),
            );

            // Seed + хуки модуля
            $ctx = new InstallContext(
                container: $this->container,
                logger: $this->logger,
                input: $input,
            );
            $installer->install($ctx);

            // Зарегистрировать установку
            $this->recordInstallation($name);

            $io->writeln(\sprintf(
                'Module <info>%s</info> installed successfully.',
                $name,
            ));
        }

        return Command::SUCCESS;
    }

    /**
     * Получить список модулей для установки.
     *
     * Если указан --modules — парсит csv и валидирует наличие installer.
     * Если не указан — возвращает все enabled модули из реестра.
     *
     * @return list<string>
     */
    private function resolveModules(InputInterface $input): array
    {
        /** @var string|null $filter */
        $filter = $input->getOption('modules');

        if ($filter !== null) {
            /** @var list<string> $names */
            $names = \array_map('trim', \explode(',', $filter));

            foreach ($names as $name) {
                if (!$this->registry->hasInstaller($name)) {
                    throw new \RuntimeException(\sprintf(
                        'Module "%s" has no registered installer.',
                        $name,
                    ));
                }
            }

            return $names;
        }

        return \array_map(
            static fn (ModuleInstallerInterface $i): string => $i->getModuleName(),
            $this->registry->getEnabled(),
        );
    }

    /**
     * Проверить, установлен ли модуль.
     */
    private function isModuleInstalled(string $name): bool
    {
        try {
            $db = $this->dbal->database();

            $result = $db->query(
                'SELECT 1 FROM opora_installation WHERE module_name = ?',
                [$name],
            );

            foreach ($result as $_) {
                return true;
            }

            return false;
        } catch (\Throwable) {
            // Таблица opora_installation ещё не создана — первый запуск
            return false;
        }
    }

    /**
     * Записать установку модуля в БД.
     */
    private function recordInstallation(string $name): void
    {
        $db = $this->dbal->database();

        $db->execute(
            'INSERT INTO opora_installation (module_name, version, installed_at) VALUES (?, ?, ?)',
            [
                $name,
                '0.1.0',
                new \DateTimeImmutable()->format('Y-m-d H:i:s'),
            ],
        );
    }
}
