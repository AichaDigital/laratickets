<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v1.0 (ADR-004): make escalation_requests.requester_id nullable.
 *
 * A timeout / SLA auto-escalation is a system action, not a user action — it
 * has no human requester. The domain now models that with a null requester_id
 * (resolved from SystemActor), mirroring approver_id which is already nullable.
 * Before this, the column was NOT NULL and auto-escalation could not insert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escalation_requests', function (Blueprint $table) {
            $table->uuid('requester_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // WARNING: restoring NOT NULL will FAIL if any escalation_requests rows
        // have a null requester_id (system / auto-escalation rows created while
        // the column was nullable). Reassign or remove those rows before
        // rolling back.
        Schema::table('escalation_requests', function (Blueprint $table) {
            $table->uuid('requester_id')->nullable(false)->change();
        });
    }
};
