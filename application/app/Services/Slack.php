<?php

namespace App\Services;

use App\Enums\SlackMessageLevel;
use App\Notifications\SlackNotification;
use Error;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class Slack
{
    private static function send(
        string $slackRoute,
        SlackNotification $notification
    ): void {
        if (config('engineering.log_slack_notifications_enabled') === false) {
            return;
        }

        try {
            Notification::route(
                'slack',
                $slackRoute
            )->notify(
                $notification
            );
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
        ?SlackMessageLevel $level = null,
    ): void {
        self::send(
            config('logging.channels.slack.url'),
            (new SlackNotification(
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
        ?SlackMessageLevel $level = SlackMessageLevel::INFO,
    ): void {
        if (! config('logging.channels.slack_scheduled_command.enabled')) {
            return;
        }

        self::send(
            config('logging.channels.slack_scheduled_command.url'),
            (new SlackNotification(
                $title,
                $content,
                $level,
            ))
        );
    }
}
