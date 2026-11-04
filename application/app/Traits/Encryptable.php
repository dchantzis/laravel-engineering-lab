<?php

namespace App\Traits;

trait Encryptable
{
    public function getAttribute($key): mixed
    {
        $value = parent::getAttribute($key);

        if (! in_array($key, $this->encryptable)) {
            return $value;
        }

        $value = $value
            ? customDecrypter(
                value: $value,
                decryptKey: resolveDatabaseColumnEncryptionKey($this->getConnectionName())
            )
            : null;

        return json_validate($value ?? '')
            ? json_decode($value)
            : $value;
    }

    public function setAttribute($key, $value): mixed
    {
        if (in_array($key, $this->encryptable)) {
            if (is_array($value)) {
                $value = json_encode($value);
            }

            $value = $value
                ? customEncrypter(
                    value: $value,
                    encryptKey: resolveDatabaseColumnEncryptionKey($this->getConnectionName())
                )
                : null;
        }

        return parent::setAttribute($key, $value);
    }

    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();

        if (! property_exists($this, 'encryptable') || ! is_array($this->encryptable)) {
            return $attributes;
        }

        foreach ($this->encryptable as $key) {
            if (! isset($attributes[$key])) {
                continue;
            }

            $attributes[$key] = $attributes[$key]
                ? customDecrypter(
                    value: $attributes[$key],
                    decryptKey: resolveDatabaseColumnEncryptionKey($this->getConnectionName())
                )
                : null;

            $attributes[$key] = json_validate($attributes[$key] ?? '')
                ? json_decode($attributes[$key])
                : $attributes[$key];
        }

        return $attributes;
    }
}
