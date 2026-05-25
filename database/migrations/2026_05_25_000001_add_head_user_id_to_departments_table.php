<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Support\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `head_user_id` to `departments` — a UUID v7 FK column pointing to the
 * consumer's users table. Used by `DefaultRecipientResolver` as the primary
 * fallback for `OPENED` and `CLIENT_REPLIED` events (preferred over
 * `mailbox_email`). Soft FK: no DB constraint emitted (the Core is agnostic
 * to the consumer's users schema, per `MigrationHelper::userIdColumn()`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            MigrationHelper::userIdColumn($table, 'head_user_id', nullable: true);
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->dropIndex(['head_user_id']);
            $table->dropColumn('head_user_id');
        });
    }
};
