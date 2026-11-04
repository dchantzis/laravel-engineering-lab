<?php

namespace App\Models;

use App\Enums\DatabaseConnection;
use App\Traits\Encryptable;
use App\Traits\ModelGetTableName;
use App\Traits\Models\FilterableByDateDuration;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ip_address
 * @property string $request_method
 * @property string $route
 * @property string|null $request_parameters
 * @property string|null $request_body
 * @property string|null $request_header
 * @property string $host
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, SystemLog> $systemLog
 * @property-read int|null $system_log_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpTrace byDuration(\Carbon\Carbon|\Carbon\CarbonImmutable|null $startDate = null, \Carbon\Carbon|\Carbon\CarbonImmutable|null $endDate = null, ?string $dateAttribute = null, ?string $dateFormat = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpTrace newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpTrace newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpTrace query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpTrace whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpTrace whereHost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpTrace whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpTrace whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpTrace whereRequestBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpTrace whereRequestHeader($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpTrace whereRequestMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpTrace whereRequestParameters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpTrace whereRoute($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpTrace whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class IpTrace extends Model
{
    use Encryptable;
    use FilterableByDateDuration;
    use ModelGetTableName;

    protected $connection = DatabaseConnection::APPLICATION;

    protected $table = 'ip_trace';

    protected $fillable = [
        'ip_address',
        'request_method',
        'route',
        'request_parameters',
        'request_body',
        'request_header',
        'host',
    ];

    protected array $encryptable = [
        'request_parameters',
        'request_body',
        'request_header',
    ];

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function apiAuditLog(): HasMany
    {
        return $this->hasMany(ApiAuditLog::class);
    }

    public function systemLog(): HasMany
    {
        return $this->hasMany(SystemLog::class);
    }
}
