<?php

declare(strict_types=1);

namespace Opora\Core\Migration;

use Cycle\Migrations\Migration;

/**
 * Таблицы установки модулей и расширений.
 *
 * opora_installation — реестр установленных модулей.
 * opora_extensions — реестр расширений (marketplace-задел).
 *
 * @see .local/tech_specs/1_1-opora-core.md §2
 */
final class CreateInstallationTable extends Migration
{
    public function up(): void
    {
        $this->database()->execute(
            'CREATE TABLE opora_installation (
                module_name VARCHAR(64) PRIMARY KEY,
                version VARCHAR(16) NOT NULL DEFAULT \'0.1.0\',
                installed_at TIMESTAMP NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMP
            )',
        );

        $this->database()->execute(
            'CREATE TABLE opora_extensions (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                name VARCHAR(64) NOT NULL UNIQUE,
                version VARCHAR(16) NOT NULL,
                enabled BOOLEAN NOT NULL DEFAULT false,
                installed_at TIMESTAMP NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMP
            )',
        );
    }

    public function down(): void
    {
        $this->database()->execute('DROP TABLE IF EXISTS opora_extensions CASCADE');
        $this->database()->execute('DROP TABLE IF EXISTS opora_installation CASCADE');
    }
}
