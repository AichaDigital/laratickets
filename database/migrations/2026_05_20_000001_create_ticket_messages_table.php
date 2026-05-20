<?php

use AichaDigital\Laratickets\Support\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_messages', function (Blueprint $table): void {
            MigrationHelper::uuidPrimaryKey($table);

            MigrationHelper::uuidForeignKey($table, 'ticket_id');
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');

            MigrationHelper::userIdColumn($table, 'author_id');
            $table->string('author_role', 16)->comment('client | staff');
            $table->string('visibility', 16)->default('public')->comment('public | internal');
            $table->text('body');

            $table->timestamp('redacted_at')->nullable();
            MigrationHelper::userIdColumn($table, 'redacted_by', true);
            $table->string('redaction_reason', 255)->nullable();

            $table->timestamps();

            $table->index(['ticket_id', 'visibility', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
    }
};
