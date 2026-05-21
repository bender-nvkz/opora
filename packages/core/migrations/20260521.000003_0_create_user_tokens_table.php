<?php

declare(strict_types=1);

namespace Opora\Core\Migration;

use Cycle\Migrations\Migration;

/**
 * Таблица токенов аутентификации.
 *
 * @see .local/tech_specs/1_1-opora-core.md §2
 */
final class CreateUserTokensTable extends Migration
{
    public function up(): void
    {
        $this->database()->execute(
            'CREATE TABLE opora_user_tokens (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                user_id UUID NOT NULL REFERENCES opora_users(id) ON DELETE CASCADE,
                token_hash VARCHAR(64) NOT NULL UNIQUE,
                type VARCHAR(32) NOT NULL DEFAULT \'access\',
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT NOW()
            )',
        );
    }

    public function down(): void
    {
        $this->database()->execute('DROP TABLE IF EXISTS opora_user_tokens CASCADE');
    }
}
