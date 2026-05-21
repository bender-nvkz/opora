<?php

declare(strict_types=1);

namespace Opora\Core\Migration;

use Cycle\Migrations\Migration;

/**
 * Таблица пользователей.
 *
 * @see .local/tech_specs/1_1-opora-core.md §2
 */
final class CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->database()->execute(
            'CREATE TABLE opora_users (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                email VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                display_name VARCHAR(255) NOT NULL DEFAULT \'\',
                is_active BOOLEAN NOT NULL DEFAULT true,
                created_at TIMESTAMP NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMP
            )',
        );
    }

    public function down(): void
    {
        $this->database()->execute('DROP TABLE IF EXISTS opora_users CASCADE');
    }
}
