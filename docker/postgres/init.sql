-- Подключить расширения PostgreSQL, необходимые для Опоры
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";
CREATE EXTENSION IF NOT EXISTS "ltree";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "vector";  -- pgvector для semantic search

-- Создать схему для будущих пакетов
CREATE SCHEMA IF NOT EXISTS opora;

-- Полнотекстовый поиск: использовать russian конфигурацию по умолчанию
ALTER DATABASE opora SET default_text_search_config = 'pg_catalog.russian';
