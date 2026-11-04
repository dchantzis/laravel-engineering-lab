<?php

namespace App\DataTransferObjects\User;

readonly class User
{
    public function __construct(
        public ?string $name,
        public ?string $email,
        public bool $isAdmin = false,
    ) {
    }

    public static function fromArray(array $attributes): self
    {
        return new static(
            name: $attributes['name'] ?? null,
            email: $attributes['email'] ?? null,
            isAdmin: $attributes['is_admin'] ?? false,
        );
    }
}
