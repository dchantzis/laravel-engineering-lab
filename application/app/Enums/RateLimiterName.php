<?php

namespace App\Enums;

enum RateLimiterName: string
{
    case WEB_RATE_LIMIT = 'webRateLimit';
    case API_DEFAULT = 'apiDefault';
    case API_ADMIN = 'apiAdmin';
    case API_CLIENT_CREDENTIALS = 'apiClientCredentials';
}
