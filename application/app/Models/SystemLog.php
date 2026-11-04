<?php

namespace App\Models;

use App\Enums\DatabaseConnection;
use App\Traits\Encryptable;
use App\Traits\ModelGetTableName;
use App\Traits\Models\FilterableByDateDuration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $message
 * @property string $level
 * @property string $level_name
 * @property string $context
 * @property string $channel
 * @property int|null $ip_trace_id
 * @property int|null $console_command_log_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ConsoleCommandLog|null $consoleCommandLog
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog byDuration(\Carbon\Carbon|\Carbon\CarbonImmutable|null $startDate = null, \Carbon\Carbon|\Carbon\CarbonImmutable|null $endDate = null, ?string $dateAttribute = null, ?string $dateFormat = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog whereChannel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog whereConsoleCommandLogId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog whereContext($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog whereIpTraceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog whereLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog whereLevelName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemLog whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class SystemLog extends Model
{
    use Encryptable;
    use FilterableByDateDuration;
    use ModelGetTableName;

    protected $connection = DatabaseConnection::APPLICATION;

    protected $table = 'system_logs';

    protected $fillable = [
        'console_command_log_id',
        'ip_trace_id',
        'message',
        'level',
        'level_name',
        'context',
        'channel',
    ];

    protected array $encryptable = [
        'context',
    ];

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function consoleCommandLog(): BelongsTo
    {
        return $this->belongsTo(ConsoleCommandLog::class);
    }
}
