# ADR-005: Module Lifecycle Contract — структура, установка, обновление, конфигурация

**Date:** 2026-05-20
**Status:** Proposed
**Deciders:** Core team

## Context

По мере роста количества модулей Опоры (core, schema-as-code, identity, catalog,
search, api-rest, io + внешние) возникла необходимость в стандартизации:

1. **Модульная установка.** Текущий `bin/opora install` (ADR-004 §3) описывает
   жёсткий pipeline с фиксированными позициями. Он не позволяет:
   - установить подмножество модулей (например, только `core` + `schema`)
   - добавить внешний модуль без изменения кода ядра
   - управлять включением/отключением модулей после установки

2. **Обновление модулей.** Если модуль (системный или внешний) обновлён через
   `composer update`, новые миграции должны примениться к уже работающей
   инсталляции. Текущий pipeline имеет только `install`, нет `update`.

3. **Конфигурация модулей.** Каждый модуль может иметь свои настройки
   (API-ключи, лимиты, флаги). Плоские ENV-переменные не масштабируются на
   десятки модулей. Нужна структурированная трёхуровневая конфигурация:
   defaults → project override → ENV secrets.

4. **Состав модулей.** Платформа может работать с разным набором модулей.
   Единственный обязательный — `opora/core`. Все остальные опциональны.
   Нужен механизм декларации состава и проверки зависимостей.

## Decision

### 1. ModuleInstallerInterface — контракт жизненного цикла модуля

Каждый модуль, который требует шагов установки/обновления (миграции + post-migration
хуки), реализует один класс-установщик:

```php
namespace Opora\Core\Module;

/**
 * Контракт жизненного цикла модуля.
 * Один класс на модуль. Реализация живёт в модуле.
 * Регистрируется в DI через тег 'opora.module.installer'.
 */
interface ModuleInstallerInterface
{
    /**
     * Порядок установки модуля (позиция в pipeline).
     * core=1, schema=2, identity=3, catalog=4, search=5, api=6, io=7
     * Внешние модули: >= 100.
     */
    public static function getPosition(): int;

    /** Машинное имя модуля (ключ в config/opora-modules.php) */
    public function getModuleName(): string;

    /** Composer package name (e.g. 'opora/core', 'acme/opora-telegram') */
    public function getPackageName(): string;

    /**
     * Установка: вызывается после применения всех миграций модуля.
     * - schema:sync (для schema-as-code)
     * - seed-данные (root-folder, admin-user)
     * - создание маркера установки
     */
    public function install(InstallContext $ctx): void;

    /**
     * Обновление: вызывается после применения НОВЫХ миграций при bin/opora update.
     * - schema:sync обновлённых определений
     * - data-migrations (ON CONFLICT DO NOTHING)
     * - индексы, триггеры
     */
    public function update(InstallContext $ctx): void;
}
```

```php
namespace Opora\Core\Module;

/**
 * Контекст, передаваемый в install() и update().
 * Содержит всё, что нужно модулю для выполнения lifecycle-хуков.
 */
final readonly class InstallContext
{
    public function __construct(
        public ContainerInterface $container,
        public DatabaseInterface  $database,
        public LoggerInterface    $logger,
        public InputInterface     $input,   // CLI input (--admin-email и т.д.)
    ) {}
}
```

**Регистрация в DI** — через тег:

```php
// config/common.php модуля
use Opora\Core\Module\ModuleInstallerInterface;

return [
    ModuleInstallerInterface::class => [
        'tag' => 'opora.module.installer',
        'class' => \Opora\Schema\ModuleInstaller::class,
    ],
];
```

### 2. ModuleMigrationRunner — обёртка для per-module миграций

Поскольку `yiisoft/yii-cycle` не пробрасывает `vendorDirectories[]` из
`cycle/migrations`, каждый модуль выполняет свои миграции через отдельный
экземпляр `Migrator`:

```php
namespace Opora\Core\Module;

/**
 * Запускает миграции для одного модуля.
 * Создаёт временный Migrator с MigrationConfig, указывающим на директорию модуля.
 * Все миграции трекаются в единую таблицу opora_migration.
 */
final class ModuleMigrationRunner
{
    public function __construct(
        private DatabaseManager     $dbal,
        private LoggerInterface     $logger,
    ) {}

    /**
     * Запускает все pending миграции из директории модуля.
     * @param string $moduleName — для логирования
     * @param string $directory — путь к migrations/ модуля
     * @param string $namespace — namespace классов миграций
     */
    public function run(string $moduleName, string $directory, string $namespace): void
    {
        $config = new MigrationConfig([
            'directory' => $directory,
            'namespace' => $namespace,
            'table'     => 'opora_migration',  // единая таблица
            'safe'      => true,
        ]);
        $migrator = new Migrator($config, $this->dbal, new FileRepository($config));
        $migrator->configure();  // создаёт opora_migration если нет
        while ($migrator->run() !== null) {}  // все pending
        $this->logger->info("Module {module}: migrations applied", ['module' => $moduleName]);
    }
}
```

**Обоснование отдельного runner вместо vendorDirectories:**
- `vendorDirectories[]` требует composer-плагина для сбора директорий (см. tech report §5)
- В Stage 1 у нас 2 модуля (core + schema) — оверхед composer-плагина не оправдан
- ModuleMigrationRunner явный, тестируемый, не зависит от extra-секций composer.json
- При >10 модулях — рассмотреть composer-плагин (ADR-006, будущее)

### 3. ModuleRegistry — сервис состояния модулей

```php
namespace Opora\Core\Module;

/**
 * Центральный реестр модулей: кто включен, кто установлен, кто что предоставляет.
 * Читает config/opora-modules.php + DI-теги ModuleInstallerInterface.
 */
final class ModuleRegistry
{
    /** @var array<string, bool> moduleName => enabled */
    private array $modules;

    /** @var array<string, ModuleInstallerInterface> moduleName => installer */
    private array $installers;

    public function __construct(
        ContainerInterface $container,
        string $configPath = '@root/config/opora-modules.php',
    ) {
        $this->modules = require $this->resolvePath($configPath);
        // Собирает все ModuleInstallerInterface из DI по тегу 'opora.module.installer'
        $this->installers = $this->discoverInstallers($container);
    }

    /** Включён ли модуль? */
    public function isEnabled(string $moduleName): bool
    {
        return $this->modules[$moduleName] ?? false;
    }

    /** Список enabled модулей, отсортированных по getPosition() */
    public function getEnabled(): array { ... }

    /** Есть ли установщик для модуля? */
    public function hasInstaller(string $moduleName): bool { ... }

    /** Получить установщик модуля */
    public function getInstaller(string $moduleName): ModuleInstallerInterface { ... }
}
```

### 4. config/opora-modules.php — реестр модулей

Плоский файл, генерируемый вручную, под git:

```php
// config/opora-modules.php
// Все модули в одном плоском формате.
// Ключ: машинное имя модуля (для системных — короткое, для внешних — composer package name)
// Значение: true = включен, false = выключен
declare(strict_types=1);

return [
    // Системные модули
    'core'     => true,   // обязательный, всегда true
    'schema'   => true,
    'identity' => false,
    'catalog'  => true,
    'search'   => false,
    'api'      => true,
    'io'       => false,

    // Внешние модули — ключ = composer package name
    //'acme/opora-telegram-notify' => true,
];
```

**Правила:**
- `core` всегда `true` (единственный обязательный модуль)
- Модуль, отсутствующий в файле, считается `false`
- Файл можно редактировать вручную или через `module:enable`/`module:disable` команды
- При `composer require` внешнего модуля разработчик сам добавляет строку в этот файл

**Версия модуля НЕ хранится в конфиге** — версия определяется `composer.json`/`composer.lock`.
Состояние установки (какие миграции выполнены) — таблицей `opora_migration`.

### 5. Консольные команды (итоговый список)

| Команда | Назначение | Реализация |
|---------|-----------|------------|
| `bin/opora install` | Полная установка: создание БД, миграции, seed всех enabled модулей | **Новая** (заменяет старый pipeline) |
| `bin/opora update` | Применение новых миграций для всех enabled модулей + update-хуки | **Новая** |
| `bin/opora module:list` | Статус всех модулей (enabled/disabled, installed/pending) | **Новая** |
| `bin/opora module:enable <name>` | Включить модуль в config | **Новая** |
| `bin/opora module:disable <name>` | Отключить модуль (НЕ удаляет таблицы!) | **Новая** |
| `bin/opora migrate:up` | cycle/migrations — все pending | существует |
| `bin/opora migrate:down` | Откат последней миграции | существует |
| `bin/opora migrate:list` | Список миграций с статусом | существует |
| `bin/opora schema:sync` | Schema-as-Code | существует |
| `bin/opora extension:install` | Marketplace (задел) | в будущем |

### 6. Алгоритм install

```
bin/opora install [--modules=core,schema] [--admin-email=...] [--admin-password=...]

1. ModuleRegistry читает config/opora-modules.php
2. Если --modules передан — использует только указанные (валидация что все есть в config)
3. Сортирует enabled модули по getPosition()
4. Для каждого модуля:
   a. detect: SELECT to_regclass('opora_migration') + installed маркер
   b. CREATE EXTENSION IF NOT EXISTS ... (только core)
   c. ModuleMigrationRunner::run() — все миграции модуля
   d. ModuleInstallerInterface::install(InstallContext) — seed, schema:sync, маркер
5. Запись финального маркера в opora_installation
```

**Идемпотентность:** повторный `install` без `--force` находит маркер → exit 0.
С `--force` — подтверждение и переустановка.

### 7. Алгоритм update

```
bin/opora update

1. ModuleRegistry::getEnabled() — все включённые модули
2. Для каждого (по getPosition()):
   a. Проверить: есть ли директория миграций?
   b. ModuleMigrationRunner::run() — применить новые
   c. Если были новые миграции → ModuleInstallerInterface::update()
3. Вывести отчёт: какие модули обновлены, сколько миграций применено
```

**Идемпотентность:** если миграций нет — update no-op.

### 8. Трёхуровневая конфигурация модулей

Каждый модуль может определять свою конфигурацию. Три уровня:

```
Уровень 1 (defaults):  vendor/{module}/config/params.php
                        Значения по умолчанию, под git в модуле

Уровень 2 (override):  config/params/{module}.php
                        Проектные значения, под git проекта

Уровень 3 (secrets):   .env / ENV-переменные
                        Чувствительные данные, НЕ в git
```

**Пример для внешнего модуля Telegram:**

```php
// vendor/acme/opora-telegram-notify/config/params.php (defaults)
return [
    'acme/opora-telegram-notify' => [
        'bot_token' => '',
        'chat_id' => 0,
        'notify_on' => ['workflow.transition'],
        'template' => 'default',
    ],
];

// config/params/acme-opora-telegram-notify.php (project override)
return [
    'acme/opora-telegram-notify' => [
        'bot_token' => $_ENV['TELEGRAM_BOT_TOKEN'] ?? '',
        'chat_id' => (int)($_ENV['TELEGRAM_CHAT_ID'] ?? 0),
    ],
];
```

**Typed Config класс в модуле:**

```php
final readonly class TelegramNotifyConfig
{
    public function __construct(
        public string $botToken,
        public int    $chatId,
        public array  $notifyOn,
        public string $template,
    ) {}

    public static function fromParams(array $params): self
    {
        $cfg = $params['acme/opora-telegram-notify'] ?? throw new \InvalidArgumentException(
            'Missing acme/opora-telegram-notify config'
        );
        return new self(
            botToken: $cfg['bot_token'],
            chatId: (int)($cfg['chat_id'] ?? 0),
            notifyOn: $cfg['notify_on'] ?? [],
            template: $cfg['template'] ?? 'default',
        );
    }
}
```

### 9. Инкапсуляция модулей (мандатные правила)

1. **SQL изоляция:** Миграции модуля пишут SQL только в таблицы со своим префиксом.
   Никакой модуль НЕ трогает таблицы другого модуля через миграции.

2. **Доступ к данным других модулей** — только через публичные Service-интерфейсы:
   ```php
   // ✅ Правильно: через интерфейс core
   $folderService->findByPath($path);

   // ❌ Запрещено: прямой SQL в таблицу core
   $this->database()->execute('SELECT ... FROM opora_folders');
   ```

3. **FK-ссылки** на таблицы другого модуля — только в своих миграциях,
   с полным префиксированным именем: `REFERENCES obj_objects(id)`.

4. **Core-only гарантия:** Единственный обязательный модуль — `core`.
   Все остальные модули должны корректно работать (или gracefully degrage)
   при их отсутствии. Проверка: `class_exists()` или проверка наличия DI-сервиса.

### 10. Порядок установки модулей (позиции)

| Позиция | Модуль | Префикс | Зависимости |
|---------|--------|---------|-------------|
| 1 | `core` | `opora_` | — |
| 2 | `schema` | `opora_` | core |
| 3 | `identity` | `opora_` | core |
| 4 | `catalog` | `obj_` | core, identity |
| 5 | `search` | `search_` | catalog |
| 6 | `api` | `api_` | identity, search |
| 7 | `io` | `io_` | catalog |
| 100+ | external | `ext_<vendor>_` | core + любые |

## Consequences

**Положительные:**
- Модули self-contained: установка, обновление, конфигурация — в одном пакете
- Состав модулей декларативен: `config/opora-modules.php` под git
- `update` команда закрывает пробел жизненного цикла
- Трёхуровневая конфигурация без плоских ENV
- Core-only гарантия: платформа работает с любым подмножеством модулей
- Все решения проверены на cycle/migrations API (реальные методы, не гипотетические)

**Отрицательные:**
- ModuleMigrationRunner создаёт N экземпляров Migrator вместо одного с vendorDirectories
- Каждый модуль должен явно реализовать `ModuleInstallerInterface` (даже если install пустой)
- `config/opora-modules.php` редактируется вручную (no auto-discovery на старте)
- Миграции внешних модулей трекаются в той же `opora_migration` — возможны конфликты имён классов (решение: namespace с vendor prefix)

## Alternatives Considered

1. **Composer-плагин для auto-discovery модулей:** Отклонено для Stage 1.
   Оверхед на старте (2 модуля). Рассмотреть в ADR-006 при >10 модулях.

2. **version в config/opora-modules.php:** Отклонено. Два источника правды
   (config vs composer.lock). Версия определяется composer, состояние миграций —
   таблицей opora_migration. Version в config не нужна.

3. **Единый Migrator с vendorDirectories[]:** Отклонено. `yiisoft/yii-cycle` не
   пробрасывает `vendorDirectories[]`. Для его использования нужен или fork,
   или composer-плагин, собирающий директории — оба варианта тяжелее
   ModuleMigrationRunner.

4. **ENV-only конфигурация:** Отклонено. Плоские ENV не масштабируются,
   нет типизации, нет defaults, нет структуры. Трёхуровневая система решает
   все эти проблемы.

5. **DB-таблица для версий модулей:** Отклонено. Миграции — единственный
   diff-механизм. Таблица `opora_migration` уже хранит, что выполнено.
   Добавление `opora_module_versions` дублирует эту информацию без пользы.
