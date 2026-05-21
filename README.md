![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php)
![Yii3](https://img.shields.io/badge/Yii3-framework-40B3AC)
![Cycle ORM 2](https://img.shields.io/badge/Cycle%20ORM-2-39B54A)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql)
![React](https://img.shields.io/badge/React-18-61DAFB?logo=react)
![TypeScript](https://img.shields.io/badge/TypeScript-5-3178C6?logo=typescript)
![License](https://img.shields.io/badge/License-AGPLv3-blue)

---

# Опора

**Российская open-source PIM/MDM/DAM платформа** — единое цифровое ядро для управления продуктовыми данными, активами и их публикацией в любые каналы продаж.

Аналог Pimcore без vendor lock-in, с честной лицензией AGPLv3, нативным MCP-сервером для AI-агентов и минимальными инфраструктурными требованиями — достаточно PostgreSQL 16.

> 🎯 **Горизонты:** PoC — август 2026 · MVP CE 1.0 — октябрь 2026 · Enterprise — Q1–Q2 2027

---

## Сравнение с конкурентами

| | Опора | Pimcore | Akeneo CE | Compo |
|---|---|---|---|---|
| **Лицензия** | AGPLv3 + commercial | POCL (source-available) | AGPLv3 (заморожен) | Проприетарная |
| **База данных** | PostgreSQL only | MySQL + Elasticsearch | MySQL + Elasticsearch | Java + Kafka + ES |
| **MCP-сервер** | ✅ из коробки | ❌ | ❌ | ❌ |
| **Юрисдикция** | РФ | Австрия | Франция | РФ |
| **On-premise без внешних сервисов** | ✅ | ❌ | ❌ | ❌ |
| **Реестр Минцифры (план)** | ✅ | ❌ | ❌ | ✅ |
| **AI-first архитектура** | ✅ | ❌ | ❌ | ❌ |

---

## Возможности

### Управление данными

- **Гибкая модель данных** — описываете структуру данных через UI-редактор или напрямую в коде (Schema-as-Code), описания хранятся в git, применяются командой `schema:sync`
- **Типы атрибутов:** text, richtext, number, money, boolean, date, select, multiselect, asset, relation, geo
- **Иерархические классы** — любой класс может быть иерархическим с `parent_id` и `ltree`. Столько деревьев, сколько нужно: ProductCategory, Brand, Region
- **Варианты (OBJECT/VARIANT)** — класс с `#[SupportsVariants]` поддерживает варианты. Типичный сценарий: футболка (OBJECT) + размеры/цвета (VARIANT)
- **Наследование значений** — `inheritable: true` на атрибуте: потомок наследует значение родителя, пока не переопределит
- **Локализация** — любое количество локалей, channel-specific значения
- **Публикация** — бинарный флаг `is_published`, независимый от workflow

### Digital Asset Management (DAM)

- Хранение файлов любых типов — локально или S3-совместимое хранилище
- Автоматическая генерация превью и thumbnails для изображений
- Привязка ассетов к объектам каталога

### Производительность

- **Двухуровневая Read Model:** быстрый GET карточки (один SELECT, JSONB), быстрые листинги с фильтрацией (плоский индекс по атрибутам)
- **Поиск:** pg_trgm + tsvector из коробки; Meilisearch — опционально
- **Целевые показатели:** GET карточки p95 < 50 мс, листинг с фильтрами p95 < 200 мс при 100k объектов

### Workflow и качество данных

- BPM-состояния объектов (черновик → на согласовании → опубликован)
- Audit log каждого изменения с указанием инициатора (пользователь / API / AI-агент)
- Версионирование объектов с возможностью отката

### API и интеграции

- **System API** (`/api/v1/`) — для UI и AI-агентов, JWT/API-key авторизация, полный RBAC
- **Gateway API** (`/gw/{slug}/`) — конфигурируемые шлюзы для внешних потребителей
- **OpenAPI 3.1** — спецификация, авто-генерируется из кода
- **MCP-сервер** — AI-агенты (Claude Desktop, Cursor, любой MCP-клиент) работают с данными напрямую
- Импорт/экспорт CSV и XML

### AI и машинное обучение

- **MCP-сервер** из коробки — AI-агенты создают, читают и редактируют объекты
- **Embeddings** — GigaChat, YandexGPT, e5-multilingual, BGE-M3 через единый `EmbeddingProviderInterface`
- AI-first архитектура с первого дня

---

## Архитектура

```
opora/
├── src/
│   ├── App/              ← Application layer: Commands, Queries, Handlers, DTOs
│   ├── Domain/           ← Domain layer: Entities, VOs, Events (чистый PHP, zero framework)
│   └── Infrastructure/   ← Cycle mappers, repositories, адаптеры
├── packages/             ← Внутренние PHP-пакеты
├── apps/
│   └── admin/            ← React SPA
├── frontend/             ← npm-пакеты (@opora/design-tokens, @opora/ui, @opora/api-client)
├── config/
│   ├── schema/classes/       ← ClassDefinition (PHP-файлы)
│   ├── schema/workflows/     ← WorkflowDefinition
│   └── schema/permissions/   ← PermissionDefinition
├── docs/
│   └── architecture/decisions/ ← ADR-000 … ADR-NNN
└── tests/
    ├── Unit/             ← Unit-тесты (Domain, App)
    ├── Integration/      ← Интеграционные тесты (Infrastructure)
    └── Functional/       ← Функциональные тесты (HTTP endpoints)
```

### Стек

| Компонент | Технология |
|---|---|
| **Backend** | PHP 8.4, Yii3, Cycle ORM 2 |
| **База данных** | PostgreSQL 16 (pg_trgm, ltree, jsonb, pgvector) |
| **Очереди** | yiisoft/queue + PostgreSQL-транспорт |
| **Хранилище** | Flysystem v3 (Local / S3) |
| **BPM** | symfony/workflow 7 |
| **Frontend** | React 18, TypeScript 5, TanStack Router/Query/Table/Virtual, Radix UI + shadcn/ui, Tailwind CSS 4 |
| **AI** | MCP Server (modelcontextprotocol/php-sdk), EmbeddingProviderInterface |
| **Тесты** | PHPUnit 11 + Pest 3, Behat, Vitest + Playwright |
| **Стратегия тестов** | Стратифицированный TDD — см. [ADR-006](docs/architecture/decisions/ADR-006-testing-strategy.md) |
| **Качество** | PHPStan level 8, Rector 2.x, PHP CS Fixer |

---

## Быстрый старт

```bash
# Поднять окружение
make up

# Установить зависимости (composer + pnpm)
make install

# Применить миграции БД
make migrate

# Применить Schema-as-Code
make schema-sync

# Проверить что всё работает
make test

# Запустить frontend dev server
make dev
```

**Требования:** Docker 26+, Docker Compose v2, Git, Node.js 22 LTS, pnpm 9.

---

## Архитектурные решения

Ключевые инварианты платформы зафиксированы в каталоге ADR:

- [ADR-000: Использование ADR](docs/architecture/decisions/ADR-000-use-adrs.md)
- [ADR-001: Выбор Yii3](docs/architecture/decisions/ADR-001-choose-yii3.md)
- [ADR-002: Выбор Cycle ORM 2](docs/architecture/decisions/ADR-002-choose-cycle-orm2.md)
- [ADR-003: Структура репозиториев](docs/architecture/decisions/ADR-003-repository-structure.md)
- [ADR-004: Префиксы таблиц и миграции](docs/architecture/decisions/ADR-004-table-prefix-and-migration-convention.md)
- [ADR-005: Module Lifecycle Contract](docs/architecture/decisions/ADR-005-module-lifecycle-contract.md)
- [ADR-006: Стратегия тестирования](docs/architecture/decisions/ADR-006-testing-strategy.md)

Полное руководство по разработке: [`docs/DEVELOPMENT_GUIDE.md`](docs/DEVELOPMENT_GUIDE.md).

---

## Лицензия

**Community Edition** — [AGPLv3](LICENSE). Свободно: используй, изучай, модифицируй, распространяй.

**Enterprise Edition** — коммерческая лицензия для организаций, которым требуется:
- SSO (OIDC / SAML2)
- Field-level и row-level permissions
- Data Quality scorecards
- Расширенные коннекторы (1С, Wildberries, Ozon, Bitrix24)
- Приоритетная поддержка

---

## Сообщество

- Репозиторий зеркалируется на GitLab для обеспечения доступности в РФ

---

<p align="center">
  <sub>Разрабатывается с ❤️ командой <a href="https://meiji.media">MEIJI MEDIA</a></sub>
</p>
