<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Pusher\Pusher;

class PusherService
{
    private ?Pusher $client = null;

    // Public channel publish for realtime list invalidation (see docs/REALTIME_CRUD_EVENTS.md)
    public function publishToPublic(string $channelName, string $event, array $data): bool
    {
        $key = config('broadcasting.connections.pusher.key');
        if ($key === null || $key === '') {
            Log::warning('Pusher publish skipped: PUSHER_APP_KEY not configured.', [
                'channel' => $channelName,
                'event' => $event,
            ]);

            return false;
        }

        try {
            $this->client()->trigger($channelName, $event, $data);

            return true;
        } catch (\Throwable $e) {
            Log::error('Pusher publish failed.', [
                'channel' => $channelName,
                'event' => $event,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function client(): Pusher
    {
        if ($this->client === null) {
            $this->client = new Pusher(
                config('broadcasting.connections.pusher.key'),
                config('broadcasting.connections.pusher.secret'),
                config('broadcasting.connections.pusher.app_id'),
                config('broadcasting.connections.pusher.options', [])
            );
        }

        return $this->client;
    }
}
