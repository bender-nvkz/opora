<?php

declare(strict_types=1);

namespace Opora\Core\Migration;

use Cycle\Migrations\Migration;

/**
 * Таблица папок (ltree-дерево).
 *
 * path — ltree-метка, parent_id — ссылка на родителя (NULL для корня).
 *
 * @see .local/tech_specs/1_1-opora-core.md §2
 */
final class CreateFoldersTable extends Migration
{
    public function up(): void
    {
        $this->database()->execute(
            'CREATE TABLE opora_folders (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                parent_id UUID REFERENCES opora_folders(id) ON DELETE RESTRICT,
                owner_id UUID NOT NULL REFERENCES opora_users(id) ON DELETE CASCADE,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                path LTREE NOT NULL,
                position INTEGER NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMP
            )',
        );
    }

    public function down(): void
    {
        $this->database()->execute('DROP TABLE IF EXISTS opora_folders CASCADE');
    }
}
