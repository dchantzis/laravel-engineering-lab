<?php

namespace App\Traits;

trait ModelGetDatabaseConnectionName
{
    protected static function getDatabaseConnectionName(): ?string
    {
        return (new self)->getConnectionName();
    }
}
