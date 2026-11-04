<?php

namespace App\Enums;

enum SlackMessageLevel: string
{
    case INFO = 'info';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case ERROR = 'error';
}
