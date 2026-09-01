<?php

namespace App\Jobs;

use App\Services\PusherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BroadcastResourceEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $channelName,
        public string $event,
        public array $payload,
    ) {}

    public function handle(PusherService $pusher): void
    {
        $pusher->publishToPublic($this->channelName, $this->event, $this->payload);
    }
}
