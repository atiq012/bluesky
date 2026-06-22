<?php

namespace App\Services\SearchV2;

use Exception;
use App\Models\BookingAttempt;
use App\Models\BookingSession;

class TpV2BookingCompanyAgencyService
{
    public function __construct(
        private readonly TpV2TravelAgencyService $travelAgencyService,
        private readonly CompanyTravelAgencyResolver $companyResolver
    ) {}

    public function ensureApplied(BookingAttempt $attempt, int|string|null $userId = null): array
    {
        if ($this->alreadyApplied($attempt)) {
            return ['skipped' => true];
        }

        $workbenchId = trim((string) ($attempt->workbench_identifier ?? ''));
        $sessionId   = (int) ($attempt->booking_workbench_session_id ?? 0);

        if ($workbenchId === '' || $sessionId <= 0) {
            throw new Exception('Workbench session missing. Restart booking from search.');
        }

        $agency = $this->companyResolver->resolveAgencyPayload();

        $result = $this->travelAgencyService->save([
            'workbench_identifier' => $workbenchId,
            'session_id'           => $sessionId,
            'agency'               => $agency,
        ], $userId);

        return [
            'skipped'             => false,
            'company_agency'      => $agency,
            'travelport_response' => $result['travelport_response'] ?? null,
        ];
    }

    private function alreadyApplied(BookingAttempt $attempt): bool
    {
        return BookingSession::query()
            ->where('booking_attempt_id', $attempt->id)
            ->where('session_type', 'add_travel_agency')
            ->where('status', 'success')
            ->exists();
    }
}
