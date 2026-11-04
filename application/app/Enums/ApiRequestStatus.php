<?php

namespace App\Enums;

enum ApiRequestStatus: string
{
    case PENDING = 'pending';
    case PROCESSED = 'processed';
}
