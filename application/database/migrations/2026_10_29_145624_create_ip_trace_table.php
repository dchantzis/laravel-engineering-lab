<?php

use App\Enums\DatabaseConnection;
use App\Enums\RequestMethods;
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

        if ($schema->hasTable('ip_trace')) {
            return;
        }

        $schema->create('ip_trace', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->string('ip_address');
            $table->enum('request_method', array_column(RequestMethods::cases(), 'value'));
            $table->string('route');
            $table->mediumText('request_parameters')->nullable();
            $table->mediumText('request_body')->nullable();
            $table->text('request_header')->nullable();
            $table->string('host');
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->getConnection())
            ->dropIfExists('ip_trace');
    }
};
