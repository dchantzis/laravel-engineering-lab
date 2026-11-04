<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WatchdogWorkCommand extends Command
{
    protected $signature = 'watchdog:work
        {source? : Input source}
        {destination? : Output destination}
        {--format= : Output format, such as json or csv}
        {--verbose-output : Enable detailed output}';

    protected $description = 'Test fixture for scheduler lock monitoring.';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
