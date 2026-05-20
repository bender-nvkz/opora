# ADR-002: Выбор Cycle ORM 2 как ORM-слоя

**Date:** 2026-05-01
**Status:** Accepted
**Deciders:** Core team

## Context

PIM-платформа работает с двумя типами данных:
1. **Статические сущности** с фиксированной схемой (Users, Assets, Connectors).
2. **Динамические PIM-объекты**, где схема определяется пользователем в runtime через Schema-as-Code.

Нам нужен ORM, который:
- Поддерживает DataMapper (не Active Record) для hexagonal architecture.
- Умеет строить схему динамически из PHP-конфигурации (не только из аннотаций).
- Имеет MIT-лицензию.
- Активно поддерживается, совместим с PHP 8.4.
- Нативно интегрируется с Yii3.

Cycle ORM 2 стабилен: `cycle/orm`, `cycle/database`, `cycle/migrations`, `cycle/schema-builder`,
`cycle/entity-behavior` — все MIT. Команда Spiral Scout (исторически РФ-корни).
Поддержка PHP 8.4, PostgreSQL 16, long-running processes (RoadRunner/Swoole).

## Decision

Использовать **Cycle ORM 2** в следующей конфигурации:

**Для статических сущностей** (User, Asset, Connector, AuditLog и др.):
- `cycle/annotated` — PHP-атрибуты (`#[Entity]`, `#[Column]`) на сущностях.
- Схема генерируется и кэшируется на старте.

**Для динамических PIM-объектов** (object_values, object_read, object_index):
- Cycle ORM НЕ используется — прямой QueryBuilder через `yiisoft/db-pgsql`.
- EAV-таблицы с JSONB, GIN-индексы, ltree для категорий.

**Schema Provider** для динамических классов:
- `cycle/schema-builder` с PHP Schema Provider, читающим `schema_compiled` из БД.
- Кэширование на файловой системе через `yiisoft/cache-file`.
- Инвалидация кэша по событию `SchemaSynced` после `schema:sync`.

## Consequences

**Положительные:**
- DataMapper обеспечивает чистое hexagonal разделение.
- Dynamic Schema Provider позволяет описывать PIM-классы в PHP под git.
- MIT лицензия, совместима с AGPLv3.
- Нативная интеграция с Yii3 через `yiisoft/yii-cycle`.

**Отрицательные:**
- Меньше документации и сообщества, чем у Doctrine.
- Гибридный подход (Cycle + raw SQL) требует дисциплины.
- Необходимо поддерживать два пути записи (см. ADR о Read Model).

## Alternatives Considered

1. **Doctrine ORM:** Отклонено. 150+ транзитивных зависимостей, плотная связка с Symfony,
   используется конкурентами (Pimcore, Akeneo) — антиотстройка. Dynamic mapping сложнее.

2. **Yii Active Record:** Отклонено. Active Record несовместим с hexagonal architecture,
   усложняет тестирование, подходит только для простых CRUD-сценариев.

3. **Чистый QueryBuilder (без ORM):** Отклонено. Для статических сущностей ORM даёт
   значительную экономию времени. Гибрид — лучший компромисс.
