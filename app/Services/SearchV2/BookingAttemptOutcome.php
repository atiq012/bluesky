<?php

namespace App\Services\SearchV2;

use App\Models\BookingAttempt;

class BookingAttemptOutcome
{
    public const STAGE_SEARCH         = 'search';
    public const STAGE_PRICE          = 'price';
    public const STAGE_WORKBENCH      = 'workbench';
    public const STAGE_ADD_OFFER      = 'add_offer';
    public const STAGE_TRAVELERS      = 'travelers';
    public const STAGE_TRAVEL_AGENCY  = 'travel_agency';
    public const STAGE_SSR            = 'ssr';
    public const STAGE_ANCILLARY      = 'ancillary';
    public const STAGE_REVIEW         = 'review';
    public const STAGE_CONFIRMED      = 'confirmed';
    public const STAGE_COMMIT         = 'commit';
    public const STAGE_COMMITTED      = 'committed';
    public const STAGE_CLOSED_SEARCH  = 'closed_search';
    public const STAGE_CLOSED_PRICE   = 'closed_price';

    public static function record(
        BookingAttempt|int|null $attempt,
        string $closingStage,
        string $apiStep,
        string $apiStatus,
        ?string $errorMessage = null,
        int|string|null $userId = null
    ): void {
        if ($attempt === null) {
            return;
        }

        $model = $attempt instanceof BookingAttempt ? $attempt : BookingAttempt::find($attempt);
        if (!$model) {
            return;
        }

        $model->update([
            'closing_stage'   => $closingStage,
            'last_api_step'   => $apiStep,
            'last_api_status' => $apiStatus,
            'last_api_error'  => $apiStatus === 'error' ? $errorMessage : null,
            'last_api_at'     => now(),
            'updated_by'      => $userId ?? $model->updated_by,
        ]);
    }

    public static function recordFromSession(
        int $attemptId,
        string $sessionType,
        string $apiStatus,
        ?string $errorMessage = null,
        int|string|null $userId = null
    ): void {
        self::record(
            $attemptId,
            self::stageFromSessionType($sessionType),
            $sessionType,
            $apiStatus,
            $errorMessage,
            $userId
        );
    }

    public static function stageFromSessionType(string $sessionType): string
    {
        return match ($sessionType) {
            'search'                  => self::STAGE_SEARCH,
            'price'                   => self::STAGE_PRICE,
            'reservation_workbench'   => self::STAGE_WORKBENCH,
            'add_offer'               => self::STAGE_ADD_OFFER,
            'add_traveler'            => self::STAGE_TRAVELERS,
            'add_travel_agency'       => self::STAGE_TRAVEL_AGENCY,
            'add_ssr_meal',
            'add_ssr_wheelchair'      => self::STAGE_SSR,
            'add_ancillary'           => self::STAGE_ANCILLARY,
            'pre_commit_snapshot'     => self::STAGE_REVIEW,
            'commit'                  => self::STAGE_COMMIT,
            'post_commit_snapshot'    => self::STAGE_COMMITTED,
            default                   => $sessionType,
        };
    }

    public static function stageLabel(?string $stage): string
    {
        return match ($stage) {
            self::STAGE_SEARCH        => 'Search',
            self::STAGE_PRICE         => 'Price',
            self::STAGE_WORKBENCH     => 'Workbench',
            self::STAGE_ADD_OFFER     => 'Add Offer',
            self::STAGE_TRAVELERS     => 'Travelers',
            self::STAGE_TRAVEL_AGENCY => 'Travel Agency',
            self::STAGE_SSR           => 'SSR',
            self::STAGE_ANCILLARY     => 'Ancillary',
            self::STAGE_REVIEW        => 'Review',
            self::STAGE_CONFIRMED     => 'Confirmed',
            self::STAGE_COMMIT        => 'Commit',
            self::STAGE_COMMITTED     => 'Committed',
            self::STAGE_CLOSED_SEARCH => 'Closed at Search',
            self::STAGE_CLOSED_PRICE  => 'Closed at Price',
            default                   => $stage ? ucwords(str_replace('_', ' ', $stage)) : '—',
        };
    }

    public static function apiStatusLabel(?string $status): string
    {
        return match ($status) {
            'success' => 'Success',
            'error'   => 'Error',
            default   => $status ? ucfirst($status) : '—',
        };
    }

    public static function stageFromAttemptStatus(string $status): ?string
    {
        return match ($status) {
            'searching'          => self::STAGE_SEARCH,
            'priced'             => self::STAGE_PRICE,
            'in_progress'        => self::STAGE_WORKBENCH,
            'ready_for_review'   => self::STAGE_REVIEW,
            'confirmed'          => self::STAGE_CONFIRMED,
            'committed'          => self::STAGE_COMMITTED,
            'complete_on_search' => self::STAGE_CLOSED_SEARCH,
            'complete_on_price'  => self::STAGE_CLOSED_PRICE,
            default              => null,
        };
    }
}
