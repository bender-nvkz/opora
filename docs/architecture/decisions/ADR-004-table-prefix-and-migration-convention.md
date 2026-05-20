# ADR-004: Table prefix and per-package migration convention

**Date:** 2026-05-20
**Status:** Accepted
**Deciders:** Core team

## Context

По мере роста количества модулей Опоры (core, identity, schema-as-code, catalog,
search, api-rest, io) возникла необходимость в стандартизации:

1. **Именование таблиц.** Без префиксов разные модули могут случайно создать таблицы
   с одинаковыми именами. Например, и `catalog`, и `search` могут захотеть таблицу
   `index_queue`. Это приводит к коллизиям в shared PostgreSQL namespace.

2. **Миграции.** Каждый модуль должен быть self-contained — уметь накатывать и откатывать
   свои миграции независимо от других модулей. Централизованная директория миграций не
   масштабируется на N модулей.

3. **Install flow.** При первой установке платформы (`bin/opora install`) модули должны
   устанавливаться в строго определённом порядке из-за FK-зависимостей между их таблицами.

4. **Язык миграций.** Возможность выполнять dry-run и сохранять портабельность между
   Postgres-совместимыми СУБД (supabase, serverless, managed) требует чистого SQL,
   не завязанного на конкретный ORM.

Текущее архитектурное исследование ([`research.md`](../../.local/tech_reports/Архитектурное%20исследование:%20SQL-схема,%20миграции%20и%20install-flow%20для%20opora-core.md))
подтвердило необходимость этих конвенций.

## Decision

### 1. Table prefix convention

Каждая группа модулей использует фиксированный префикс для всех своих таблиц:

| Префикс | Модули | Примеры |
|---------|--------|---------|
| `opora_` | core, schema-as-code, identity | `opora_users`, `opora_folders`, `opora_schema_compiled`, `opora_rate_limit_buckets` |
| `obj_` | catalog (object/read model) | `obj_objects`, `obj_object_values`, `obj_object_read`, `obj_object_index` |
| `search_` | search | `search_object_embeddings`, `search_index_queue` |
| `api_` | api-rest | `api_idempotency_log` |
| `io_` | io (import/export) | `io_import_jobs`, `io_import_errors`, `io_import_job_objects` |

**Правила:**
- Все DDL в миграциях модуля используют только префиксированные имена
- Inline SQL-запросы (в репозиториях, сервисах) также используют префиксы
- Read Model таблицы (`obj_object_values`, `obj_object_read`, `obj_object_index`)
  упоминаются в коде с префиксом `obj_`, несмотря на то что они часть модуля `catalog`
- FK-ссылки на таблицы другого модуля всегда используют полное префиксированное имя
  (например, `REFERENCES obj_objects(id)` из `io_import_job_objects`)

### 2. Per-package migration convention

Каждый модуль хранит свои миграции в собственной директории `migrations/` внутри пакета:

```
packages/{module}/
└── migrations/
    ├── 001_create_{table_name}.sql
    ├── 001_create_{table_name}.php
    ├── 002_...
    └── ...
```

**Регистрация** — через секцию `extra.cycle.migrations` в `composer.json` пакета:

```json
{
    "name": "opora/{module}",
    "extra": {
        "cycle": {
            "migrations": {
                "path": "migrations/",
                "namespace": "Opora\\{Module}\\Migration"
            }
        }
    }
}
```

**Трекинг** — через `yiisoft/definitions` в `config/params.php` модуля:

```php
return [
    'cycle.migrations' => [
        '{module}' => [
            'name' => '{module}',
            'group' => 'opora',
        ],
    ],
];
```

**Правила:**
- Два файла на миграцию: `.sql` (чистый SQL) и `.php` (класс с `@dry-run` PHPDoc)
- SQL-файл содержит `{dry}` плейсхолдер для dry-run режима
- Миграция считается executed только после успешного выполнения `.php` + `.sql`
- Откат миграции — отдельный `.sql` с суффиксом `_down` или штатный `cycle/migrations revert`

### 3. Install pipeline

`bin/opora install` выполняет идемпотентную установку всех модулей
в фиксированном порядке (позиция = порядок выполнения):

| Позиция | Модуль | Зависимости (FK) |
|---------|--------|------------------|
| 1 | `opora/core` | — |
| 2 | `opora/schema-as-code` | `opora/core` |
| 3 | `opora/identity` | `opora/core` |
| 4 | `opora/catalog` | `opora/core`, `opora/identity` |
| 5 | `opora/search` | `opora/catalog` |
| 6 | `opora/api-rest` | `opora/identity`, `opora/search` |
| 7 | `opora/io` | `opora/catalog` |

**Правила:**
- Позиция строго возрастает, новые модули получают следующую свободную позицию
- Каждая позиция выполняет: `schema:migrate --group={module}` + опционально `schema:sync`
- Повторный запуск идемпотентен — `cycle/migrations` пропускает уже выполненные миграции
- Ни одна позиция не требует интерактивного ввода

### 4. Raw SQL-first для миграций

Миграции пишутся на чистом SQL (raw), без fluent builder Cycle ORM.

**Обоснование:**
- Dry-run: SQL c `{dry}` проще, чем эмулировать dry-run через fluent builder
- Портабельность: SQL не завязан на конкретную версию Cycle ORM
- Прозрачность: DDL на SQL читается любым DBA без знания PHP
- CI/CD: SQL-миграции можно прогонять через внешние инструменты (golang-migrate, Atlas)

**Формат:**

```sql
-- 001_create_opora_users.sql
CREATE TABLE "opora_users" (
    "id" BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    "email" VARCHAR(255) NOT NULL,
    "password_hash" VARCHAR(255) NOT NULL,
    "is_active" BOOLEAN NOT NULL DEFAULT true,
    "created_at" TIMESTAMPTZ NOT NULL DEFAULT now(),
    "updated_at" TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- {dry} плейсхолдер для dry-run:
-- {dry} возвращает SQL без выполнения, {wet} выполняет реально
```

**Запрещено:** `SchemaBuilder::table()`, `Column::bigPrimary()`, и другие fluent API Cycle ORM в миграциях.

## Consequences

**Положительные:**
- Ноль коллизий имён таблиц между модулями (пространство имён через префикс)
- Self-contained модули: миграции живут рядом с кодом, не в общем реестре
- Предиктивный `install` — порядок установки явный и документированный
- SQL-миграции читаются DBA и инструментами без PHP
- Dry-run работает без эмуляции, за счёт `{dry}` плейсхолдера в SQL

**Отрицательные:**
- Длина имён таблиц увеличивается (например, `io_import_job_objects` — 22 символа)
- При переименовании модуля нужно менять префикс во всех миграциях (крайне редко)
- Raw SQL не использует type-safe API Cycle ORM (меньше автокомплита в IDE)
- `{dry}` плейсхолдер — самодельный механизм, не стандарт Cycle ORM

## Alternatives Considered

1. **Без префиксов, уникальные имена через суффиксы:** Отклонено. Суффиксы не
   группируют таблицы по модулям, сложнее понять принадлежность таблицы в БД.

2. **PostgreSQL schema (`catalog.object_values`, `search.index_queue`):** Отклонено.
   Cycle ORM 2 имеет ограниченную поддержку PG schema. Усложняет подключение к БД
   через `search_path`. Некоторые managed PostgreSQL (Supabase) ограничивают количество схем.

3. **Fluent builder Cycle ORM для миграций:** Отклонено. Не поддерживает dry-run
   на уровне SQL. Привязывает миграции к конкретной версии Cycle. Нечитаем для DBA.
