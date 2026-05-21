<?php

declare(strict_types=1);

use Cycle\Database\DatabaseProviderInterface;
use Opora\Core\Command\InstallCommand;
use Opora\Core\Module\ModuleMigrationRunner;
use Opora\Core\Module\ModuleRegistry;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Definitions\Reference;

/**
 * DI-контейнер: консольные команды.
 */
return [
    InstallCommand::class => [
        'class' => InstallCommand::class,
        '__construct()' => [
            'registry' => Reference::to(ModuleRegistry::class),
            'migrationRunner' => Reference::to(ModuleMigrationRunner::class),
            'container' => Reference::to(ContainerInterface::class),
            'dbal' => Reference::to(DatabaseProviderInterface::class),
            'logger' => Reference::to(LoggerInterface::class),
        ],
    ],
];
