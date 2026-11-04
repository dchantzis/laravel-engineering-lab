<?php

namespace App\Models;

use App\Enums\DatabaseConnection;
use App\Traits\Encryptable;
use App\Traits\Factories\MakesUuid;
use App\Traits\ModelGetTableName;
use App\Traits\Models\FilterableByDateDuration;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Laravel\Passport\Client;
use Ramsey\Uuid\UuidInterface;

/**
 * @property int $id
 * @property UuidInterface|string $request_uuid
 * @property string type
 *
 * @method static byListOfRequests(Collection|array $uuids)
 * @method static byRequest(string|UuidInterface $uuid)
 */
class ApiAuditLog extends Model
{
    use Encryptable;
    use FilterableByDateDuration;
    use ModelGetTableName;
    use MakesUuid;

    protected $connection = DatabaseConnection::APPLICATION;

    protected $table = 'api_audit_logs';

    protected $fillable = [
        'ip_trace_id',
        'oauth_client_id',
        'user_id',
        'resource',
        'request_uuid',
        'content',
        'http_status_code',
        'type',
        'meta',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    protected $encryptable = [
        'content',
        'meta',
    ];

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'oauth_client_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function ipTrace(): BelongsTo
    {
        return $this->belongsTo(IpTrace::class);
    }

    public function scopeByListOfRequests(Builder $query, array|Collection $uuids): Builder
    {
        return $query->whereIn('request_uuid', $uuids);
    }

    public function scopeByRequest(Builder $query, string|UuidInterface $uuid): Builder
    {
        return $query->where('request_uuid', static::makeUuid($uuid));
    }
}
