<?php

namespace App\Enums;

enum ApiAuditLogType: string
{
    case REQUEST = 'request';
    case RESPONSE = 'response';
    case ASYNC_RESPONSE = 'async_response';
}
