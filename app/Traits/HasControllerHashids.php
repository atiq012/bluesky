<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasControllerHashids
{
    // Override when auto name does not match existing connection (e.g. booking_attempt)
    protected string $hashidConnection = '';

    protected function hashidConnection(): string
    {
        if ($this->hashidConnection !== '') {
            return $this->hashidConnection;
        }

        $basename = class_basename(static::class);
        $name = preg_replace('/Controller$/', '', $basename);

        return Str::snake((string) $name);
    }

    protected function encodeHashid(int $id): string
    {
        return hashid_encode($this->hashidConnection(), $id);
    }

    protected function decodeHashid(string $hash): ?int
    {
        return hashid_decode($this->hashidConnection(), $hash);
    }
}
