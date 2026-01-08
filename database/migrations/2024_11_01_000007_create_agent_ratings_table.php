<?php

use AichaDigital\Laratickets\Support\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_ratings', function (Blueprint $table) {
            $table->id();

            // Ticket reference (UUID - matches tickets.id type)
            MigrationHelper::uuidForeignKey($table, 'ticket_id');
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');

            // User references - auto-detected type
            MigrationHelper::userIdColumn($table, 'agent_id');
            MigrationHelper::userIdColumn($table, 'rater_id');

            $table->decimal('score', 3, 2)->comment('Agent performance score (1.00 to 5.00)');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'agent_id']);
            $table->index(['agent_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_ratings');
    }
};
