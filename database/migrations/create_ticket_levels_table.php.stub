<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_levels', function (Blueprint $table) {
            $table->id();
            $table->integer('level')->unique()->comment('Level number: 1, 2, 3, 4');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('can_escalate')->default(true)->comment('Can escalate to higher level');
            $table->boolean('can_assess_risk')->default(false)->comment('Can assess ticket risk');
            $table->integer('default_sla_hours')->default(24)->comment('Default SLA in hours');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('level');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_levels');
    }
};
