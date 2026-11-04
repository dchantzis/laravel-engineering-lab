<?php

namespace Tests\Support\Console {
    use Closure;
    use Throwable;

    /**
     * Intercept only the base command's unqualified PCNTL calls while this suite runs.
     * The registered callback is invoked synchronously by tests, never by a signal.
     */
    final class Heartbeat
    {
        public static bool $active = false;

        public static bool $available = false;

        /** @var list<int> */
        public static array $alarms = [];

        /** @var list<callable|int> */
        public static array $handlers = [];

        public static ?Closure $callback = null;

        public static ?Throwable $startFailure = null;

        public static ?Throwable $stopFailure = null;

        public static function reset(bool $active = false): void
        {
            self::$active = $active;
            self::$available = defined('SIGALRM');
            self::$alarms = [];
            self::$handlers = [];
            self::$callback = null;
            self::$startFailure = null;
            self::$stopFailure = null;
        }
    }
}

namespace App\Console\Commands {
    use Closure;
    use Tests\Support\Console\Heartbeat;

    function extension_loaded(string $extension): bool
    {
        return Heartbeat::$active && $extension === 'pcntl'
            ? Heartbeat::$available
            : \extension_loaded($extension);
    }

    function pcntl_async_signals(?bool $enable = null): bool
    {
        if (! Heartbeat::$active) {
            return \pcntl_async_signals($enable);
        }

        if (Heartbeat::$startFailure !== null) {
            throw Heartbeat::$startFailure;
        }

        return true;
    }

    function pcntl_signal(int $signal, callable|int $handler, bool $restartSyscalls = true): bool
    {
        if (! Heartbeat::$active) {
            return \pcntl_signal($signal, $handler, $restartSyscalls);
        }

        Heartbeat::$handlers[] = $handler;

        if ($handler instanceof Closure) {
            Heartbeat::$callback = $handler;
        }

        return true;
    }

    function pcntl_alarm(int $seconds): int
    {
        if (! Heartbeat::$active) {
            return \pcntl_alarm($seconds);
        }

        Heartbeat::$alarms[] = $seconds;

        if ($seconds === 0 && Heartbeat::$stopFailure !== null) {
            throw Heartbeat::$stopFailure;
        }

        return 0;
    }
}
