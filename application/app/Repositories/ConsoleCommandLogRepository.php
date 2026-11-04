<?php

namespace App\Repositories;

use App\Models\ConsoleCommandLog;
use App\Traits\FilterableByDateDuration;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ConsoleCommandLogRepository
{
    use FilterableByDateDuration;

    public static function getById(
        int $id
    ): ?ConsoleCommandLog {
        return ConsoleCommandLog::find($id);
    }

    public static function getEntries(
        ?int $limit,
        ?int $offset,
        Carbon|DateTimeInterface|null $startDate,
        Carbon|DateTimeInterface|null $endDate
    ) {
        return self::buildFilterQuery(
            $limit,
            $offset,
            $startDate,
            $endDate
        )->get();
    }

    protected static function buildFilterQuery(
        ?int $limit,
        ?int $offset,
        Carbon|DateTimeInterface|null $startDate,
        Carbon|DateTimeInterface|null $endDate
    ) {
        $query = ConsoleCommandLog::orderBy('created_at', 'DESC');

        if (! is_null($limit) && $limit >= 0) {
            $query->limit($limit);

            if (! is_null($offset)) {
                $query->offset($offset);
            }
        }

        return static::inDateDuration(
            $query,
            $startDate,
            $endDate
        );
    }

    public static function paginate(
        ?int $perPage,
        Carbon|DateTimeInterface|null $startDate,
        Carbon|DateTimeInterface|null $endDate
    ): LengthAwarePaginator {
        return ConsoleCommandLog::byDuration(
                $startDate,
                $endDate
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public static function paginateActive(
        ?int $perPage,
        Carbon|DateTimeInterface|null $startDate,
        Carbon|DateTimeInterface|null $endDate
    ): LengthAwarePaginator {
        return self::currentlyRunningQuery(
            startDate: $startDate,
            endDate: $endDate,
        )
        ->orderByDesc('created_at')
        ->paginate($perPage);
    }

    public static function currentlyRunningQuery(
        ?string $hostname = null,
        ?int $excludeId = null,
        ?string $excludeCommandName = null,
        Carbon|DateTimeInterface|null $startDate = null,
        Carbon|DateTimeInterface|null $endDate = null,
    ): EloquentBuilder {
        $query = ConsoleCommandLog::whereNull('memory_usage_end')
            ->when(! is_null($excludeId), fn ($query) => $query->where('id', '!=', $excludeId))
            ->when($excludeCommandName, fn ($query) => $query->where('name', '!=', $excludeCommandName))
            ->when($hostname, fn ($query) => $query->where('executed_on_host', $hostname));

        return $query->byDuration(
            $startDate,
            $endDate
        );
    }

    public static function countCurrentlyRunning(
        ?string $hostname = null,
        ?int $excludeId = null,
        ?string $excludeCommandName = null
    ): int {
        return self::currentlyRunningQuery(
            $hostname,
            $excludeId,
            $excludeCommandName
        )->count();
    }

    /**
     * Build a text body from the latest completed console command log entry
     * for use as healthchecks.io ping payload when running in quiet mode.
     */
    public static function buildHealthChecksIoBody(string $commandName): string
    {
        $entry = ConsoleCommandLog::where('name', $commandName)
            ->whereNotNull('memory_usage_end')
            ->orderByDesc('id')
            ->first();

        if (! $entry) {
            return '';
        }

        $durationSeconds = $entry->created_at->diffInSeconds($entry->updated_at);

        $lines = [
            sprintf('Command: %s', $entry->name),
            sprintf('Console Command Log ID: %s', $entry->id),
            sprintf('Host: %s', $entry->executed_on_host),
            sprintf('Success: %s', $entry->success ? 'Yes' : 'No'),
            '',
            sprintf('Started: %s', $entry->created_at->format(dateFormat())),
            sprintf('Finished: %s', $entry->updated_at->format(dateFormat())),
            sprintf('Duration: %02d:%02d:%02d', intdiv($durationSeconds, 3600), intdiv($durationSeconds % 3600, 60), $durationSeconds % 60),
            '',
            sprintf('Memory Start: %s', $entry->memory_usage_start),
            sprintf('Memory Peak: %s', $entry->memory_usage_end),
        ];

        $lines[] = '';
        $lines[] = 'Parameters:';
        $lines = array_merge($lines, self::formatParameters($entry->parameters));

        if (! is_null($entry->meta)) {
            $lines[] = '';
            $lines[] = 'Meta:';
            $lines = array_merge($lines, self::formatMeta($entry->meta));
        }

        return implode("\n", $lines);
    }

    private static function formatParameters(mixed $parameters): array
    {
        $decoded = is_string($parameters) ? json_decode($parameters, true) : $parameters;

        if (! is_array($decoded)) {
            return [sprintf('  %s', self::formatHealthcheckValue($decoded ?? $parameters))];
        }

        $flat = [];
        foreach (['arguments', 'options'] as $section) {
            if (isset($decoded[$section]) && is_array($decoded[$section])) {
                foreach ($decoded[$section] as $key => $value) {
                    $flat[$key] = $value;
                }
            }
        }

        if (empty($flat) && ! empty($decoded)) {
            $flat = $decoded;
        }

        if (empty($flat)) {
            return ['  -'];
        }

        $lines = [];
        foreach ($flat as $key => $value) {
            $lines[] = sprintf('  %s: %s', $key, self::formatHealthcheckValue($value));
        }

        return $lines;
    }

    private static function formatMeta(mixed $meta): array
    {
        $decoded = is_string($meta) ? json_decode($meta, true) : (array) $meta;

        if (! is_array($decoded)) {
            return [sprintf('  %s', self::formatHealthcheckValue($decoded ?? $meta))];
        }

        if (empty($decoded)) {
            return ['  -'];
        }

        $lines = [];
        foreach ($decoded as $key => $value) {
            $lines[] = sprintf('  %s: %s', $key, self::formatHealthcheckValue($value));
        }

        return $lines;
    }

    private static function formatHealthcheckValue(mixed $value): string
    {
        return match (true) {
            is_null($value) => '-',
            is_bool($value) => $value ? 'Yes' : 'No',
            is_scalar($value) => (string) $value,
            default => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[unserialisable]',
        };
    }

    public static function hasCurrentlyRunning(
        ?string $hostname = null,
        ?int $excludeId = null,
        ?string $excludeCommandName = null
    ): bool {
        return self::currentlyRunningQuery(
            $hostname,
            $excludeId,
            $excludeCommandName
        )->exists();
    }

    public static function getCurrentlyRunning(
        ?string $hostname = null,
        ?int $excludeId = null,
        ?string $excludeCommandName = null,
    ): Collection {
        return self::currentlyRunningQuery(
            $hostname,
            $excludeId,
            $excludeCommandName
        )
            ->orderByDesc('created_at')
            ->get();
    }
}
