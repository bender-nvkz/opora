<?php

declare(strict_types=1);

use Cycle\Database\DatabaseProviderInterface;
use Opora\Core\Module\CoreModuleInstaller;
use Opora\Core\Module\ModuleMigrationRunner;
use Opora\Core\Module\ModuleRegistry;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Definitions\Reference;

/**
 * DI-контейнер: общие сервисы.
 *
 * @var array{opora-modules: array<non-empty-string, array{position: int, enabled: bool, description: non-empty-string}>} $params Мердж всех params-файлов
 */
return [
    // Module lifecycle services
    ModuleRegistry::class => [
        'class' => ModuleRegistry::class,
        '__construct()' => [
            'container' => Reference::to(ContainerInterface::class),
            'configPath' => __DIR__ . '/../opora-modules.php',
        ],
    ],

    ModuleMigrationRunner::class => [
        'class' => ModuleMigrationRunner::class,
        '__construct()' => [
            'dbal' => Reference::to(DatabaseProviderInterface::class),
            'logger' => Reference::to(LoggerInterface::class),
        ],
    ],

    CoreModuleInstaller::class => [
        'class' => CoreModuleInstaller::class,
        '__construct()' => [
            'dbal' => Reference::to(DatabaseProviderInterface::class),
            'logger' => Reference::to(LoggerInterface::class),
        ],
        'tags' => ['opora.module.installer'],
    ],
];
