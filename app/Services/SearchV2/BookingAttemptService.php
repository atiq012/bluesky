<?php

namespace App\Services\SearchV2;

use Exception;
use App\Models\BookingAttempt;
use App\Models\BookingPriceLog;
use App\Models\BookingSearchLog;

class BookingAttemptService
{
    public function __construct(
        private readonly BookingSnapshotBuilder $snapshotBuilder,
        private readonly TpV2BookingCompanyAgencyService $companyAgencyService,
        private readonly BookingSnapshotRecorder $snapshotRecorder,
        private readonly BookingActivityLogger $activityLogger,
    ) {}

    public function resolvePriceLog(int $priceLogId): BookingPriceLog
    {
        $log = BookingPriceLog::find($priceLogId);
        if (!$log) {
            throw new Exception('Price log not found.');
        }

        return $log;
    }

    public function createForSearch(BookingSearchLog $searchLog, int|string|null $userId = null): BookingAttempt
    {
        $actor = $userId ?? $searchLog->user_id;

        $attempt = BookingAttempt::create([
            'user_id'               => $actor,
            'status'                => 'searching',
            'booking_search_log_id' => $searchLog->id,
            'closing_stage'         => BookingAttemptOutcome::STAGE_SEARCH,
            'last_api_step'         => 'search',
            'last_api_status'       => $searchLog->status === 'error' ? 'error' : 'success',
            'last_api_error'        => $searchLog->error_message,
            'last_api_at'           => now(),
            'created_by'            => $actor,
            'updated_by'            => $actor,
        ]);

        return $attempt;
    }

    public function attachPriceToAttempt(
        BookingPriceLog $priceLog,
        ?int $attemptId = null,
        ?array $selectionJson = null,
        int|string|null $userId = null
    ): BookingAttempt {
        $attempt = $attemptId ? BookingAttempt::find($attemptId) : null;

        if (!$attempt && $priceLog->booking_search_log_id) {
            $attempt = BookingAttempt::query()
                ->where('booking_search_log_id', $priceLog->booking_search_log_id)
                ->when($userId, fn($q) => $q->where('user_id', $userId))
                ->whereIn('status', ['searching', 'priced', 'complete_on_price'])
                ->orderByDesc('id')
                ->first();
        }

        if ($attempt) {
            $attempt->update([
                'booking_price_log_id' => $priceLog->id,
                'selection_json'       => $selectionJson ?? $priceLog->selection_json ?? $attempt->selection_json,
                'status'               => 'priced',
                'updated_by'           => $userId,
            ]);
        } else {
            $actor = $userId ?? $priceLog->user_id;

            $attempt = BookingAttempt::create([
                'user_id'               => $actor,
                'status'                => 'priced',
                'booking_search_log_id' => $priceLog->booking_search_log_id,
                'booking_price_log_id'  => $priceLog->id,
                'selection_json'        => $selectionJson ?? $priceLog->selection_json,
                'created_by'            => $actor,
                'updated_by'            => $actor,
            ]);
        }

        $priceLog->update(['booking_attempt_id' => $attempt->id]);

        BookingAttemptOutcome::record(
            $attempt,
            BookingAttemptOutcome::STAGE_PRICE,
            'price',
            $priceLog->status === 'error' ? 'error' : 'success',
            $priceLog->error_message,
            $userId
        );

        return $attempt->fresh();
    }

    public function resolveAttemptForPriceLog(BookingPriceLog $priceLog): ?BookingAttempt
    {
        if (!$priceLog->booking_attempt_id) {
            return null;
        }

        return BookingAttempt::find($priceLog->booking_attempt_id);
    }

    public function createForWorkbench(
        int $priceLogId,
        ?array $selectionJson,
        int|string|null $userId = null
    ): BookingAttempt {
        $priceLog = $this->resolvePriceLog($priceLogId);
        $attempt  = $this->resolveAttemptForPriceLog($priceLog);

        if ($attempt) {
            $attempt->update([
                'selection_json' => $selectionJson ?? $attempt->selection_json,
                'status'         => 'in_progress',
                'updated_by'     => $userId,
            ]);

            return $attempt->fresh();
        }

        $attempt = BookingAttempt::create([
            'user_id'                 => $userId,
            'status'                  => 'in_progress',
            'booking_search_log_id'   => $priceLog->booking_search_log_id,
            'booking_price_log_id'    => $priceLog->id,
            'selection_json'          => $selectionJson,
            'created_by'              => $userId,
            'updated_by'              => $userId,
        ]);

        $priceLog->update(['booking_attempt_id' => $attempt->id]);

        return $attempt;
    }

    public function completeOnSearch(BookingAttempt $attempt, int|string|null $userId = null): BookingAttempt
    {
        if ($attempt->status !== 'searching') {
            return $attempt;
        }

        $attempt->update([
            'status'        => 'complete_on_search',
            'closing_stage' => BookingAttemptOutcome::STAGE_CLOSED_SEARCH,
            'updated_by'    => $userId,
        ]);

        return $attempt->fresh();
    }

    public function completeOnPrice(BookingAttempt $attempt, int|string|null $userId = null): BookingAttempt
    {
        if ($attempt->status !== 'priced') {
            return $attempt;
        }

        $statusBefore = $attempt->status;

        $attempt->update([
            'status'        => 'complete_on_price',
            'closing_stage' => BookingAttemptOutcome::STAGE_CLOSED_PRICE,
            'updated_by'    => $userId,
        ]);

        $this->activityLogger->log(
            $attempt,
            BookingActivityLogger::ACTION_PROCEED_TO_BOOKING,
            $userId,
            [],
            $statusBefore,
            'complete_on_price',
        );

        return $attempt->fresh();
    }

    public function attachWorkbenchSession(BookingAttempt $attempt, int $sessionId, string $workbenchIdentifier): void
    {
        $attempt->update([
            'booking_workbench_session_id' => $sessionId,
            'workbench_identifier'         => $workbenchIdentifier,
            'updated_by'                   => $attempt->updated_by,
        ]);
    }

    public function prepareReview(BookingAttempt $attempt, int|string|null $userId = null): array
    {
        $this->companyAgencyService->ensureApplied($attempt, $userId);

        $snapshot = $this->snapshotBuilder->build($attempt);
        $this->snapshotRecorder->recordPreCommit($attempt, $snapshot, $userId);

        $attempt->update([
            'snapshot_json' => $snapshot,
            'status'        => 'ready_for_review',
            'updated_by'    => $userId,
        ]);

        BookingAttemptOutcome::record(
            $attempt,
            BookingAttemptOutcome::STAGE_REVIEW,
            'pre_commit_snapshot',
            'success',
            null,
            $userId
        );

        return $snapshot;
    }

    public function confirm(BookingAttempt $attempt, int|string|null $userId = null): BookingAttempt
    {
        if ($attempt->status !== 'ready_for_review') {
            throw new Exception('Booking is not ready for confirmation.');
        }

        $this->companyAgencyService->ensureApplied($attempt, $userId);

        $attempt->update([
            'status'       => 'confirmed',
            'confirmed_at' => now(),
            'confirmed_by' => $userId,
            'updated_by'   => $userId,
        ]);

        BookingAttemptOutcome::record(
            $attempt,
            BookingAttemptOutcome::STAGE_CONFIRMED,
            'confirm',
            'success',
            null,
            $userId
        );

        return $attempt->fresh();
    }
}
