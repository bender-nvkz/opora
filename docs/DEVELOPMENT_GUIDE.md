# Opora Development Guide

> **Статус:** черновик. Разделы помеченные ⏳ заполняются по мере реализации соответствующих
> спецификаций. Актуальная информация всегда в первоисточниках: [`CLAUDE.md`](CLAUDE.md),
> [ADRs](docs/architecture/decisions/).

---

## Architecture Overview

> ⏳ Раздел будет заполнен после завершения спеки `1_1-opora-core`.

Опора — российская open-source PIM/MDM/DAM платформа. Аналог Pimcore без vendor lock-in,
с честной лицензией AGPLv3, нативным MCP-сервером для AI-агентов и минимальными
инфраструктурными требованиями — достаточно PostgreSQL 16.

**Ключевые слои архитектуры:**

| Слой | Назначение | Зависимости | Тестирование |
|------|-----------|-------------|--------------|
| `src/Domain/` | Чистая бизнес-логика, Entity, Value Object, Event | Нет (чистый PHP) | Строгий TDD |
| `src/App/` | Use Cases: Command, Query, Handler, DTO | Только интерфейсы (PSR) | Specification-first |
| `src/Infrastructure/` | ORM, HTTP, Console, адаптеры | Всё (Cycle, Yii, клиенты) | Implementation-first |

Подробнее:
- [CLAUDE.md](CLAUDE.md) — архитектурные инварианты, стек, инженерные принципы
- [README.md](README.md) — общее описание платформы, возможности, сравнение с конкурентами

---

## Module Structure

> ⏳ Раздел будет заполнен после завершения спеки `1_1-opora-core`.

Монорепо организовано как:

```
opora/
├── src/                    ← Application слои (App / Domain / Infrastructure)
├── packages/               ← Внутренние PHP-пакеты (opora/core, opora/identity, …)
├── apps/                   ← SPA-приложения (admin)
├── frontend/               ← npm-пакеты (@opora/*)
├── config/                 ← Глобальная конфигурация (DI, schema, routes)
├── docs/                   ← Документация, ADR
├── tests/                  ← Интеграционные / функциональные тесты
└── bin/opora               ← Console entrypoint
```

Каждый модуль следует lifecycle-контракту ([`ModuleInstallerInterface`](packages/core/src/Module/ModuleInstallerInterface.php))
и использует префикс таблиц БД согласно ADR-004.

Актуальные декларации модулей: [`config/opora-modules.php`](config/opora-modules.php).

---

## Getting Started

### Требования

- Docker & Docker Compose (единственный поддерживаемый способ запуска)
- pnpm (для frontend-зависимостей)

### Быстрый старт

```bash
# 1. Клонировать репозиторий
git clone git@github.com:opora/opora.git
cd opora

# 2. Настроить окружение
cp .env.example .env
# Отредактировать .env при необходимости (APP_SECRET, DB_*)

# 3. Поднять сервисы
make up

# 4. Установить PHP-зависимости
make install-php

# 5. Установить frontend-зависимости
make install-frontend

# 6. Применить миграции
make migrate

# 7. Проверить — открыть http://localhost:8080
```

### Доступные команды

См. полный список: `make help`.

**Окружение:**

| Команда | Что делает |
|---------|-----------|
| `make up` | Поднять сервисы (php-fpm, nginx, postgres) |
| `make down` | Остановить (данные сохраняются) |
| `make down-volumes` | Остановить и удалить volumes |
| `make shell` | Открыть bash в php-fpm контейнере |
| `make db-shell` | Открыть psql |

**Зависимости:**

| Команда | Что делает |
|---------|-----------|
| `make install` | Установить все зависимости |
| `make install-php` | composer install |
| `make install-frontend` | pnpm install --frozen-lockfile |

**База данных:**

| Команда | Что делает |
|---------|-----------|
| `make migrate` | Применить миграции |
| `make migrate-down` | Откатить последнюю миграцию |
| `make fresh-db` | Пересоздать БД с нуля |

**Тесты и качество:**

| Команда | Что делает |
|---------|-----------|
| `make test` | Все тесты (PHPUnit) |
| `make test-unit` | Unit-тесты (быстро, без БД) |
| `make test-integration` | Integration-тесты (нужна БД) |
| `make stan` | PHPStan level 8 |
| `make cs` | PHP CS Fixer (fix) |
| `make cs-check` | PHP CS Fixer (check) |
| `make rector` | Rector (apply) |
| `make rector-check` | Rector (dry-run) |
| `make ci` | cs-check + stan + rector-check + test |

**Frontend:**

| Команда | Что делает |
|---------|-----------|
| `make dev` | Dev-сервер (Vite) |
| `make build-frontend` | Production сборка |
| `make lint-frontend` | Biome lint |
| `make storybook` | Storybook |

---

## Development Workflow

### Ветки

```
main                          ← защищённая, только через PR
feat/<ticket-id>-<slug>       ← новая функциональность
fix/<ticket-id>-<slug>        ← исправление багов
docs/<slug>                   ← документация
chore/<slug>                  ← инфраструктура, зависимости
refactor/<slug>               ← рефакторинг без изменения поведения
```

### Conventional Commits

```
<type>(<scope>): <description>
```

**Типы:** `feat` / `fix` / `docs` / `chore` / `refactor` / `test`

**Скоупы:** `core` / `identity` / `catalog` / `schema` / `read-model` / `dam` / `workflow` / `search` /
`api` / `gw` / `queue` / `frontend` / `ui` / `ci` / `adr` / `docker`

Примеры:

```
feat(catalog): add object inheritance resolution for read model
fix(schema): validate attribute_code uniqueness within class
docs(adr): add ADR-004 for embedding provider selection
```

Полный список скоупов — в [`CLAUDE.md`](CLAUDE.md#conventional-commits--%D1%84%D0%BE%D1%80%D0%BC%D0%B0%D1%82).

### Quality Gates (CI)

Каждый PR проходит:

1. **PHP CS Fixer** — проверка code style (`make cs-check`)
2. **PHPStan level 8** — статический анализ (`make stan`)
3. **Rector** — проверка рефакторингов (`make rector-check`)
4. **PHPUnit** — все тесты (`make test`)
5. **License check** — совместимость с AGPLv3 (`make license-check`)

Локальный прогон: `make ci`

---

## Testing Strategy

Опора использует **стратифицированный TDD** ([ADR-006](docs/architecture/decisions/ADR-006-testing-strategy.md))
— разные подходы для разных слоёв архитектуры:

| Слой | Подход | Тип теста | Порядок |
|------|--------|-----------|---------|
| `src/Domain/` | Строгий TDD | Unit, без моков на конкретные классы | Тест → реализация |
| `src/App/` | Specification-first | Unit, моки на интерфейсы | AI пишет тест → подтверждение → реализация |
| `src/Infrastructure/` | Implementation-first | Integration, реальная тестовая БД | Реализация → интеграционный тест |

### Расположение тестов

Тесты лежат рядом с кодом зеркально структуре `src/`:

```
packages/core/src/Module/ModuleRegistry.php
packages/core/tests/Unit/Module/ModuleRegistryTest.php
```

### Именование тестов

```
test_[что_тестируем]_[при_каком_условии]_[ожидаемый_результат]
```

Пример:

```php
public function test_create_folder_throws_when_slug_conflicts(): void
```

### Запрещено

- `@runInSeparateProcess` без явной причины
- Тесты с `sleep()` или `usleep()`
- Прямые SQL-запросы в unit-тестах (только в integration)
- Моки на конкретные классы — только на интерфейсы
- Создание класса в `src/Domain/` без существующего теста

Подробнее:
- [ADR-006: Стратегия тестирования](docs/architecture/decisions/ADR-006-testing-strategy.md)
- [CLAUDE.md → Стандарты тестирования](CLAUDE.md#%D1%81%D1%82%D0%B0%D0%BD%D0%B4%D0%B0%D1%80%D1%82%D1%8B-%D1%82%D0%B5%D1%81%D1%82%D0%B8%D1%80%D0%BE%D0%B2%D0%B0%D0%BD%D0%B8%D1%8F)

### Known PHPStan limitations

PHPStan level 8 требует два ignore-правила для тестов. Они задокументированы здесь,
чтобы не накапливаться без объяснения:

#### 1. `Dynamic call to static method PHPUnit\Framework\Assert::#`

```neon
# phpstan.neon:19-21
- message: '#Dynamic call to static method PHPUnit\\Framework\\Assert::#'
  paths:
      - packages/*/tests/*
```

**Причина:** PHP-код вызывает `self::assert*()`, `static::assert*()` и `$this->assert*()`.
PHPStan level 8 требует чтобы статические методы вызывались статически. PHPUnit runtime
поддерживает все три варианта, и `$this->assert*()` — идиоматический стиль.
PHPStan-phpunit extension также не подавляет эти вызовы на level 8.

**Условие удаления:** `phpstan-phpunit` extension начнёт корректно обрабатывать
`$this->assert*()` на level 8, или проект перейдёт на `self::assert*()` везде.

#### 2. `@disregard` для `createMock()` с `DatabaseInterface`

```php
# packages/core/tests/Unit/Command/InstallCommandUnitTest.php:58
/** @disregard PHPStan false-positive — psalm bug in phpstan-phpunit */
$this->database = $this->createMock(DatabaseInterface::class);
```

**Причина:** `phpstan-phpunit` ошибочно требует psalm-совместимый тип для аргумента
`createMock()`, хотя метод принимает `string`. Баг на стыке phpstan-phpunit и psalm
type inference.

**Условие удаления:** Исправится в `phpstan/phpstan-phpunit` (требует мониторинга апстрима).

> **Политика:** Если добавляешь новый ignore-rule в `phpstan.neon` —
> добавь запись в этот список с причиной и условием удаления.
> Если правило больше не актуально — удали и из конфига, и из этого списка.

---

## Yii3 DI: tagged dependencies

Для регистрации middleware (и любых других коллекций сервисов с порядком) используется
механизм тегов Yii3 DI:

### Регистрация сервиса с тегом

```php
// config/web/middleware.php
use Yiisoft\Definitions\Reference;

return [
    MyMiddleware::class => [
        'class' => MyMiddleware::class,
        '__construct()' => [/* ... */],
        'tags' => ['opora.middleware'],
    ],
];
```

### Получение всех сервисов с тегом

```php
// config/web/container.php
use Yiisoft\Di\Reference\TagReference;

return [
    MyPipeline::class => [
        'class' => MyPipeline::class,
        '__construct()' => [
            'items' => TagReference::to('opora.middleware'),
            // Внутри создаёт Reference::to('tag@opora.middleware')
        ],
    ],
];
```

### Важно

- **Используй `TagReference::to('tag-name')`** — это правильный API.
- **НЕ используй `Reference::tagged()`** — метод отсутствует в `yiisoft/definitions` v3.4.
- `TagReference::to()` возвращает `Reference`, который DI-контейнер разрешает
  в `iterable` всех сервисов с указанным тегом.
- Тип параметра конструктора — `iterable`, не `array`, чтобы контейнер мог
  передать lazy iterator без материализации всех сервисов сразу.

---

## ADR Index

| ID | Название | Статус | Кратко |
|----|---------|--------|--------|
| [ADR-000](docs/architecture/decisions/ADR-000-use-adrs.md) | Use ADRs | Accepted | Фиксировать архитектурные решения в ADR |
| [ADR-001](docs/architecture/decisions/ADR-001-choose-yii3.md) | Choose Yii3 | Accepted | Yii3 как фреймворк (DI, Router, Config) |
| [ADR-002](docs/architecture/decisions/ADR-002-choose-cycle-orm2.md) | Choose Cycle ORM 2 | Accepted | Cycle ORM 2 как ORM (DataMapper, не AR) |
| [ADR-003](docs/architecture/decisions/ADR-003-repository-structure.md) | Repository Structure | Accepted | Структура репозиториев в монорепо |
| [ADR-004](docs/architecture/decisions/ADR-004-table-prefix-and-migration-convention.md) | Table Prefix & Migration Convention | Accepted | Префиксы таблиц по модулям, конвенция миграций |
| [ADR-005](docs/architecture/decisions/ADR-005-module-lifecycle-contract.md) | Module Lifecycle Contract | Accepted | ModuleInstallerInterface, pipeline установки |
| [ADR-006](docs/architecture/decisions/ADR-006-testing-strategy.md) | Testing Strategy | Accepted | Стратифицированный TDD |

---

## Module Development Guide

> ⏳ Раздел будет заполнен после завершения специй `2-opora-schema-as-code`
> и `3-opora-catalog-read-model`.

Содержит:

- Инструкцию по созданию нового модуля (интерфейсы, конфиги, миграции)
- Паттерны маппинга Cycle ORM 2
- Работу с Read Model
- Schema-as-Code
- OpenAPI-first разработку

---

*Актуализировано: май 2026*
