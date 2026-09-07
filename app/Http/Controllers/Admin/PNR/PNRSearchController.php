<?php

namespace App\Http\Controllers\Admin\PNR;

use App\Http\Controllers\BaseController;
use App\Models\Agent\Agent;
use App\Models\BookingAttempt;
use App\Services\SearchV2\BookingListMapper;
use App\Services\SearchV2\BookingSnapshotBuilder;
use App\Services\SearchV2\TravelportFareRulesService;
use Illuminate\Http\Request;

class PNRSearchController extends BaseController
{
    public function __construct(
        private readonly BookingSnapshotBuilder $snapshotBuilder,
        private readonly TravelportFareRulesService $fareRulesService,
    ) {}

    public function search(Request $request)
    {
        $validated = $request->validate([
            'pnr' => ['required', 'string', 'max:30'],
        ]);

        $pnr = strtoupper(trim($validated['pnr']));

        // Group orWhere so later filters cannot widen PNR match across rows
        $attempt = BookingAttempt::query()
            ->where(function ($q) use ($pnr) {
                $q->where('gds_pnr', $pnr)->orWhere('airline_pnr', $pnr);
            })
            ->latest('id')
            ->first();

        if (!$attempt) {
            return $this->ErrorResponse('No booking found for this PNR.', [], 404);
        }

        // Same agency scope as booking detail — other agency PNR → pretend missing (no existence leak)
        if (!BookingListMapper::userCanAccessAttempt($attempt, $request->user())) {
            return $this->ErrorResponse('No booking found for this PNR.', [], 404);
        }

        $snapshot = $this->snapshotBuilder->build($attempt);
        $fareRuleSegments = $this->fareRulesService->getSavedFareRulesForAttempt($attempt->id);

        $agencyUserId = $attempt->user_id ?: $attempt->created_by;
        $agencyName = $agencyUserId
            ? Agent::query()->where('user_id', $agencyUserId)->value('name')
            : null;

        $activityLogs = $attempt->activityLogs()
            ->orderByDesc('created_at')
            ->get(['id', 'action_type', 'user_name', 'status_before', 'status_after', 'metadata', 'created_at'])
            ->map(fn ($log) => [
                'id'            => $log->id,
                'action_type'   => $log->action_type,
                'user_name'     => $log->user_name,
                'status_before' => $log->status_before,
                'status_after'  => $log->status_after,
                'metadata'      => $log->metadata,
                'created_at'    => optional($log->created_at)->format('Y-m-d H:i:s'),
            ]);
        // dd($snapshot);
        return $this->SuccessResponse([
            'attempt' => [
                'id'                     => $attempt->id,
                'booking_code'           => BookingListMapper::bookingCode($attempt->id),
                'agency_name'            => $agencyName,
                'status'                 => $attempt->status,
                'gds_pnr'                => $attempt->gds_pnr,
                'airline_pnr'            => $attempt->airline_pnr,
                'airline_code'           => $attempt->airline_code,
                'airline_name'           => $attempt->airline_name,
                'cabin_class'            => $attempt->cabin_class,
                'reservation_identifier' => $attempt->reservation_identifier,
                'ticket_numbers'         => $attempt->ticket_numbers,
                'ticketed_at'            => optional($attempt->ticketed_at)->format('Y-m-d H:i:s'),
                'confirmed_at'           => optional($attempt->confirmed_at)->format('Y-m-d H:i:s'),
                'cancelled_at'           => optional($attempt->cancelled_at)->format('Y-m-d H:i:s'),
                'voided_at'              => optional($attempt->voided_at)->format('Y-m-d H:i:s'),
                'workbench_identifier'   => $attempt->workbench_identifier,
                'commit_error'           => $attempt->commit_error,
                'created_at'             => optional($attempt->created_at)->format('Y-m-d H:i:s'),
            ],
            'price'               => $snapshot['price'],
            'travelers'           => $snapshot['travelers'],
            'fare_rules_segments' => $fareRuleSegments,
            'activity_logs'       => $activityLogs,
        ], 'PNR found.');
    }
}
