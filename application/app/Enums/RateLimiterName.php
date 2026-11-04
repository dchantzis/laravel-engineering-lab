<?php

namespace App\Enums;

enum RateLimiterName: string
{
    case API_DEFAULT = 'apiDefault';
    case API_ADMIN = 'apiAdmin';
    case WEB_RATE_LIMIT = 'webRateLimit';
}
