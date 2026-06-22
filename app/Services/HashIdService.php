<?php

namespace App\Services;

use Hashids\Hashids;

class HashIdService
{
    public const AGENT = 'agent';
    public const BOOKING_ATTEMPT = 'booking_attempt';
    public const SEARCH_LOG = 'search_log';
    public const PRICE_LOG = 'price_log';
    public const BOOKING_SESSION = 'booking_session';

    private array $encoders = [];

    public function encode(string $connection, int $id): string
    {
        return $this->encoder($connection)->encode($id);
    }

    public function decode(string $connection, string $hash): ?int
    {
        if ($hash === '') {
            return null;
        }

        $decoded = $this->encoder($connection)->decode($hash);

        return isset($decoded[0]) ? (int) $decoded[0] : null;
    }

    public function encodeAgentId(int $id): string
    {
        return $this->encode(self::AGENT, $id);
    }

    public function decodeAgentId(string $hash): ?int
    {
        return $this->decode(self::AGENT, $hash);
    }

    private function encoder(string $connection): Hashids
    {
        if (!isset($this->encoders[$connection])) {
            $salt = config('hashids.salt_base') . ':' . $connection;
            $this->encoders[$connection] = new Hashids(
                $salt,
                (int) config('hashids.min_length', 8),
                (string) config('hashids.alphabet')
            );
        }

        return $this->encoders[$connection];
    }
}
