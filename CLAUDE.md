# Опора — корневой контекст для AI-агентов

> Читай этот файл перед любой задачей. Он — источник истины об архитектуре.
> AI-агенты пишут код, люди принимают архитектурные решения.

## Контекст проекта

Опора — российская open-source PIM/MDM/DAM платформа. Аналог Pimcore без
vendor lock-in и токсичных зависимостей. CE под AGPLv3 в `opora/opora`,
Enterprise — в `opora/opora-enterprise` (приватный).
Горизонт: PoC август 2026, MVP CE 1.0 октябрь 2026 (синхронно с Akeneo CE EOL).

## Стек

- **Backend:** PHP 8.4, Yii3, Cycle ORM 2, PostgreSQL 16 (pg_trgm, ltree, jsonb, pgvector)
- **Queue:** yiisoft/queue + PostgreSQL-транспорт (очереди: inheritance_update, embeddings, thumbnail)
- **Storage:** Flysystem v3 (Local / S3)
- **BPM:** symfony/workflow ^7
- **MCP:** modelcontextprotocol/php-sdk
- **Embeddings:** GigaChat / YandexGPT / e5-multilingual / BGE-m3 (интерфейс EmbeddingProviderInterface)
- **Frontend:** React 18, TypeScript 5, TanStack Router/Query/Table/Virtual, Radix UI + shadcn/ui, Tailwind CSS 4
- **Тесты:** PHPUnit 11 + Pest 3 (DSL), Behat (acceptance), Vitest + Playwright (frontend)
- **Качество:** PHPStan level 8, Rector 2.x, PHP CS Fixer

## Структура монорепо

```
opora/                          ← корень монорепо
├── src/
│   ├── App/                    ← Application layer: Commands, Queries, Handlers, DTOs
│   ├── Domain/                 ← Domain layer: Entities, Value Objects, Events (NO framework deps)
│   └── Infrastructure/         ← Cycle mappers, repositories, adapters
├── packages/                   ← внутренние PHP-пакеты (выделяются по мере роста)
├── apps/
│   └── admin/                  ← React SPA
├── frontend/                   ← npm-пакеты (НЕ packages/)
│   ├── design-tokens/          ← @opora/design-tokens
│   ├── ui-primitives/          ← @opora/ui-primitives
│   ├── ui/                     ← @opora/ui
│   ├── composites/             ← @opora/composites
│   └── api-client/             ← @opora/api-client (генерируется из OpenAPI)
├── config/
│   ├── schema/classes/         ← Schema-as-Code: ClassDefinition PHP-файлы
│   ├── schema/workflows/       ← WorkflowDefinition
│   └── schema/permissions/     ← PermissionDefinition
├── docs/
│   └── architecture/decisions/ ← ADR-000 … ADR-NNN
├── CLAUDE.md                   ← этот файл
└── compose.yaml
```

## Архитектурные инварианты (нарушение = блок merge)

### 1. Schema-as-Code
Все определения классов, workflow, permissions — в `config/schema/*` под git.
Runtime-изменения на prod запрещены. `schema:sync` применяет изменения из git.
`OPORA_SCHEMA_WRITABLE=true` только на dev. Атрибут `inheritable: true` активирует
механизм наследования значений через дерево объектов.

### 2. Read Model — три таблицы, два пути записи
- `obj_object_values` — собственные значения (NULL если не задано)
- `obj_object_read` — JSONB-снимок с resolved-значениями (своё ИЛИ унаследованное)
- `obj_object_index` — плоские resolved-значения + `is_inherited BOOL` для фильтров

**Прямая запись:** Command → Handler → все три таблицы в ОДНОЙ транзакции синхронно (is_inherited=false).
**Изменение inheritable у родителя:** три таблицы родителя синхронно → очередь
`InheritanceUpdateJob` → асинхронно все потомки (is_inherited=true).
Расхождение между таблицами после завершения джоба = критический баг.

### 3. OpenAPI — единственный источник истины API
Фронт не делает HTTP-запросов вручную. Всё через `@opora/api-client` (генерируется из spec).
Drift между spec и кодом в CI = блок merge. Добавил endpoint — обнови spec первым.

### 4. Два уровня API
- **System API** (`/api/v1/*`) — `AuthProviderInterface`, полный доступ
- **Gateway API** (`/gw/{slug}/*`) — `GatewayAuthProviderInterface` per-шлюз, уважает `is_published`

Не смешивать middleware между уровнями. Не добавлять бизнес-логику в контроллеры.

### 5. AGPLv3 — священная корова
Любая зависимость с GPL-несовместимой лицензией = блок merge.
`composer licenses` проверяется в CI при каждом PR. Перед предложением библиотеки — проверь лицензию.

### 6. Clean Architecture в Domain/
`src/Domain/` — чистый PHP, zero зависимостей от Yii, Cycle ORM, любых фреймворков.
Только PSR-интерфейсы и PHP 8.4. Нарушение = блок merge.

### 7. Folders — только UI-навигация
`folder_id` в таблицах внешних модулей — исключительно для навигации в UI.
Бизнес-логика, основанная на `folder_id`, запрещена.

### 8. Table prefix convention по модулям
Все таблицы БД используют префикс, соответствующий модулю:
- `opora_` — core, schema-as-code, identity (`opora_users`, `opora_folders`, `opora_schema_compiled`)
- `obj_` — catalog / read model (`obj_objects`, `obj_object_values`, `obj_object_read`, `obj_object_index`)
- `search_` — search (`search_object_embeddings`, `search_index_queue`)
- `api_` — api-rest (`api_idempotency_log`)
- `io_` — io/import-export (`io_import_jobs`, `io_import_errors`)
- `ext_` — external modules (marketplace)

### 9. Module lifecycle contract
Каждый модуль может реализовать [`ModuleInstallerInterface`](docs/architecture/decisions/ADR-005-module-lifecycle-contract.md) —
контракт установки/обновления. Состав модулей декларируется в
[`config/opora-modules.php`](config/opora-modules.php). Версия модуля —
только в `composer.json`/`composer.lock`. Миграции трекаются в единой таблице
`opora_migration`. Единственный обязательный модуль — `opora/core`.
Подробнее: ADR-005.

Inline SQL, FK-ссылки, репозитории используют полное префиксированное имя.
Новый модуль получает новый префикс. Нарушение = блок merge.

## Стандарты тестирования

### Стратификация

Разные слои архитектуры тестируются разными подходами (ADR-006):

| Слой | Подход | Тип теста | Порядок |
|------|--------|-----------|---------|
| `src/Domain/` | Строгий TDD | Unit, без моков на конкретные классы | Тест → реализация |
| `src/App/` | Specification-first | Unit, моки на интерфейсы | AI пишет тест → подтверждение → реализация |
| `src/Infrastructure/` | Implementation-first | Integration, реальная тестовая БД | Реализация → интеграционный тест |

### Расположение тестов

Тесты лежат рядом с кодом зеркально структуре `src/`:

```
packages/core/src/Folder/FolderService.php
packages/core/tests/Unit/Folder/FolderServiceTest.php

packages/core/src/Module/ModuleRegistry.php
packages/core/tests/Unit/Module/ModuleRegistryTest.php
```

### Обязательный охват для каждого среза

1. Все **инварианты из спеки** — каждый инвариант покрыт тестом
2. Все **граничные случаи** (slug конфликт, превышение глубины, disabled пользователь)
3. **Happy path** — основной сценарий использования

### Именование тестов

Формат: `test_[что_тестируем]_[при_каком_условии]_[ожидаемый_результат]`

```php
// ✅ Правильно
public function test_create_folder_throws_when_slug_conflicts(): void
public function test_config_raises_exception_when_debug_enabled_in_production(): void

// ❌ Неправильно
public function testCreate(): void
public function test1(): void
```

### Запрещено в тестах

- `@runInSeparateProcess` без явной причины
- Тесты с `sleep()` или `usleep()`
- Прямые SQL-запросы в unit-тестах (только в integration)
- Моки на конкретные классы — только на интерфейсы
- `@covers` аннотации без реального кода
- Магические числа без пояснения

### Запуск

```bash
make test              # все тесты
make test-unit         # только unit
make test-integration  # только integration
make test-filter=ИмяТеста  # один тестовый класс
make ci                # cs-check + stan + rector-check + test
make stan              # PHPStan level 8
```

---

## Чего НЕ делать

- НЕ импортировать Yii/Cycle в `src/Domain/`
- НЕ читать данные через `object_values` в API-ответах — только через `object_read`
- НЕ изменять `config/schema/*` напрямую на prod без `schema:sync`
- НЕ добавлять бизнес-логику в HTTP-контроллеры — только в `App/` handlers
- НЕ использовать `array` без type hints в публичных API
- НЕ создавать новые пакеты без ADR
- НЕ смешивать System API и Gateway API middleware

## Команды

```bash
make up           # docker compose up -d
make install      # composer install + pnpm install
make migrate      # применить миграции БД
make schema-sync  # применить Schema-as-Code изменения
make test         # все тесты (Unit + Integration)
make stan         # PHPStan level 8
make cs           # PHP CS Fixer
make dev          # frontend dev server (localhost:5173)
make storybook    # Storybook (localhost:6006)
make up-tools     # pgAdmin (5050) + Mailpit (8025)
```

## ADR-каталог (читай перед архитектурными изменениями)

- ADR-000: `docs/architecture/decisions/ADR-000-use-adrs.md`
- ADR-001: `docs/architecture/decisions/ADR-001-choose-yii3.md`
- ADR-002: `docs/architecture/decisions/ADR-002-choose-cycle-orm2.md`
- ADR-003: `docs/architecture/decisions/ADR-003-repository-structure.md`
- ADR-004: `docs/architecture/decisions/ADR-004-table-prefix-and-migration-convention.md`
- ADR-005: `docs/architecture/decisions/ADR-005-module-lifecycle-contract.md`
- ADR-006: `docs/architecture/decisions/ADR-006-testing-strategy.md`

При любом архитектурном изменении — предложи создать новый ADR.
PR без ADR при архитектурном изменении отклоняется на ревью.

## Дополнительный контекст

Полное руководство по разработке: `docs/DEVELOPMENT_GUIDE.md`
