# Changelog

Все заметные изменения в проекте документируются здесь.

Формат основан на [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
и проект следует [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-05-20

### Added

- **Docker Compose окружение** для локальной разработки: PHP 8.4 + Nginx 1.27 + PostgreSQL 16 с pgvector. Три сервиса (php-fpm, nginx, postgres) с health checks.
- **CLI entrypoint** через `bin/opora` — заглушка консольного приложения для будущих команд (migration, schema:sync).
- **HTTP entrypoint** через `public/index.php` — заглушка Application, возвращает "OK" для проверки работоспособности.
- **Config-plugin структура** Yii3: раздельные конфиги для params/common/web/console с поддержкой локального переопределения (`config/params/local.php`).
- **PSR-4 автолоад** для неймспейсов `Opora\Core`, `Opora\App`, `Opora\Domain`, `Opora\Infrastructure`.
- **Architecture Decision Records**: ADR-000 (использование ADR), ADR-001 (выбор Yii3), ADR-002 (выбор Cycle ORM 2), ADR-003 (dual-license схема репозиториев).
- **Документация проекта**: README с архитектурным обзором, CLAUDE.md для AI-агентов, CONTRIBUTING.md, SECURITY.md, CODE_OF_CONDUCT.md.
- **Makefile** с командами: `make dev`, `make test`, `make stan`, `make cs-fix`, `make frontend-build` и др.
- **CI/CD pipeline**: 7 джобов в GitHub Actions — PHP CS Fixer, PHPStan (level 8), Rector (check-only), PHPUnit (Unit + Integration), Frontend Lint & TypeCheck, Frontend Build, License Check. Агрегатор `ci-success` для branch protection.
- **GitLab mirroring**: workflow автоматического зеркалирования репозитория из GitHub в GitLab.
- **Frontend окружение**: pnpm workspace, Biome (линтер + форматтер), Turbo (parallel builds), Vite + React 18 + TanStack Router, Storybook для ui-primitives.
- **UI пакеты**: `@opora/design-tokens` (CSS-переменные), `@opora/ui-primitives` (базовые компоненты со Storybook), `@opora/api-client` (скелет клиента).
- **Docker конфиги**: php-dev.ini/php-prod.ini, fpm-dev.conf/fpm-prod.conf, nginx/default.conf, postgres/init.sql (расширения uuid-ossp, pg_trgm, ltree, pgcrypto, vector).
- **Инструменты качества**: PHP CS Fixer, PHPStan (level 8), Rector, PHPUnit, Biome, pre-commit hooks.
- **Лицензионный контроль**: `scripts/check-licenses.py` проверяет AGPLv3-совместимость всех зависимостей в CI.
- **Placeholder test** для верификации PHPUnit (`tests/Unit/PlaceholderTest.php`).

### Changed

- Структура frontend-пакетов перенесена из `packages/` в `frontend/` для соответствия структуре монорепо.

### Security

- Лицензионный контроль в CI: автоматическая проверка всех зависимостей (Composer + npm) на AGPLv3-совместимость.
- `roave/security-advisories` в dev-зависимостях для блокировки пакетов с известными уязвимостями.
- CODEOWNERS для обязательного ревью на критические файлы.

### Known Issues

- `GET /health` endpoint не реализован — `public/index.php` возвращает 200 OK без проверки состояния БД и сервисов. Будет исправлено в v0.2.0.

### Notes

- **Версии зависимостей**: указанные в `0-opora-guide.md` версии были спекулятивными. В `composer.json` установлены реально доступные. Основные расхождения: `yiisoft/di` (spec: 2.2.0 → fact: ^1.4.1), `yiisoft/yii-cycle` (spec: 2.0.0 → fact: 2.0.x-dev), `yiisoft/queue` (spec: 3.1.0 → fact: 3.0.x-dev), `yiisoft/auth` (spec: 4.0.0 → fact: ^3.2.1). Полный список — в `composer.json`.
- **Минимальная стабильность** изменена на `dev` (в spec: `stable`), т.к. некоторые пакеты Yii3 доступны только как dev-версии.
- **Full CI pipeline** включает 7 параллельных джобов + агрегатор. Среднее время прохода: ~5-8 минут.
- **Frontend packages** (api-client, design-tokens, ui-primitives) находятся в состоянии скелета — реальная имплементация начнётся на соответствующих этапах.

[Unreleased]: https://github.com/bender-nvkz/opora/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/bender-nvkz/opora/releases/tag/v0.1.0
