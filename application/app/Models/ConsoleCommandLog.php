<?php

namespace App\Models;

use App\Enums\DatabaseConnection;
use App\Traits\Encryptable;
use App\Traits\ModelGetTableName;
use App\Traits\Models\FilterableByDateDuration;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $parameters
 * @property DateTimeInterface|CarbonInterface|Carbon $created_at
 * @property DateTimeInterface|CarbonInterface|Carbon $updated_at
 * @property mixed $memory_usage_start
 * @property mixed $memory_usage_end
 *
 * @method static find(int $trigger_id)
 */
class ConsoleCommandLog extends Model
{
    use Encryptable;
    use FilterableByDateDuration;
    use ModelGetTableName;

    protected $connection = DatabaseConnection::APPLICATION;

    protected $table = 'console_command_logs';

    protected $fillable = [
        'name',
        'parameters',
        'memory_usage_start',
        'memory_usage_end',
        'success',
        'meta',
        'executed_on_host',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    protected $encryptable = [
        'meta',
    ];

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function systemLog(): HasMany
    {
        return $this->hasMany(SystemLog::class);
    }
}
