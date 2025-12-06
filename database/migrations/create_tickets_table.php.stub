<?php

use AichaDigital\Laratickets\Support\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            // UUID v7 primary key (agnostic: string or binary via cast)
            MigrationHelper::uuidPrimaryKey($table);

            $table->string('subject');
            $table->text('description');

            // Status and priority
            $table->string('status')->default('new')->index();
            $table->string('user_priority')->default('medium')->comment('User requested priority');
            $table->string('assessed_risk')->nullable()->comment('Risk assessed by Level III/IV');

            // Level management
            $table->foreignId('current_level_id')->constrained('ticket_levels');
            $table->foreignId('requested_level_id')->nullable()->constrained('ticket_levels')->comment('For pending escalations');

            // Department
            $table->foreignId('department_id')->constrained('departments');

            // User references - auto-detected type
            MigrationHelper::userIdColumn($table, 'created_by');
            MigrationHelper::userIdColumn($table, 'resolved_by', nullable: true);

            // Scores and ratings
            $table->decimal('global_score', 3, 2)->nullable()->comment('Average evaluation score (1-5)');
            $table->integer('total_evaluations')->default(0);

            // Timestamps and deadlines
            $table->timestamp('estimated_deadline')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['status', 'current_level_id']);
            $table->index(['department_id', 'status']);
            $table->index('estimated_deadline');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
