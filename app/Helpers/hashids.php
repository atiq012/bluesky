<?php

use App\Services\HashIdService;

function hashid_encode(string $connection, int $id): string
{
    return app(HashIdService::class)->encode($connection, $id);
}

function hashid_decode(string $connection, string $hash): ?int
{
    return app(HashIdService::class)->decode($connection, $hash);
}
