# Base console command lifecycle tests

Run from the repository root:

```sh
docker compose exec -T app php vendor/bin/phpunit --do-not-cache-result tests/Feature/Console/CommandTest.php
```

Or, from `application/`, with a PHP runtime that has `pdo_sqlite`:

```sh
php vendor/bin/phpunit --do-not-cache-result tests/Feature/Console/CommandTest.php
```

These feature tests use the existing SQLite in-memory database configuration.
They exercise the inherited public `run()` method with a small command fixture.
Slack notifications are faked; the delivery failure test replaces the notification
dispatcher with a throwing mock. No external notifications are delivered.

`tests/Support/Console/Heartbeat.php` provides namespaced function replacements
for the base command's unqualified PCNTL calls. While this suite is active, they
capture handlers and alarm requests without installing signal handlers, changing
asynchronous signal settings, or scheduling real alarms. Tests invoke the captured
heartbeat callback synchronously. Outside these tests, the replacements delegate
to PHP's original functions. Tests restore this switch, frozen time, and the cached
`LogSystem` console service after each case.

The unavailable-PCNTL branch is simulated on every platform. Tests requiring
signal constants skip on platforms without them. Real operating-system signal
delivery, process termination, scheduler lock recovery, and watchdog integration
are outside this suite.

## Current failing contracts

These tests deliberately assert the requested best-effort contracts; they do not
expect or hide known production failures.

- `test_end_failure_does_not_replace_the_original_execution_exception`:
  `app/Console/Commands/Command.php:113` reuses `$exception` for an inner logging
  failure, replacing the business exception later thrown at line 134. Use a
  separate variable for the logging exception and rethrow the original instance.
- `test_heartbeat_setup_failure_does_not_prevent_work_from_running`:
  `Command.php:75` calls `startHeartbeat()` before `fulfill()` in the same try
  block. PCNTL setup exceptions are treated as execution failures. Handle heartbeat
  setup failures separately so work continues.
- `test_notification_setup_failure_does_not_interrupt_successful_execution`:
  `Command.php:144` calls `sendToSlack()` without a best-effort boundary. An enabled
  Slack channel with a missing URL raises a `TypeError` at
  `app/Services/Slack.php:66`, before its delivery catch block. Protect notification
  setup and dispatch so successful work retains a successful exit status.

No production fixes are included.
