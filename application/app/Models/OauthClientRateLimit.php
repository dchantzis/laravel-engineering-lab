<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\DatabaseConnection;

class OauthClientRateLimit extends Model
{
    protected $connection = DatabaseConnection::APPLICATION;

    protected $table = 'oauth_client_rate_limits';

    protected $primaryKey = 'client_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'client_id',
        'requests_per_minute',
    ];

    protected function casts(): array
    {
        return [
            'requests_per_minute' => 'integer',
        ];
    }
}
