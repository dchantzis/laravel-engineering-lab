<?php

namespace App\Enums;

enum QueueConnection: string
{
    case SYNC = 'sync';
    case DATABASE = 'database';
    case BEANSTALKD = 'beanstalkd';
    case SQS = 'sqs';
    case REDIS = 'redis';

    public static function default(): self
    {
        return self::from(config('queue.default'));
    }
}
