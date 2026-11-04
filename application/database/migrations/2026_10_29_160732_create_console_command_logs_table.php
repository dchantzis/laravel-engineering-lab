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

        if ($schema->hasTable('console_command_logs')) {
            return;
        }

        $schema->create('console_command_logs', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->string('name');
            $table->text('parameters')->nullable();
            $table->string('memory_usage_start')->nullable();
            $table->string('memory_usage_end')->nullable();
            $table->boolean('success')->default(false);
            $table->mediumText('meta')->nullable();
            $table->string('executed_on_host')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index('updated_at');
            $table->index('executed_on_host', 'idx_console_command_logs_executed_on_host');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->getConnection())
            ->dropIfExists('console_command_logs');
    }
};
