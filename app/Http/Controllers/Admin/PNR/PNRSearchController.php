<?php

namespace App\Http\Controllers\Admin\PNR;

use App\Http\Controllers\BaseController;
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

        $pnr  = strtoupper(trim($validated['pnr']));
        $user = $request->user();

        $query = BookingAttempt::query()
            ->where(function ($q) use ($pnr) {
                // MUST be grouped — otherwise the agency scope below
                // is bypassed by the OR and other agencies' PNRs leak
                $q->where('gds_pnr', $pnr)
                  ->orWhere('airline_pnr', $pnr);
            })
            ->latest('id');

        // Same rule as the booking list: only PNRs booked/ticketed
        // inside the user's own agency
        BookingListMapper::applyAgencyScope($query, $user);

        $attempt = $query->first();

        if (!$attempt) {
            return $this->ErrorResponse('No booking found for this PNR.', [], 404);
        }

        // Resolves agency for both owner and staff users (users.agent_id / agents.user_id)
        BookingListMapper::attachAgencyNames([$attempt]);
        BookingListMapper::attachBookerNamesAgent([$attempt]);

        $snapshot         = $this->snapshotBuilder->build($attempt);
        $fareRuleSegments = $this->fareRulesService->getSavedFareRulesForAttempt($attempt->id);

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

        // Who ticketed it: newest log entry that moved the attempt into "ticketed"
        // (adjust the match if your action_type naming differs)
        $ticketedBy = $activityLogs
            ->first(fn ($log) => ($log['status_after'] ?? null) === 'ticketed')['user_name'] ?? null;

        return $this->SuccessResponse([
            'attempt' => [
                'id'                     => $attempt->id,
                'booking_code'           => BookingListMapper::bookingCode($attempt->id),
                'agency_name'            => $attempt->agency_name ?? '—',
                'agency_code'            => $attempt->agency_code,
                'booked_by'              => $attempt->booked_by_name ?? '—',
                'booked_by_id'           => (int) ($attempt->user_id ?: $attempt->created_by),
                'ticketed_by'            => $ticketedBy,
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
