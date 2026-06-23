<?php

namespace App\Http\Controllers\Admin\API;

use Exception;
use App\Models\BookingAttempt;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use App\Services\AgentBalanceService;
use App\Services\HashIdService;
use App\Services\SearchV2\TpV2CommitService;
use App\Services\SearchV2\BookingAttemptService;

class BookingAttemptController extends BaseController
{
    public function __construct(
        private readonly BookingAttemptService $attemptService,
        private readonly TpV2CommitService $commitService,
        private readonly AgentBalanceService $balanceService,
    ) {}

    public function prepareReview(string $id)
    {
        $attempt = $this->resolveAttempt($id);
        try {
            $snapshot = $this->attemptService->prepareReview($attempt, optional(auth()->user())->id);

            return $this->SuccessResponse([
                'booking_attempt_id' => hashid_encode(HashIdService::BOOKING_ATTEMPT, (int) $attempt->id),
                'status'             => $attempt->fresh()->status,
                'snapshot'           => $snapshot,
            ], 'Review snapshot prepared.');
        } catch (Exception $e) {
            report($e);
            return $this->ErrorResponse($e->getMessage(), [], 422);
        }
    }

    public function summary(string $id)
    {
        $attempt = $this->resolveAttempt($id);
        $snapshot = $attempt->snapshot_json;
        if (empty($snapshot)) {
            $snapshot = $this->attemptService->prepareReview($attempt, optional(auth()->user())->id);
        }

        return $this->SuccessResponse([
            'booking_attempt_id' => hashid_encode(HashIdService::BOOKING_ATTEMPT, (int) $attempt->id),
            'status'             => $attempt->status,
            'snapshot'           => $snapshot,
        ], 'Booking summary loaded.');
    }

    public function confirm(string $id)
    {
        $attempt = $this->resolveAttempt($id);
        try {
            if (empty($attempt->snapshot_json)) {
                $this->attemptService->prepareReview($attempt, optional(auth()->user())->id);
                $attempt->refresh();
            }

            $attempt = $this->attemptService->confirm($attempt, optional(auth()->user())->id);

            try {
                $commit = $this->commitWithBalance($attempt->fresh(), optional(auth()->user())->id);

                return $this->SuccessResponse(
                    $this->commitSuccessPayload($attempt->fresh(), $commit),
                    'Booking confirmed and committed.'
                );
            } catch (Exception $commitEx) {
                report($commitEx);

                return $this->SuccessResponse(
                    $this->commitPendingPayload($attempt->fresh(), $commitEx->getMessage()),
                    'Booking confirmed. Commit to GDS pending.'
                );
            }
        } catch (Exception $e) {
            report($e);
            return $this->ErrorResponse($e->getMessage(), [], 422);
        }
    }

    public function completeOnSearch(string $id)
    {
        $attempt = $this->resolveAttempt($id);
        $attempt = $this->attemptService->completeOnSearch($attempt, optional(auth()->user())->id);

        return $this->SuccessResponse([
            'booking_attempt_id' => hashid_encode(HashIdService::BOOKING_ATTEMPT, (int) $attempt->id),
            'status'             => $attempt->status,
        ], 'Search session completed.');
    }

    public function completeOnPrice(string $id)
    {
        $attempt = $this->resolveAttempt($id);
        $attempt = $this->attemptService->completeOnPrice($attempt, optional(auth()->user())->id);

        return $this->SuccessResponse([
            'booking_attempt_id' => hashid_encode(HashIdService::BOOKING_ATTEMPT, (int) $attempt->id),
            'status'             => $attempt->status,
        ], 'Price session completed.');
    }

    public function retryCommit(string $id)
    {
        $attempt = $this->resolveAttempt($id);

        if (!in_array($attempt->status, ['confirmed', 'committed'], true)) {
            return $this->ErrorResponse('Booking must be confirmed before retrying GDS commit.', [], 422);
        }

        if (!empty($attempt->gds_pnr)) {
            return $this->SuccessResponse(
                $this->commitSuccessPayload($attempt, [
                    'pnr'                    => $attempt->gds_pnr,
                    'gds_pnr'                => $attempt->gds_pnr,
                    'airline_pnr'            => $attempt->airline_pnr,
                    'reservation_identifier' => $attempt->reservation_identifier,
                    'reservation_status'     => null,
                    'travelport_response'    => null,
                ]),
                'PNR already exists for this booking.'
            );
        }

        try {
            $commit = $this->commitWithBalance($attempt, optional(auth()->user())->id);

            return $this->SuccessResponse(
                $this->commitSuccessPayload($attempt->fresh(), $commit),
                'GDS commit successful.'
            );
        } catch (Exception $e) {
            report($e);
            $attempt->refresh();

            if (TpV2CommitService::isWorkbenchExpiredMessage($e->getMessage())) {
                return $this->ErrorResponse($e->getMessage(), [
                    'workbench_expired' => true,
                    'commit_pending'    => true,
                ], 422);
            }

            return $this->SuccessResponse(
                $this->commitPendingPayload($attempt, $e->getMessage()),
                'GDS commit failed.'
            );
        }
    }

    private function commitWithBalance(BookingAttempt $attempt, ?int $userId): array
    {
        $agent = $this->balanceService->resolveAgentForUser($attempt->user_id);
        $amount = $this->balanceService->resolveBookingAmount($attempt);

        if ($agent) {
            $this->balanceService->assertSufficientBalance($agent, $amount);
        }

        $commit = $this->commitService->commit($attempt, $userId);
        $attempt->refresh();

        if ($agent) {
            $this->balanceService->debitForBooking($agent, $amount, $attempt, $userId);
        }

        return $commit;
    }

    private function commitSuccessPayload(BookingAttempt $attempt, array $commit): array
    {
        return [
            'booking_attempt_id'     => hashid_encode(HashIdService::BOOKING_ATTEMPT, (int) $attempt->id),
            'status'                 => $attempt->status,
            'commit_pending'         => false,
            'commit_error'           => null,
            'pnr'                    => $commit['gds_pnr'] ?? $commit['pnr'] ?? $attempt->gds_pnr,
            'gds_pnr'                => $commit['gds_pnr'] ?? $attempt->gds_pnr,
            'airline_pnr'            => $commit['airline_pnr'] ?? $attempt->airline_pnr,
            'reservation_identifier' => $commit['reservation_identifier'] ?? $attempt->reservation_identifier,
            'reservation_status'     => $commit['reservation_status'] ?? null,
            'travelport_response'    => $commit['travelport_response'] ?? null,
        ];
    }

    private function commitPendingPayload(BookingAttempt $attempt, string $message): array
    {
        $expired = TpV2CommitService::isWorkbenchExpiredMessage($attempt->commit_error ?? $message);

        return [
            'booking_attempt_id'     => hashid_encode(HashIdService::BOOKING_ATTEMPT, (int) $attempt->id),
            'status'                 => $attempt->status,
            'commit_pending'         => true,
            'workbench_expired'      => $expired,
            'commit_error'           => $attempt->commit_error ?? $message,
            'pnr'                    => $attempt->gds_pnr,
            'gds_pnr'                => $attempt->gds_pnr,
            'airline_pnr'            => $attempt->airline_pnr,
            'reservation_identifier' => $attempt->reservation_identifier,
            'travelport_response'    => $this->latestCommitResponse($attempt),
            'message'                => $message,
        ];
    }

    private function latestCommitResponse(BookingAttempt $attempt): ?array
    {
        $session = $attempt->sessions()
            ->where('session_type', 'commit')
            ->orderByDesc('id')
            ->first();

        $payload = $session?->response_payload;
        if (is_array($payload) && $payload !== []) {
            return $payload;
        }

        return null;
    }

    private function resolveAttempt(string $id): BookingAttempt
    {
        $attemptId = hashid_decode(HashIdService::BOOKING_ATTEMPT, $id);
        if (!$attemptId) {
            abort(404, 'Booking attempt not found.');
        }

        $userId = optional(auth()->user())->id;
        $attempt = BookingAttempt::query()
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->find($attemptId);

        if (!$attempt) {
            abort(404, 'Booking attempt not found.');
        }

        return $attempt;
    }
}
