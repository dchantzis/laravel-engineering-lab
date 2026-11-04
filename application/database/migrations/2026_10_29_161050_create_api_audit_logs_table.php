<?php

use App\Enums\DatabaseConnection;
use App\Enums\ApiAuditLogType;
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

        if ($schema->hasTable('api_audit_logs')) {
            return;
        }

        $schema->create('api_audit_logs', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('ip_trace_id')->nullable();
            $table->uuid('oauth_client_id')->nullable();
            $table->bigInteger('user_id')->nullable();
            $table->string('resource')->nullable();
            $table->uuid('request_uuid')->nullable();
            $table->mediumText('content')->nullable();
            $table->unsignedSmallInteger('http_status_code')->nullable();
            $table->enum('type', [
                ApiAuditLogType::REQUEST->value,
                ApiAuditLogType::RESPONSE->value,
                ApiAuditLogType::ASYNC_RESPONSE->value,
            ]);
            $table->mediumText('meta');
            $table->timestamps();

            $table->index('ip_trace_id');
            $table->index('created_at');
            $table->index('request_uuid');
            $table->index('resource', 'api_audit_logs_resource');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->getConnection())
            ->dropIfExists('api_audit_logs');
    }
};
