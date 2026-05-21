<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\CodeQuality\Rector\FunctionLike\SimplifyUselessVariableRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/config',
        __DIR__ . '/packages',
    ])
    ->withSkip([
        __DIR__ . '/vendor',
        __DIR__ . '/runtime',
        __DIR__ . '/node_modules',
        // Не трогать Schema definitions — они намеренно многословны
        __DIR__ . '/config/schema',
        // MiddlewarePipeline: $finalHandler и $next — семантически верные PSR-15 имена,
        // RenameParamToMatchTypeRector ломает named arguments (finalHandler:)
        \Rector\Naming\Rector\ClassMethod\RenameParamToMatchTypeRector::class => [
            __DIR__ . '/packages/core/src/Http/MiddlewarePipeline.php',
        ],
        \Rector\Naming\Rector\Class_\RenamePropertyToMatchTypeRector::class => [
            __DIR__ . '/packages/core/src/Http/MiddlewarePipeline.php',
        ],
        \Rector\Naming\Rector\Assign\RenameVariableToMatchMethodCallReturnTypeRector::class => [
            __DIR__ . '/packages/core/src/Http/MiddlewarePipeline.php',
        ],
    ])

    // PHP 8.4 — целевая версия
    ->withPhpSets(php84: true)

    // Наборы правил — содержат все нужные правила, дублировать в withRules не нужно
    ->withSets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::TYPE_DECLARATION,
        SetList::PRIVATIZATION,
        SetList::NAMING,
    ])

    // Только правила которых НЕТ в наборах выше
    ->withRules([
        SimplifyUselessVariableRector::class,
    ])

    ->withImportNames(importNames: true, importDocBlockNames: true, importShortClasses: false)

    ->withPhpVersion(\Rector\ValueObject\PhpVersion::PHP_84);
