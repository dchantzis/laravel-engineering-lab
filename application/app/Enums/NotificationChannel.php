<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case SLACK = 'slack';
    case TEAMS = 'teams';
    case BOTH = 'both';

    public function shouldSendToSlack(): bool
    {
        return $this === self::SLACK || $this === self::BOTH;
    }

    public function shouldSendToTeams(): bool
    {
        return $this === self::TEAMS || $this === self::BOTH;
    }
}
