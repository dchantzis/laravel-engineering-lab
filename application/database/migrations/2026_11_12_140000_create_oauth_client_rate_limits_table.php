<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());

        $schema->create('oauth_client_rate_limits', function (Blueprint $table) {
            $table->uuid('client_id')->primary();

            $table->unsignedInteger('requests_per_minute');

            $table->timestamps();

            $table->foreign('client_id')
                ->references('id')
                ->on('oauth_clients')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->getConnection())
            ->dropIfExists('oauth_client_rate_limits');
    }
};
