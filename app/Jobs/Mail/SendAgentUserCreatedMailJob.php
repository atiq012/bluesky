<?php

namespace App\Jobs\Mail;

use App\Mail\User\AgentUserCreatedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendAgentUserCreatedMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = 30;

    public function __construct(
        public string $recipientEmail,
        public string $userName,
        public string $agencyName,
        public string $username,
        public string $phone,
        public string $department,
        public string $designation,
        public string $defaultPassword,
        public string $portalUrl,
        public string $createdByName,
    ) {}

    public function handle(): void
    {
        if (! filter_var($this->recipientEmail, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Agent user created mail skipped: invalid recipient email', [
                'email' => $this->recipientEmail,
            ]);

            return;
        }

        try {
            Mail::to($this->recipientEmail)->send(new AgentUserCreatedMail(
                userName: $this->userName,
                agencyName: $this->agencyName,
                username: $this->username,
                phone: $this->phone,
                department: $this->department,
                designation: $this->designation,
                defaultPassword: $this->defaultPassword,
                portalUrl: $this->portalUrl,
                createdByName: $this->createdByName,
            ));
        } catch (\Throwable $e) {
            Log::error('Agent user created mail send failed', [
                'email' => $this->recipientEmail,
                'error' => $e->getMessage(),
            ]);

            // Under a real worker ($this->job set) rethrow so the retries above apply.
            // Running after the HTTP response there is no job and nobody to report to,
            // so the log entry is all we can leave behind.
            if ($this->job) {
                throw $e;
            }
        }
    }
}
