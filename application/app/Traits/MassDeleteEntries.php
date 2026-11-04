<?php

namespace App\Traits;

use App\Enums\DatabaseConnection;
use Carbon\Carbon;
use Error;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

trait MassDeleteEntries
{
    /**
     * @throws Throwable
     */
    public function massDeleteEntries(Carbon $endDate): int
    {
        $totalEntriesCount = 0;

        try {
            do {
                DB::connection(DatabaseConnection::APPLICATION)->beginTransaction();

                /** @var Model $modelClass */
                $modelClass = $this->model();

                $deletedEntriesCount = $modelClass::where('created_at', '<=', $endDate->format('Y-m-d H:i:s'))
                    ->limit(100)
                    ->delete();

                DB::connection(DatabaseConnection::APPLICATION)->commit();

                $totalEntriesCount += $deletedEntriesCount;

            } while ($deletedEntriesCount > 0);
        } catch (Exception|Error $exception) {
            DB::connection(DatabaseConnection::APPLICATION)->rollBack();

            Log::error(
                $exception->getMessage()
            );

            return 0;
        }

        return $totalEntriesCount;
    }
}
