<?php

declare(strict_types=1);

use Cycle\Database\DatabaseProviderInterface;
use Opora\Core\Event\EventBus;
use Opora\Core\Event\EventBusInterface;
use Opora\Core\Module\CoreModuleInstaller;
use Opora\Core\Module\ModuleMigrationRunner;
use Opora\Core\Module\ModuleMigrationRunnerInterface;
use Opora\Core\Module\ModuleRegistry;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Definitions\Reference;
use Yiisoft\EventDispatcher\Dispatcher\Dispatcher;
use Yiisoft\EventDispatcher\Provider\Provider;

/**
 * DI-контейнер: общие сервисы.
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

    // Interface → concrete binding
    ModuleMigrationRunnerInterface::class => ModuleMigrationRunner::class,

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

    // Event Bus
    ListenerProviderInterface::class => [
        'class' => Provider::class,
    ],

    EventDispatcherInterface::class => [
        'class' => Dispatcher::class,
    ],

    EventBusInterface::class => [
        'class' => EventBus::class,
        '__construct()' => [
            'eventDispatcher' => Reference::to(EventDispatcherInterface::class),
        ],
    ],
];
