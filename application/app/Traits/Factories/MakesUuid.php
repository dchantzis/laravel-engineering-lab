<?php

namespace App\Traits\Factories;

use Illuminate\Support\Str;
use Ramsey\Uuid\Exception\InvalidUuidStringException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

trait MakesUuid
{
    protected static function makeUuid(
        null|string|UuidInterface $data = null,
        bool $makeIfNull = true,
        bool $isOrdered = false,
    ): UuidInterface {
        if ($data instanceof UuidInterface) {
            return $data;
        }

        if (is_string($data)) {
            return Uuid::fromString($data);
        }

        if ($makeIfNull === false) {
            throw new InvalidUuidStringException('Failed to make UUID from NULL');
        }

        return $isOrdered === true ? Str::orderedUuid() : Uuid::uuid4();
    }

    protected static function tryMakeUuid(
        mixed $data = null,
        bool $makeIfNull = true,
        bool $isOrdered = false,
    ): ?UuidInterface {
        if ($data === null && $makeIfNull === false) {
            return null;
        }

        try {
            return static::makeUuid($data, makeIfNull: true, isOrdered: $isOrdered);
        } catch (InvalidUuidStringException $exception) {
            return null;
        }
    }
}
