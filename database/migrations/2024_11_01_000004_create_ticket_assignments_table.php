<?php

use AichaDigital\Laratickets\Support\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_assignments', function (Blueprint $table) {
            $table->id();

            // Ticket reference (UUID - matches tickets.id type)
            MigrationHelper::uuidForeignKey($table, 'ticket_id');
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');

            // Agent reference - auto-detected type
            MigrationHelper::userIdColumn($table, 'user_id');

            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('individual_rating', 3, 2)->nullable()->comment('Rating for this agent on this ticket');
            $table->timestamps();

            $table->index(['ticket_id', 'user_id']);
            $table->index('completed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_assignments');
    }
};
