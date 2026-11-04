<?php

namespace App\Enums;

enum Environment: string
{
    case LOCAL = 'local';
    case STAGING = 'staging';
    case PRODUCTION = 'production';

    public static function default(): self
    {
        return self::LOCAL;
    }
}
