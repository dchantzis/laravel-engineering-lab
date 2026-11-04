<?php

namespace App\Services;

use App\Enums\MessageLevel;
use App\Enums\NotificationChannel;

class Notifications
{
    private static function getChannel(): NotificationChannel
    {
        $channel = config('engineering.notification_channel', 'slack');

        return NotificationChannel::tryFrom($channel) ?? NotificationChannel::SLACK;
    }

    public static function sendToExceptionChannel(
        array $content,
        ?MessageLevel $level = null,
    ): void {
        $channel = self::getChannel();
        $level = $level ?? MessageLevel::WARNING;

        if ($channel->shouldSendToSlack()) {
            Slack::sendToExceptionChannel($content, $level->toSlackMessageLevel());
        }

        if ($channel->shouldSendToTeams()) {
            Teams::sendToExceptionChannel($content, $level->toTeamsMessageLevel());
        }
    }

    public static function sendToScheduledCommandsLogChannel(
        string $title,
        array $content,
        ?MessageLevel $level = MessageLevel::INFO,
    ): void {
        $channel = self::getChannel();
        $level = $level ?? MessageLevel::INFO;

        if ($channel->shouldSendToSlack()) {
            Slack::sendToScheduledCommandsLogChannel($title, $content, $level->toSlackMessageLevel());
        }

        if ($channel->shouldSendToTeams()) {
            Teams::sendToScheduledCommandsLogChannel($title, $content, $level->toTeamsMessageLevel());
        }
    }
}
