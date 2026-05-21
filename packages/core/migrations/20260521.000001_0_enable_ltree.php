<?php

declare(strict_types=1);

namespace Opora\Core\Migration;

use Cycle\Migrations\Migration;

/**
 * Включение расширения PostgreSQL ltree для nested paths в opora_folders.
 *
 * @see ADR-004
 */
final class EnableLtree extends Migration
{
    public function up(): void
    {
        $this->database()->execute('CREATE EXTENSION IF NOT EXISTS ltree');
    }

    public function down(): void
    {
        // Не удаляем расширение при откате — может использоваться другими модулями
    }
}
