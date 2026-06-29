<?php

namespace App\Services;

use Ably\AblyRest;
use Illuminate\Support\Facades\Log;

class AblyService
{
    private ?AblyRest $client = null;

    // Public channel publish for realtime list invalidation (see docs/REALTIME_CRUD_EVENTS.md)
    public function publishToPublic(string $channelName, string $event, array $data): bool
    {
        $key = config('services.ably.key');
        if ($key === null || $key === '') {
            Log::warning('Ably publish skipped: ABLY_KEY not configured.', [
                'channel' => $channelName,
                'event' => $event,
            ]);

            return false;
        }

        try {
            $this->client()->channels->get($channelName)->publish($event, $data);

            return true;
        } catch (\Throwable $e) {
            Log::error('Ably publish failed.', [
                'channel' => $channelName,
                'event' => $event,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function client(): AblyRest
    {
        if ($this->client === null) {
            $this->client = new AblyRest(config('services.ably.key'));
        }

        return $this->client;
    }
}
