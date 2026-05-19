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
- `object_values` — собственные значения (NULL если не задано)
- `object_read` — JSONB-снимок с resolved-значениями (своё ИЛИ унаследованное)
- `object_index` — плоские resolved-значения + `is_inherited BOOL` для фильтров

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

При любом архитектурном изменении — предложи создать новый ADR.
PR без ADR при архитектурном изменении отклоняется на ревью.

## Дополнительный контекст

Полное руководство по разработке: `docs/DEVELOPMENT_GUIDE.md`