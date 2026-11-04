<?php

namespace App\Services;

use App\Enums\TeamsMessageLevel;
use App\Notifications\TeamsNotification;
use Error;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Teams
{
    private static function normalizeContent(array $content): array
    {
        foreach ($content as $key => $line) {
            $content[$key] = jsonEncode($line);
        }

        return $content;
    }

    private static function send(
        string $webhookUrl,
        TeamsNotification $notification
    ): void {
        if (config('engineering.log_teams_notifications_enabled') === false) {
            return;
        }

        try {
            Http::timeout(config('engineering.log_teams_notifications_webhook_timeout_seconds'))
                ->post($webhookUrl, $notification->toAdaptiveCard())
                ->throw();
        } catch (Exception|Error $exception) {
            Log::warning(
                $exception->getMessage(),
                formatExceptionPayload(
                    $exception,
                    $exception->getMessage()
                )
            );
        }
    }

    public static function sendToExceptionChannel(
        array $content,
        ?TeamsMessageLevel $level = null,
    ): void {
        self::send(
            config('logging.channels.teams.url'),
            (new TeamsNotification(
                sprintf(
                    'An exception has been thrown at [%s].',
                    config('app.name')
                ),
                $content,
                $level,
            ))
        );
    }

    public static function sendToScheduledCommandsLogChannel(
        string $title,
        array $content,
        ?TeamsMessageLevel $level = TeamsMessageLevel::INFO,
    ): void {
        if (! config('logging.channels.teams_scheduled_command.enabled')) {
            return;
        }

        self::send(
            config('logging.channels.teams_scheduled_command.url'),
            (new TeamsNotification(
                $title,
                $content,
                $level,
            ))
        );
    }
}
