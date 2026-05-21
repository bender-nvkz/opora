<?php

declare(strict_types=1);

/**
 * Реестр модулей Опоры.
 *
 * Определяет какие модули известны системе, их позицию установки и статус.
 * enabled=true — модуль активен, участвует в install/update.
 * enabled=false — модуль зарегистрирован, но не активен (placeholder).
 *
 * @see ADR-005 §4
 */
return [
    'opora-modules' => [
        'core' => [
            'position' => 1,
            'enabled' => true,
            'description' => 'Ядро платформы: пользователи, токены, папки, установка',
        ],
        'identity' => [
            'position' => 10,
            'enabled' => false,
            'description' => 'Идентификация и аутентификация',
        ],
        'schema' => [
            'position' => 20,
            'enabled' => false,
            'description' => 'Schema-as-Code: классы, атрибуты, воркфлоу',
        ],
        'catalog' => [
            'position' => 30,
            'enabled' => false,
            'description' => 'Каталог: объекты, наследование, Read Model',
        ],
        'search' => [
            'position' => 40,
            'enabled' => false,
            'description' => 'Поиск: pg_trgm, Meilisearch',
        ],
        'api' => [
            'position' => 50,
            'enabled' => false,
            'description' => 'REST API Gateway',
        ],
        'io' => [
            'position' => 60,
            'enabled' => false,
            'description' => 'Импорт/экспорт: CSV, XLSX, XML, JSON, YAML',
        ],
    ],
];
