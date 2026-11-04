<?php

namespace App\Enums;

enum MessageLevel: string
{
    case INFO = 'info';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case ERROR = 'error';

    public function toSlackMessageLevel(): SlackMessageLevel
    {
        return match ($this) {
            self::INFO => SlackMessageLevel::INFO,
            self::SUCCESS => SlackMessageLevel::SUCCESS,
            self::WARNING => SlackMessageLevel::WARNING,
            self::ERROR => SlackMessageLevel::ERROR,
        };
    }

    public function toTeamsMessageLevel(): TeamsMessageLevel
    {
        return match ($this) {
            self::INFO => TeamsMessageLevel::INFO,
            self::SUCCESS => TeamsMessageLevel::SUCCESS,
            self::WARNING => TeamsMessageLevel::WARNING,
            self::ERROR => TeamsMessageLevel::ERROR,
        };
    }
}
