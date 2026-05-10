<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Support;

use Illuminate\Database\Schema\Blueprint;

/**
 * Migration Helper — UUID-first
 *
 * Per ADR-001 (laratickets) and ADR-006 (larabill canonical), all FK
 * references to the consumer's users table are emitted as char(36) UUID
 * v7. No agnostic detection, no integer/ULID branches.
 *
 * See: docs/ADR-001-uuid-first.md
 */
class MigrationHelper
{
    /**
     * Add a UUID FK column referencing users.id.
     *
     * Used for: created_by, resolved_by, user_id, requester_id, approver_id,
     * evaluator_id, agent_id, rater_id, assessor_id.
     */
    public static function userIdColumn(
        Blueprint $table,
        string $columnName = 'user_id',
        bool $nullable = false
    ): void {
        $column = $table->uuid($columnName);

        if ($nullable) {
            $column->nullable();
        }

        $table->index($columnName);
    }

    /**
     * Add a UUID primary key column.
     */
    public static function uuidPrimaryKey(Blueprint $table, string $columnName = 'id'): void
    {
        $table->uuid($columnName)->primary();
    }

    /**
     * Add a UUID foreign key column (for referencing internal package PKs like ticket.id).
     */
    public static function uuidForeignKey(
        Blueprint $table,
        string $columnName,
        bool $nullable = false,
        bool $index = true
    ): void {
        $column = $table->uuid($columnName);

        if ($nullable) {
            $column->nullable();
        }

        if ($index) {
            $table->index($columnName);
        }
    }
}
