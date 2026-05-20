# ADR-001: Выбор Yii3 как backend-фреймворка

**Date:** 2026-05-01
**Status:** Accepted
**Deciders:** Core team

## Context

Платформе Опора нужен PHP-фреймворк, который:
- Поддерживает PHP 8.4 со всеми его возможностями.
- Имеет лёгкий runtime без тяжёлых зависимостей.
- Совместим с Cycle ORM 2 (наш выбор по ADR-002).
- Лицензионно чист: MIT или BSD-3 без GPL.
- Активно поддерживается российским или международным сообществом.
- Имеет хорошую поддержку DI (PSR-11), PSR-15 middleware, PSR-3 logging.
- Не навязывает активный record — мы хотим hexagonal architecture.

Yii3 был официально выпущен 31 декабря 2025 года; на май 2026 более 109 пакетов
yiisoft/* достигли stable. `yiisoft/app` 1.1.0, `yiisoft/db` 2.0, `yiisoft/yii-cycle` 2.0.

## Decision

Использовать **Yii3** (пакеты yiisoft/*) как основной backend-фреймворк.

Конкретные пакеты: yiisoft/app, yiisoft/di, yiisoft/router, yiisoft/middleware-dispatcher,
yiisoft/log, yiisoft/config, yiisoft/yii-cycle, yiisoft/queue, yiisoft/yii-console.

Архитектурный принцип: Yii3 используется как **тонкий фреймворк**. Пакет `Domain/` не зависит
от Yii вообще — только от PSR-интерфейсов и чистого PHP. Это позволяет при необходимости
заменить фреймворк без переписывания доменной логики.

## Consequences

**Положительные:**
- Нет транзитивных зависимостей из «экосистемы Symfony» — чистый minimal.
- Нативная поддержка Cycle ORM 2 через `yiisoft/yii-cycle`.
- BSD-3 лицензия — полностью совместима с AGPLv3.
- Российские корни команды yiisoft — снижает риски поддержки в РФ-контексте.
- Каждый пакет версионируется независимо, SemVer.

**Отрицательные:**
- Меньше готовых расширений и туториалов, чем у Laravel/Symfony.
- Yii3 всё ещё относительно молодой (stable с декабря 2025), экосистема меньше.
- Hiring: меньше Yii3-разработчиков на рынке.

## Alternatives Considered

1. **Laravel 11:** Отклонено. Active Record (Eloquent) несовместим с hexagonal architecture.
   Тяжёлый bootstrap, тянет Symfony Components транзитивно, хуже для DDD.

2. **Symfony 7:** Отклонено. Тяжелее, больше магии, плотная связка компонентов.
   Doctrine ORM — наш выбор в ADR-002 против.

3. **Slim + ручная сборка:** Отклонено. Слишком много boilerplate для нашего размера.
   Yii3 даёт нужный уровень структуры без lock-in.

4. **RoadRunner (Spiral):** Оставлен как опциональный runtime (`yiisoft/yii-runner-roadrunner`)
   для клиентов с высокими требованиями к производительности. PHP-FPM — default.
