<?php

declare(strict_types=1);

use Opora\Core\Command\InstallCommand;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Definitions\Reference;

/**
 * DI-контейнер: консольные команды.
 *
 * @var array{opora-modules: array<non-empty-string, array{position: int, enabled: bool, description: non-empty-string}>} $params Мердж всех params-файлов
 */
return [
    InstallCommand::class => [
        'class' => InstallCommand::class,
        '__construct()' => [
            'registry' => Reference::to(\Opora\Core\Module\ModuleRegistry::class),
            'migrationRunner' => Reference::to(\Opora\Core\Module\ModuleMigrationRunner::class),
            'container' => Reference::to(ContainerInterface::class),
            'dbal' => Reference::to(\Cycle\Database\DatabaseProviderInterface::class),
            'logger' => Reference::to(LoggerInterface::class),
        ],
    ],
];
