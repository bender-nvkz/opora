# ADR-007: Interface Extraction for Testability

**Date:** 2026-05-21
**Status:** Accepted
**Deciders:** Core team

## Context

В срезе A спеки `1_1-opora-core` потребовалось замокать `ModuleMigrationRunner` в unit-тесте `InstallCommandUnitTest`.

`ModuleMigrationRunner` был спроектирован как `final readonly class` — по общему соглашению (см. CLAUDE.md: «Handlers — final, без наследования»). PHPUnit 13.1 не может создать мок для final-класса без интерфейса:

```php
// ❌ Не работает: PHPUnit 13.1 не мокает final-классы
$this->createMock(ModuleMigrationRunner::class);
// PHPUnit Fatal Error: Class "Opora\Core\Module\ModuleMigrationRunner" is "final"

// ❌ Не работает: createMock требует интерфейс или абстрактный класс
$this->getMockBuilder(ModuleMigrationRunner::class)
    ->disableOriginalConstructor()
    ->getMock();
// PHPUnit deprecation: "Creating test doubles for final classes is deprecated"
```

Альтернатива — вынести интерфейс `ModuleMigrationRunnerInterface` и заставить команду зависеть от интерфейса. Тест мокает интерфейс.

```php
// ✅ Работает: мок интерфейса
$this->createMock(ModuleMigrationRunnerInterface::class);
```

**Проблема:** это создаёт прецедент. Каждый новый модуль будет упираться в ту же ситуацию — `final class` в Infrastructure/App layer, который нужно замокать в unit-тесте. Без формального решения каждый разработчик/AI-агент будет принимать решение ad-hoc.

## Decision

**Извлекать интерфейс из final-класса, когда класс нужно замокать в unit-тесте.**

Правила:

1. **Интерфейс располагается рядом с классом** в том же namespace и директории.
   - Класс: `src/Module/ModuleMigrationRunner.php`
   - Интерфейс: `src/Module/ModuleMigrationRunnerInterface.php`

2. **Именование:** `{ClassName}Interface` — зеркально имени класса.

3. **Интерфейс содержит ТОЛЬКО публичные методы**, которые нужны потребителям (командам, сервисам). Не нужно выносить все pubic-методы класса — только те, что реально вызываются через контракт.

4. **Класс имплементирует интерфейс** и добавляет `implements {InterfaceName}`.

5. **Потребители (команды, сервисы) зависят от интерфейса**, а не от класса.
   - Конструктор: `__construct(private readonly ModuleMigrationRunnerInterface $runner)`
   - DI-контейнер: биндинг интерфейса на реализацию.

6. **Unit-тесты мокают интерфейс**, а не класс.

### Когда НЕ извлекать интерфейс

- Класс уже покрыт **интеграционными тестами**, и unit-тесты ему не нужны.
- Класс — это **Value Object** или **Domain Event** (чистые данные, нет поведения для мока).
- Класс — это **хендлер без потребителя** (вызывается только через Command Bus, диспатч которого интеграционно тестируется).

### Процесс принятия решения

При создании нового `final class`, который:
- имеет потребителя (команду, сервис, другой хендлер), И
- потребитель требует unit-тестирования

→ **автоматически извлечь интерфейс на этапе планирования.**

## Consequences

### Положительные

- **Тестируемость:** unit-тесты мокают интерфейсы, не ломаются от изменений реализации.
- **LSP compliance:** потребитель зависит от абстракции, а не от конкретного класса. Замена реализации (например, `CycleMigrationRunner` вместо `FileMigrationRunner`) не требует изменения потребителя.
- **Атомарные коммиты:** интерфейс можно закоммитить отдельно от реализации — это снижает конфликты в параллельных ветках.
- **Понятный контракт:** интерфейс — это явный список методов, которые нужны потребителю. Проще читать, чем выискивать вызовы в коде.

### Отрицательные

- **Интерфейс пролиферация:** каждый потребитель порождает как минимум один интерфейс. В большом проекте это ведёт к росту числа файлов.
- **Дублирование docblock:** при смене сигнатуры метода нужно менять и интерфейс, и реализацию.
- **Дополнительный файл при создании класса:** нарушает поток «написал класс — всё работает».

### Смягчение отрицательных последствий

- **Не выносить все pubic-методы** — только те, что реально нужны потребителю. Методы для внутреннего использования остаются только в классе.
- **Не извлекать интерфейс превентивно** — только когда появляется реальный потребитель, которому нужен мок.
- **Рассмотреть PHPUnit 14+:** если PHPUnit научится мокать final-классы без deprecation, правило можно пересмотреть.

## Alternatives Considered

### 1. Мокать final-класс через PHPUnit 13 workaround

PHPUnit 13.1 позволяет создавать моки final-классов с deprecation notice.
В PHPUnit 14 это будет удалено.

**Отвергнуто,** потому что:
- Deprecation notice засоряет вывод тестов.
- PHPUnit 14 сломает такие тесты.
- Зависимость от реализации, а не от абстракции — нарушение LSP.

### 2. Использовать partial mock (`onlyMethods`)

```php
$this->getMockBuilder(ModuleMigrationRunner::class)
    ->onlyMethods(['run'])
    ->disableOriginalConstructor()
    ->getMock();
```

**Отвергнуто,** потому что:
- `disableOriginalConstructor()` оставляет зависимости незаданными — `null`-безопасность не гарантируется.
- Если класс не имеет конструктора без параметров — мок не создашь.
- PHPUnit deprecation для final-классов.

### 3. Создать Test Double вручную

```php
final class ModuleMigrationRunnerTestDouble implements ModuleMigrationRunnerInterface
{
    public function run(string $moduleName, string $directory, string $namespace): void {}
}
```

**Отвергнуто,** потому что:
- Ручное поддержание test double при изменении интерфейса — источник багов.
- Test double не умеет проверять аргументы вызовов без дополнительного кода.
- PHPUnit-мок делает это бесплатно через `with()` / `willReturn()` / `expects()`.

### 4. Integration-only тестирование (без моков)

Тестировать команду только через integration-тесты с реальной БД.

**Отвергнуто,** потому что:
- Integration-тесты медленные (БД, filesystem).
- Нельзя тестировать граничные случаи (ошибки БД, timeout) без моков.
- Нарушает ADR-006 (стратифицированный TDD) — unit-тесты для Application layer обязательны.

## Связь с другими ADR

- **ADR-003** (Repository Structure): репозитории в Domain — только интерфейсы; реализации — в Infrastructure. Интерфейсы репозиториев извлекаются по тому же принципу, но они являются частью Domain (бизнес-контракт), а не тестовым паттерном. ADR-007 дополняет: интерфейсы для App/Infrastructure классов тоже допустимы, если нужны для тестируемости.
- **ADR-006** (Testing Strategy): specification-first для Application layer предполагает моки на интерфейсы. ADR-007 объясняет, откуда берутся эти интерфейсы, если класс спроектирован как `final`.

## References

- `InstallCommandUnitTest.php` — пример использования `ModuleMigrationRunnerInterface` как мока
- `ModuleMigrationRunnerInterface.php` — интерфейс, извлечённый по этому решению
- `CLAUDE.md` — раздел «Стиль кода PHP»: «Handlers — final, без наследования»

## Changelog

- 2026-05-21: Initial version (Accepted)
