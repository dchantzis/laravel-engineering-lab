<?php

namespace App\Traits;

trait ModelGetTableName
{
    protected static function getTableName(): string
    {
        return (new self)->getTable();
    }
}
