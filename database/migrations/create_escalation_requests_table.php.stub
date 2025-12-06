<?php

use AichaDigital\Laratickets\Support\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalation_requests', function (Blueprint $table) {
            $table->id();

            // Ticket reference (UUID - matches tickets.id type)
            MigrationHelper::uuidForeignKey($table, 'ticket_id');
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');

            $table->foreignId('from_level_id')->constrained('ticket_levels');
            $table->foreignId('to_level_id')->constrained('ticket_levels');

            // User references - auto-detected type
            MigrationHelper::userIdColumn($table, 'requester_id');
            MigrationHelper::userIdColumn($table, 'approver_id', nullable: true);

            $table->text('justification');
            $table->string('status')->default('pending')->comment('pending, approved, rejected');
            $table->text('rejection_reason')->nullable();
            $table->boolean('is_automatic')->default(false)->comment('Auto-escalation by SLA or risk');

            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'status']);
            $table->index('status');
            $table->index('requested_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalation_requests');
    }
};
