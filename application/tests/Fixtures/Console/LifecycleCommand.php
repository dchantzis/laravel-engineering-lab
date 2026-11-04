<?php

namespace Tests\Fixtures\Console;

use App\Console\Commands\Command;
use Closure;

final class LifecycleCommand extends Command
{
    protected $signature = 'test:base-command-lifecycle {subject=example} {--mode=normal}';

    protected $description = 'Exercise the base command lifecycle';

    public int $fulfillCalls = 0;

    public function __construct(private readonly ?Closure $work = null)
    {
        parent::__construct();
    }

    public function fulfill(): array
    {
        $this->fulfillCalls++;

        return $this->work !== null
            ? ($this->work)($this)
            : ['processed' => 3, 'details' => ['source' => 'fixture']];
    }
}
