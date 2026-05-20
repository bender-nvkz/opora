# ═══════════════════════════════════════════════════════════════════
# Опора PIM — Makefile
# Все команды выполняются через Docker Compose (единственный поддерживаемый способ)
# Локальный PHP без Docker — не поддерживается
# ═══════════════════════════════════════════════════════════════════

.PHONY: help up down restart build install migrate fresh-db test test-unit \
        test-integration test-functional stan cs cs-check rector rector-check \
        dev build-frontend shell db-shell logs clean ci format

# Значения по умолчанию
COMPOSE = docker compose
PHP = $(COMPOSE) exec php-fpm
PNPM = pnpm
APP_ENV ?= dev

## ─── Справка ──────────────────────────────────────────────────────

help: ## Показать эту справку
	@echo "Опора PIM — список команд:"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'
	@echo ""
	@echo "Инструменты: docker compose, pnpm"

## ─── Docker окружение ─────────────────────────────────────────────

up: ## Поднять все сервисы
	$(COMPOSE) up -d
	@echo "✓ Окружение запущено. Приложение: http://localhost:8080"

up-tools: ## Поднять все сервисы включая pgAdmin и Mailpit
	$(COMPOSE) --profile tools up -d
	@echo "✓ pgAdmin: http://localhost:5050 | Mailpit: http://localhost:8025"

down: ## Остановить все сервисы (данные сохраняются)
	$(COMPOSE) down

down-volumes: ## Остановить и удалить все данные (volumes)
	$(COMPOSE) down -v
	@echo "⚠ Все данные удалены"

restart: ## Перезапустить все сервисы
	$(COMPOSE) restart

build: ## Пересобрать Docker-образы
	$(COMPOSE) build --no-cache

ps: ## Показать статус контейнеров
	$(COMPOSE) ps

logs: ## Показать логи всех сервисов (follow)
	$(COMPOSE) logs -f

logs-php: ## Показать логи только php-fpm
	$(COMPOSE) logs -f php-fpm

logs-nginx: ## Показать логи только nginx
	$(COMPOSE) logs -f nginx

logs-postgres: ## Показать логи только postgres
	$(COMPOSE) logs -f postgres

## ─── Зависимости ──────────────────────────────────────────────────

install: install-php install-frontend ## Установить все зависимости (PHP + frontend)

install-php: ## Установить PHP-зависимости
	$(PHP) composer install --prefer-dist --no-interaction
	@echo "✓ PHP-зависимости установлены"

install-frontend: ## Установить frontend-зависимости
	$(PNPM) install --frozen-lockfile
	@echo "✓ Frontend-зависимости установлены"

update-php: ## Обновить PHP-зависимости
	$(PHP) composer update

update-frontend: ## Обновить frontend-зависимости
	$(PNPM) update

## ─── База данных ──────────────────────────────────────────────────

migrate: ## Применить все миграции
	$(PHP) php bin/opora migrate/up

migrate-down: ## Откатить последнюю миграцию
	$(PHP) php bin/opora migrate/down

fresh-db: ## Пересоздать БД с нуля + применить миграции
	$(COMPOSE) exec postgres psql -U opora -c "DROP DATABASE IF EXISTS opora; CREATE DATABASE opora;"
	$(COMPOSE) exec postgres psql -U opora -d opora -f /docker-entrypoint-initdb.d/01-init.sql
	$(MAKE) migrate
	@echo "✓ БД пересоздана"

db-shell: ## Открыть psql в контейнере postgres
	$(COMPOSE) exec postgres psql -U opora -d opora

## ─── Тесты ────────────────────────────────────────────────────────

test: ## Запустить все тесты
	$(PHP) vendor/bin/phpunit

test-unit: ## Только unit-тесты (быстро, без БД)
	$(PHP) vendor/bin/phpunit --testsuite Unit

test-integration: ## Только integration-тесты (нужна БД)
	$(PHP) vendor/bin/phpunit --testsuite Integration

test-functional: ## Только functional/e2e тесты
	$(PHP) vendor/bin/phpunit --testsuite Functional

test-coverage: ## Тесты с отчётом о покрытии
	$(PHP) vendor/bin/phpunit --coverage-html=runtime/coverage/html
	@echo "✓ Отчёт: runtime/coverage/html/index.html"

## ─── Качество кода ────────────────────────────────────────────────

ci: cs-check stan rector-check test ## Полный CI-прогон локально

stan: ## PHPStan level 8 — статический анализ
	$(PHP) vendor/bin/phpstan analyse --no-progress

stan-verbose: ## PHPStan с полным выводом
	$(PHP) vendor/bin/phpstan analyse

cs: ## PHP CS Fixer — исправить нарушения (fix mode)
	$(PHP) vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php
	@echo "✓ Code style исправлен"

cs-check: ## PHP CS Fixer — проверить без исправления (check mode)
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.php

rector: ## Rector — применить рефакторинг
	$(PHP) vendor/bin/rector process
	@echo "✓ Rector применён"

rector-check: ## Rector — проверить без изменений (dry-run)
	$(PHP) vendor/bin/rector process --dry-run --no-progress-bar

## ─── Frontend ─────────────────────────────────────────────────────

dev: ## Запустить frontend dev-сервер
	$(PNPM) run dev

build-frontend: ## Собрать frontend для production
	$(PNPM) run build
	@echo "✓ Frontend собран: apps/admin/dist/"

lint-frontend: ## Lint всего frontend кода (Biome)
	$(PNPM) run lint

lint-fix: ## Lint с автоисправлением
	$(PNPM) run lint:fix

typecheck: ## TypeScript проверка типов
	$(PNPM) run typecheck

storybook: ## Запустить Storybook
	$(PNPM) run storybook

storybook-build: ## Собрать Storybook static
	$(PNPM) run storybook:build

## ─── Утилиты ──────────────────────────────────────────────────────

shell: ## Открыть bash в контейнере php-fpm
	$(COMPOSE) exec php-fpm bash

nginx-shell: ## Открыть shell в контейнере nginx
	$(COMPOSE) exec nginx sh

clean: ## Очистить кэши и временные файлы
	$(PHP) rm -rf runtime/cache/* runtime/logs/*
	rm -f .php-cs-fixer.cache .phpunit.result.cache
	$(PNPM) run clean
	@echo "✓ Кэши очищены"

pre-commit: ## Запустить все pre-commit хуки вручную
	pre-commit run --all-files

license-check: ## Проверить лицензии зависимостей
	$(PHP) composer licenses
	@echo ""
	@echo "⚠ Убедитесь, что все лицензии совместимы с AGPLv3"

env: ## Создать .env из .env.example (если .env не существует)
	@if [ ! -f .env ]; then \
		cp .env.example .env; \
		echo "✓ .env создан из .env.example. Проверьте и настройте значения."; \
	else \
		echo "⚠ .env уже существует. Пропускаем."; \
	fi

gitlab-sync: ## Принудительно синхронизировать с GitLab-зеркалом
	git push --mirror https://git.meiji.media/opora/opora.git
	@echo "✓ Синхронизация с GitLab завершена"
