<?php

namespace App\Enums;

enum ScheduledCommandLockStatus: string
{
    case OK = 'OK';
    case LOCKED_ACTIVE = 'LOCKED (active)';
    case LOCKED = 'LOCKED';
    case ORPHANED = 'ORPHANED';
    case RELEASED = 'RELEASED';
    case RELEASE_FAILED = 'RELEASE FAILED';
}
