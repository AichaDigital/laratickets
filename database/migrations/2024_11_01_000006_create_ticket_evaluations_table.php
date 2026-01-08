<?php

use AichaDigital\Laratickets\Support\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_evaluations', function (Blueprint $table) {
            $table->id();

            // Ticket reference (UUID - matches tickets.id type)
            MigrationHelper::uuidForeignKey($table, 'ticket_id');
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');

            // Evaluator reference - auto-detected type
            MigrationHelper::userIdColumn($table, 'evaluator_id');

            $table->decimal('score', 3, 2)->comment('Global ticket score (1.00 to 5.00)');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'evaluator_id']);
            $table->index('score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_evaluations');
    }
};
