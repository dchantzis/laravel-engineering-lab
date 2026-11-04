<?php

namespace App\Enums;

enum ResponseStatus: string
{
    case STATUS_OK = 'OK';
    case STATUS_ERROR = 'ERROR';
}
