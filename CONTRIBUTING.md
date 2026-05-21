# Участие в разработке Опоры

Опора — российская open-source PIM/MDM/DAM платформа под AGPLv3.
Это руководство описывает как правильно вносить вклад в развитие проекта.

---

## Быстрый старт

```bash
git clone https://github.com/bender-nvkz/opora
cd opora
cp .env.example .env          # настрой переменные окружения
docker compose up -d          # поднять PostgreSQL, Redis, nginx, php-fpm
make install                  # composer install + pnpm install
make migrate                  # применить миграции БД
make test                     # убедиться что всё работает
```

Все сервисы после `make up`:
- Приложение: http://localhost:8080
- Frontend dev: http://localhost:5173 (после `make dev`)
- pgAdmin: http://localhost:5050 (после `make up-tools`)
- Mailpit: http://localhost:8025 (после `make up-tools`)
- Storybook: http://localhost:6006 (после `make storybook`)

---

## Требования

1. **CLA не требуется** для CE-вклада.
2. Весь код в этом репозитории — под AGPLv3.
3. Внешние зависимости — только AGPLv3-совместимые лицензии (MIT, Apache 2.0, BSD, LGPL).
   Проверяй перед добавлением: `composer licenses` / `pnpm licenses list`.
4. Перед началом работы над задачей — обсуди её в GitHub Issues.

---

## Процесс работы

### 1. Найди или создай задачу

- `good first issue` — задачи для новых участников
- `help wanted` — приоритетные задачи которые ждут контрибьютора
- Если хочешь сделать что-то своё — открой Issue и обсуди до начала работы

### 2. Создай ветку

```bash
git checkout main
git pull origin main
git checkout -b feat/42-add-object-search
```

**Формат имени ветки:** `<type>/<issue-id>-<slug>`

| Тип | Назначение |
|-----|-----------|
| `feat` | Новая функциональность |
| `fix` | Исправление бага |
| `docs` | Документация |
| `chore` | Инфраструктура, зависимости |
| `refactor` | Рефакторинг без изменения поведения |
| `test` | Тесты |

### 3. Пиши код

Читай [Стандарты кода](#стандарты-кода) ниже.

Архитектурные инварианты — в `CLAUDE.md` в корне репозитория.
Если меняешь архитектуру — нужен ADR (см. [ADR-процесс](#adr-процесс)).

### 4. Коммиты — Conventional Commits

**Формат:**
```
<type>(<scope>): <description>

[optional body — объясни ПОЧЕМУ, не ЧТО]

[optional footer: Closes #123, BREAKING CHANGE: ...]
```

**Типы:** `feat` / `fix` / `docs` / `style` / `refactor` / `test` / `chore` / `perf` / `ci` / `build`

**Скоупы Опоры** (использовать только эти):

| Скоуп | Область |
|-------|---------|
| `core` | Bootstrapping, DI, middleware, Folders |
| `identity` | Аутентификация, пользователи, токены |
| `catalog` | Объекты каталога, атрибуты, наследование |
| `schema` | Schema-as-Code, ClassDefinition, schema:sync |
| `read-model` | object_values, object_read, object_index |
| `dam` | Digital Asset Management |
| `workflow` | BPM, symfony/workflow, состояния объектов |
| `search` | Полнотекстовый и семантический поиск |
| `api` | System API (/api/v1/*) |
| `gw` | Gateway API (/gw/{slug}/*) |
| `mcp` | MCP-сервер для AI-агентов |
| `queue` | Очереди (InheritanceUpdateJob, EmbeddingsJob и др.) |
| `frontend` | React SPA (apps/admin) |
| `ui` | UI-пакеты (design-tokens, ui-primitives) |
| `ci` | CI/CD pipeline |
| `docker` | Docker, compose.yaml, Dockerfile |
| `adr` | Architecture Decision Records |
| `deps` | Обновление зависимостей |

**Примеры:**
```
feat(catalog): add object inheritance resolution for read model
fix(schema): validate attribute_code uniqueness within class
docs(adr): add ADR-004 for embedding provider selection
chore(ci): add rector check-only job to pipeline
refactor(identity): extract password hashing to dedicated service
feat(dam): implement flysystem s3 adapter for asset storage
fix(workflow): handle concurrent state transitions with advisory lock
test(catalog): add unit tests for InheritanceUpdateJob
perf(catalog): add GIN index on object_index for faster filtering
fix(queue): handle deadlock in InheritanceUpdateJob retry logic
```

### 5. Проверь перед открытием PR

```bash
make ci   # cs-check + stan + rector-check + test — должно быть всё зелёное
```

По отдельности:
```bash
make cs-check      # PHP CS Fixer — 0 нарушений
make stan          # PHPStan level 8 — 0 ошибок
make rector-check  # Rector — 0 предупреждений
make test          # PHPUnit — все тесты зелёные
pnpm typecheck     # TypeScript — 0 ошибок
pnpm lint          # Biome — 0 предупреждений
```

### 6. Открой Pull Request

**Шаблон описания:**
```markdown
## Что сделано
[Краткое описание изменений — 2-3 предложения]

## Мотивация
[Почему это нужно? Какую проблему решает?]

## Как проверить
1. [Конкретный шаг]
2. [Ещё шаг]
3. Ожидаемый результат: ...

## Связанные Issues
Closes #NNN

## ADR
[Ссылка на ADR если изменена архитектура, или "не требуется"]

## Чеклист
- [ ] `make ci` пройден без ошибок
- [ ] Новый код в `src/Domain/` — есть unit-тест, написанный до реализации
- [ ] Новый код в `src/App/` — есть тест, фиксирующий контракт
- [ ] Новый код в `src/Infrastructure/` — есть интеграционный тест
- [ ] Инварианты из спеки покрыты тестами
- [ ] PHPStan level 8 — 0 ошибок
- [ ] CLAUDE.md обновлён (если нужно)
- [ ] ADR создан (если архитектурное изменение)
- [ ] CHANGELOG.md обновлён
- [ ] Новые зависимости проверены на AGPLv3-совместимость
```

**Ревью:**
- Минимум 1 апрув от `@opora/core-team`
- Все CI-проверки зелёные
- Для архитектурных изменений — ADR обязателен

---

## Стандарты кода

### PHP

- PHP 8.4, `declare(strict_types=1)` в каждом файле
- PSR-12 + наши правила (`.php-cs-fixer.php`)
- PHPStan level 8 — 0 ошибок
- `readonly` классы/свойства везде где применимо
- Один класс — один файл, имя файла = PascalCase имя класса

**Архитектурные правила:**
- `src/Domain/` — чистый PHP без зависимостей на Yii/Cycle/любой фреймворк
- Бизнес-логика — только в `src/App/` handlers, никогда в контроллерах
- API-ответы — только через `object_read`, никогда через `object_values`
- Репозитории в Domain — только интерфейсы; реализации — в Infrastructure

### TDD — стратифицированный подход

Мы используем **стратифицированный TDD** (см. ADR-006). Разные слои архитектуры
тестируются разными подходами:

**Domain layer (`src/Domain/`):** строгий TDD. Тест пишется до кода.
Тест — это спецификация поведения, а не документация. Если ты не можешь
написать тест до кода — скорее всего интерфейс недостаточно продуман.

```php
// Сначала тест — фиксируем контракт
final class FolderServiceTest extends TestCase
{
    public function test_create_throws_when_slug_conflicts(): void
    {
        $repo = $this->createMock(FolderRepositoryInterface::class);
        $repo->method('findBySlugAndParent')->willReturn(new Folder(/* ... */));

        $service = new FolderService($repo, $this->createMock(EventBusInterface::class));

        $this->expectException(SlugConflictException::class);
        $service->create(new CreateFolderCommand(parentId: 1, name: 'Test', slug: 'existing', ownerId: 1));
    }
}
// Только после того как тест написан — реализуем FolderService
```

**Application layer (`src/App/`):** specification-first. Перед реализацией
хендлера — опиши тест ("при команде X хендлер вызывает Y и публикует Z").
Зафиксируй тест. Потом реализуй. Паттерн: AI пишет тест → ты читаешь и
подтверждаешь → AI реализует.

**Infrastructure layer (`src/Infrastructure/`):** implementation-first.
Интеграционный тест после реализации. Mock-free: тестируем с реальной БД
(тестовый контейнер).

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

### Расположение тестов

Тесты лежат рядом с кодом зеркально структуре `src/`:

```
packages/core/src/Folder/FolderService.php
packages/core/tests/Unit/Folder/FolderServiceTest.php
```

### Обязательный охват

Для каждого среза разработки:
1. Все **инварианты из спеки** — каждый инвариант покрыт тестом
2. Все **граничные случаи** (slug конфликт, превышение глубины, disabled пользователь)
3. **Happy path** — основной сценарий использования

### Запрещённые паттерны в тестах

- `@runInSeparateProcess` без явной причины и комментария
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

### Frontend (React/TypeScript)

- TypeScript strict mode — 0 ошибок
- Biome lint — 0 предупреждений
- Storybook story для каждого нового компонента в `frontend/ui-primitives/`
- Только через `@opora/api-client` — прямые fetch/axios запросы запрещены
- Tailwind CSS 4 утилиты + shadcn/ui компоненты

---

## ADR-процесс

Architecture Decision Records — обязательны при:
- Добавлении новой внешней зависимости в core
- Изменении публичного API или его поведения
- Изменении схемы БД в существующих таблицах
- Любом архитектурном изменении которое влияет на другие модули
- Создании нового внутреннего пакета

**Как создать ADR:**
```bash
# Определи следующий номер
ls docs/architecture/decisions/ | sort | tail -1

# Создай файл
touch docs/architecture/decisions/ADR-NNN-short-title.md
```

**Шаблон ADR:**
```markdown
# ADR-NNN: Заголовок решения

## Status
Proposed | Accepted | Deprecated | Superseded by ADR-NNN

## Context
[Контекст и проблема которую решаем]

## Decision
[Принятое решение]

## Consequences
[Последствия — положительные и отрицательные]

## Alternatives Considered
[Что рассматривали и почему отклонили]
```

Текущие ADR: `docs/architecture/decisions/`

---

## Справочник команд

```bash
make help           # полный список команд
make up             # docker compose up -d
make down           # docker compose down
make install        # composer install + pnpm install
make migrate        # применить миграции БД
make schema-sync    # применить Schema-as-Code изменения
make test           # все тесты
make test-unit      # только unit-тесты
make stan           # PHPStan level 8
make cs             # PHP CS Fixer (fix mode)
make cs-check       # PHP CS Fixer (check mode, не меняет файлы)
make rector         # Rector (fix mode)
make rector-check   # Rector (dry-run)
make ci             # cs-check + stan + rector-check + test
make dev            # frontend dev server (localhost:5173)
make build          # frontend production build
make storybook      # Storybook (localhost:6006)
make up-tools       # pgAdmin (5050) + Mailpit (8025)
make logs           # логи всех контейнеров
make shell          # bash в php-fpm контейнере
make db-shell       # psql в postgres контейнере
```

---

## Вопросы и помощь

- **GitHub Discussions** — общие вопросы, идеи, обсуждения
- **GitHub Issues** — баги и предложения функций

Баги и уязвимости — см. [SECURITY.md](SECURITY.md).
