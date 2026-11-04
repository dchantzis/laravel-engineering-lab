<?php

use App\Enums\DatabaseConnection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return DatabaseConnection::APPLICATION;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());

        if ($schema->hasTable('system_logs')) {
            return;
        }

        $schema->create('system_logs', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->text('message');
            $table->string('level');
            $table->string('level_name');
            $table->mediumText('context');
            $table->string('channel');
            $table->unsignedBigInteger('ip_trace_id')->nullable();
            $table->unsignedBigInteger('console_command_log_id')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index('console_command_log_id');
            $table->index('ip_trace_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->getConnection())
            ->dropIfExists('system_logs');
    }
};
