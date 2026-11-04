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

        if (!$schema->hasTable('users')) {
            return;
        }

        $schema->table('users', function (Blueprint $table) use ($schema){
            if (!$schema->hasColumn('users', 'is_admin')){
                $table->boolean('is_admin')
                    ->default(false)
                    ->after('id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection($this->getConnection());

        if (!$schema->hasTable('users')) {
            return;
        }

        $schema->table('users', function (Blueprint $table) use ($schema){
            if ($schema->hasColumn('users', 'is_admin')){
                $table->dropColumn('is_admin');
            }
        });
    }
};
