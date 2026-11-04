<?php

namespace App\Enums;

use App\Traits\Enums\HasValues;

enum DatabaseConnection: string
{
    use HasValues;

    public const string APPLICATION = 'laravel_engineering_lab';

    case APPLICATION_VALUE = self::APPLICATION;
}
