<?php

namespace App\Enums;

enum TeamsMessageLevel: string
{
    case INFO = 'info';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case ERROR = 'error';

    /**
     * Get the Adaptive Card color for this message level.
     *
     * @see https://adaptivecards.io/explorer/TextBlock.html
     */
    public function toAdaptiveCardColor(): string
    {
        return match ($this) {
            self::INFO => 'default',
            self::SUCCESS => 'good',
            self::WARNING => 'warning',
            self::ERROR => 'attention',
        };
    }

    /**
     * Get the hex color for the accent bar in message cards.
     */
    public function toHexColor(): string
    {
        return match ($this) {
            self::INFO => '0078D4',
            self::SUCCESS => '2DC72D',
            self::WARNING => 'FFA500',
            self::ERROR => 'D13438',
        };
    }
}