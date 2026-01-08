<?php

use AichaDigital\Laratickets\Support\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->id();

            // Ticket reference (UUID - matches tickets.id type)
            MigrationHelper::uuidForeignKey($table, 'ticket_id');
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');

            // Assessor reference - auto-detected type
            MigrationHelper::userIdColumn($table, 'assessor_id');

            $table->string('risk_level')->comment('low, medium, high, critical');
            $table->text('justification');
            $table->timestamps();

            $table->index(['ticket_id', 'risk_level']);
            $table->index('risk_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_assessments');
    }
};
