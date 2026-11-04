<?php

namespace Tests\Fixtures\Scheduler;

use Closure;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\EventMutex;

/** Reproduce a concurrent change once, after the real mutex existence check. */
final class InterleavingEventMutex implements EventMutex
{
    public function __construct(private readonly EventMutex $inner, private ?Closure $afterExists) {}

    public function create(Event $event): bool
    {
        return $this->inner->create($event);
    }

    public function exists(Event $event): bool
    {
        $exists = $this->inner->exists($event);
        $callback = $this->afterExists;
        $this->afterExists = null;
        $callback?->__invoke($event);

        return $exists;
    }

    public function forget(Event $event): void
    {
        $this->inner->forget($event);
    }
}
