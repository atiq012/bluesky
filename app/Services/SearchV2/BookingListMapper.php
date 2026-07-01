<?php

namespace App\Services\SearchV2;

use App\Models\Agent\Agent;
use App\Models\BookingAttempt;
use App\Models\BookingSession;
use App\Models\User;
use App\Services\HashIdService;
use Carbon\Carbon;

class BookingListMapper
{
    public const LIST_STATUS_CONFIRMED         = 'confirmed';
    public const LIST_STATUS_BOOKING_CONFIRMED = 'booking_confirmed';
    public const LIST_STATUS_BOOKING_FAILED    = 'booking_failed';
    public const LIST_STATUS_TICKETING         = 'ticketing';
    public const LIST_STATUS_TICKETED          = 'ticketed';
    public const LIST_STATUS_CANCELLED         = 'cancelled';
    public const LIST_STATUS_VOIDED            = 'voided';

    public static function bookingListDbStatuses(): array
    {
        return [
            self::LIST_STATUS_CONFIRMED,
            'committed',
            self::LIST_STATUS_TICKETING,
            self::LIST_STATUS_TICKETED,
            self::LIST_STATUS_BOOKING_FAILED,
            self::LIST_STATUS_CANCELLED,
            self::LIST_STATUS_VOIDED,
        ];
    }

    public static function applyBookingListScope($query): void
    {
        $query->whereIn('status', self::bookingListDbStatuses());
    }

    public static function resolveListStatus(BookingAttempt $row): array
    {
        $status = (string) $row->status;

        if ($status === self::LIST_STATUS_VOIDED) {
            return ['label' => 'Voided', 'raw' => self::LIST_STATUS_VOIDED];
        }

        if ($status === self::LIST_STATUS_CANCELLED) {
            return ['label' => 'Cancelled', 'raw' => self::LIST_STATUS_CANCELLED];
        }

        if ($status === self::LIST_STATUS_TICKETED) {
            return ['label' => 'Ticketed', 'raw' => self::LIST_STATUS_TICKETED];
        }

        if ($status === self::LIST_STATUS_TICKETING) {
            return ['label' => 'Ticketing', 'raw' => self::LIST_STATUS_TICKETING];
        }

        if ($status === self::LIST_STATUS_BOOKING_FAILED) {
            return ['label' => 'Booking Failed', 'raw' => self::LIST_STATUS_BOOKING_FAILED];
        }

        if ($status === 'committed') {
            return ['label' => 'Booking Confirmed', 'raw' => self::LIST_STATUS_BOOKING_CONFIRMED];
        }

        if ($status === self::LIST_STATUS_CONFIRMED) {
            if (!empty($row->commit_error)) {
                return ['label' => 'Booking Failed', 'raw' => self::LIST_STATUS_BOOKING_FAILED];
            }

            return ['label' => 'Confirmed', 'raw' => self::LIST_STATUS_CONFIRMED];
        }

        return [
            'label' => self::statusLabel($status),
            'raw'   => $status,
        ];
    }

    public static function bookingCode(int $id): string
    {
        return 'BS' . str_pad((string) $id, 11, '0', STR_PAD_LEFT);
    }

    public static function extractLocators(?array $commitResponse): array
    {
        $reservation = data_get($commitResponse, 'ReservationResponse.Reservation', []);
        $receipts    = data_get($reservation, 'Receipt', []);
        if (!is_array($receipts)) {
            return ['gds_pnr' => null, 'airline_pnr' => null];
        }

        if (isset($receipts['@type'])) {
            $receipts = [$receipts];
        }

        $gdsPnr     = null;
        $airlinePnr = null;

        foreach ($receipts as $receipt) {
            $loc = data_get($receipt, 'Confirmation.Locator');
            if (empty($loc['value'])) {
                continue;
            }
            $val    = strtoupper(trim((string) $loc['value']));
            $source = (string) ($loc['source'] ?? '');
            if ($source === '1G') {
                $gdsPnr = $val;
            } elseif ($airlinePnr === null) {
                $airlinePnr = $val;
            }
        }

        return ['gds_pnr' => $gdsPnr, 'airline_pnr' => $airlinePnr];
    }

    public static function extractPaymentDeadline(?array $commitResponse): ?string
    {
        $reservation = data_get($commitResponse, 'ReservationResponse.Reservation', []);
        $offer       = data_get($reservation, 'Offer.0', data_get($reservation, 'Offer'));
        $terms       = data_get($offer, 'TermsAndConditionsFull', []);
        if (!is_array($terms)) {
            return null;
        }
        if (isset($terms['@type'])) {
            $terms = [$terms];
        }
        foreach ($terms as $term) {
            if (!empty($term['PaymentTimeLimit'])) {
                return (string) $term['PaymentTimeLimit'];
            }
            if (!empty($term['ExpiryDate'])) {
                return (string) $term['ExpiryDate'];
            }
        }

        return null;
    }

    public static function resolveRefundStatus(BookingAttempt $attempt): string
    {
        $selection = $attempt->selection_json ?? [];
        $type      = data_get($selection, 'refund_type')
            ?? data_get($attempt->snapshot_json, 'selection.refund_type');

        return match ($type) {
            'refundable'     => 'Refundable',
            'partial'        => 'Partially Refundable',
            'non_refundable' => 'Non Refundable',
            default          => 'Refundable',
        };
    }

    public static function resolveWayBadge(?int $way, array $journeyLines): string
    {
        if (count($journeyLines) > 2) {
            return 'Multi City';
        }

        return ((int) $way) === 2 ? 'Round Way' : 'One Way';
    }

    public static function wayBadgeClass(string $label): string
    {
        return match ($label) {
            'Round Way'  => 'bg-light-primary text-primary',
            'Multi City' => 'bg-light-info text-info',
            default      => 'bg-light-secondary text-secondary',
        };
    }

    public static function extractJourneyLines(?array $commitResponse, ?array $snapshot, $search, mixed $pricePayload = null): array
    {
        $fromCommit = self::journeysFromCommit($commitResponse);
        if ($fromCommit !== []) {
            return $fromCommit;
        }

        $fromSnapshot = self::journeysFromSnapshot($snapshot);
        if ($fromSnapshot !== []) {
            return $fromSnapshot;
        }

        $fromPrice = self::journeysFromPricePayload($pricePayload);
        if ($fromPrice !== []) {
            return $fromPrice;
        }

        if ($search && $search->from_airport && $search->to_airport) {
            $depFmt = optional($search->dep_date)->format('d-M-Y');

            return [[
                'sector'           => strtoupper($search->from_airport) . '-' . strtoupper($search->to_airport),
                'departure_at_fmt' => $depFmt,
                'legs'             => [[
                    'dep_date_fmt' => $depFmt,
                    'dep_time_fmt' => null,
                ]],
            ]];
        }

        return [];
    }

    private static function journeysFromPricePayload(mixed $payload): array
    {
        if (!is_array($payload)) {
            return [];
        }

        $products = data_get($payload, 'mapped.products')
            ?? data_get($payload, 'products', []);

        if (!is_array($products) || $products === []) {
            return [];
        }

        $lines = [];
        foreach ($products as $product) {
            $flight = data_get($product, 'flight', []);
            $dep    = data_get($flight, 'departure', []);
            $arr    = data_get($flight, 'arrival', []);
            if (empty($dep['location']) || empty($arr['location'])) {
                continue;
            }

            $depDate = self::formatDateValue($dep['date'] ?? null);
            $depTime = self::formatTimeValue($dep['time'] ?? null, $dep['date'] ?? null);

            $lines[] = [
                'sector'           => strtoupper((string) $dep['location']) . '-' . strtoupper((string) $arr['location']),
                'departure_at_fmt' => self::formatDateTimeParts($depDate, $depTime),
                'legs'             => [[
                    'dep_date_fmt' => $depDate,
                    'dep_time_fmt' => $depTime,
                ]],
            ];
        }

        return $lines;
    }

    private static function journeysFromCommit(?array $commitResponse): array
    {
        if (empty($commitResponse)) {
            return [];
        }

        $offer    = data_get($commitResponse, 'ReservationResponse.Reservation.Offer.0')
            ?? data_get($commitResponse, 'ReservationResponse.Reservation.Offer');
        $products = data_get($offer, 'Product', []);
        if (!is_array($products) || $products === []) {
            return [];
        }
        if (isset($products['@type'])) {
            $products = [$products];
        }

        $lines = [];
        foreach ($products as $product) {
            $parsed = self::parseProductSegments($product);
            if ($parsed !== null) {
                $lines[] = $parsed;
            }
        }

        return $lines;
    }

    private static function journeysFromSnapshot(?array $snapshot): array
    {
        if (empty($snapshot)) {
            return [];
        }

        $products = data_get($snapshot, 'price.products', []);
        if (!is_array($products) || $products === []) {
            return [];
        }

        $lines = [];
        foreach ($products as $product) {
            $flight = data_get($product, 'flight', []);
            $dep    = data_get($flight, 'departure', []);
            $arr    = data_get($flight, 'arrival', []);
            if (empty($dep['location']) || empty($arr['location'])) {
                continue;
            }

            $depDate = self::formatDateValue($dep['date'] ?? null);
            $depTime = self::formatTimeValue($dep['time'] ?? null, $dep['date'] ?? null);

            $lines[] = [
                'sector'           => strtoupper((string) $dep['location']) . '-' . strtoupper((string) $arr['location']),
                'departure_at_fmt' => self::formatDateTimeParts($depDate, $depTime),
                'legs'             => [[
                    'dep_date_fmt' => $depDate,
                    'dep_time_fmt' => $depTime,
                ]],
            ];
        }

        return $lines;
    }

    private static function parseProductSegments(array $product): ?array
    {
        $segments = data_get($product, 'FlightSegment', []);
        if (!is_array($segments) || $segments === []) {
            return null;
        }
        if (isset($segments['@type'])) {
            $segments = [$segments];
        }

        $legs      = [];
        $firstFrom = null;
        $lastTo    = null;
        $firstDep  = null;

        foreach ($segments as $segment) {
            $flight = data_get($segment, 'Flight', []);
            $dep    = data_get($flight, 'Departure', []);
            $arr    = data_get($flight, 'Arrival', []);
            $from   = strtoupper(trim((string) data_get($dep, 'location', '')));
            $to     = strtoupper(trim((string) data_get($arr, 'location', '')));
            if ($from === '' || $to === '') {
                continue;
            }

            if ($firstFrom === null) {
                $firstFrom = $from;
                $firstDep  = $dep;
            }
            $lastTo = $to;

            $legs[] = [
                'dep_date_fmt' => self::formatDateValue($dep['date'] ?? null),
                'dep_time_fmt' => self::formatTimeValue($dep['time'] ?? null, $dep['date'] ?? null),
            ];
        }

        if ($firstFrom === null || $lastTo === null) {
            return null;
        }

        $depDate = self::formatDateValue($firstDep['date'] ?? null);
        $depTime = self::formatTimeValue($firstDep['time'] ?? null, $firstDep['date'] ?? null);

        return [
            'sector'           => "{$firstFrom}-{$lastTo}",
            'departure_at_fmt' => self::formatDateTimeParts($depDate, $depTime),
            'legs'             => $legs,
        ];
    }

    private static function formatDateValue(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('d-M-Y');
        } catch (\Throwable) {
            return $value;
        }
    }

    private static function formatTimeValue(?string $time, ?string $date): ?string
    {
        if ($time === null || trim($time) === '') {
            return null;
        }

        try {
            $base = $date ? Carbon::parse("{$date} {$time}") : Carbon::parse($time);

            return $base->format('h:i A');
        } catch (\Throwable) {
            return $time;
        }
    }

    private static function formatDeadlineParts(?string $iso): array
    {
        if ($iso === null || trim($iso) === '') {
            return ['date' => null, 'time' => null, 'full' => null];
        }

        try {
            $dt = Carbon::parse($iso);
            $date = $dt->format('d-M-Y');
            $time = $dt->format('h:i A');

            return [
                'date' => $date,
                'time' => $time,
                'full' => "{$date} {$time}",
            ];
        } catch (\Throwable) {
            return ['date' => null, 'time' => null, 'full' => null];
        }
    }

    private static function formatDateTimeParts(?string $date, ?string $time): ?string
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        if ($time === null || trim($time) === '') {
            return $date;
        }

        return "{$date} {$time}";
    }

    public static function creatorInitials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $parts = array_values(array_filter($parts));
        if ($parts === []) {
            return '?';
        }

        return strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1));
    }

    public static function resolveOutcome(BookingAttempt $attempt): array
    {
        $stage     = $attempt->closing_stage;
        $apiStatus = $attempt->last_api_status;
        $apiStep   = $attempt->last_api_step;
        $apiError  = $attempt->last_api_error;

        if (!$stage || !$apiStatus) {
            $derived   = self::deriveOutcome($attempt);
            $stage     = $stage ?: ($derived['stage_raw'] ?? null);
            $apiStatus = $apiStatus ?: ($derived['last_api_raw'] ?? null);
            $apiStep   = $apiStep ?: ($derived['last_api_step'] ?? null);
            $apiError  = $apiError ?: ($derived['last_api_error'] ?? null);
        }

        return [
            'stage'            => BookingAttemptOutcome::stageLabel($stage),
            'stage_raw'        => $stage,
            'last_api_status'  => BookingAttemptOutcome::apiStatusLabel($apiStatus),
            'last_api_raw'     => $apiStatus,
            'last_api_step'    => $apiStep,
            'last_api_error'   => $apiError,
        ];
    }

    public static function deriveOutcome(BookingAttempt $attempt): array
    {
        $timeline = app(BookingSnapshotBuilder::class)->timeline($attempt);
        if ($timeline !== []) {
            $last = $timeline[array_key_last($timeline)];
            $step = (string) ($last['session_type'] ?? 'search');

            return [
                'stage_raw'      => BookingAttemptOutcome::stageFromSessionType($step),
                'last_api_raw'   => (string) ($last['status'] ?? ''),
                'last_api_step'  => $step,
                'last_api_error' => $last['error_message'] ?? null,
            ];
        }

        $stage = BookingAttemptOutcome::stageFromAttemptStatus((string) $attempt->status);

        return [
            'stage_raw'      => $stage,
            'last_api_raw'   => 'success',
            'last_api_step'  => $stage ?? 'search',
            'last_api_error' => null,
        ];
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'committed'        => 'Booking Confirmed',
            'confirmed'        => 'Confirmed',
            'booking_failed'   => 'Booking Failed',
            'ticketed'         => 'Ticketed',
            'ticketing'        => 'Ticketing',
            'cancelled'        => 'Cancelled',
            'voided'           => 'Voided',
            'ready_for_review' => 'Ready for Review',
            'in_progress'      => 'In Progress',
            'priced'              => 'Priced',
            'complete_on_price'   => 'Complete On Price',
            'searching'           => 'Searching',
            'complete_on_search'  => 'Complete On Search',
            default            => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public static function mapRow(BookingAttempt $row, int $index, ?array $commitResponse = null): array
    {
        $search       = $row->searchLog;
        $price        = $row->priceLog;
        $snapshot     = $row->snapshot_json;
        $journeyLines = self::extractJourneyLines(
            $commitResponse,
            is_array($snapshot) ? $snapshot : null,
            $search,
            $price?->price_payload
        );
        $wayBadge     = self::resolveWayBadge($search?->way, $journeyLines);
        $deadline     = self::formatDeadlineParts(self::extractPaymentDeadline($commitResponse));

        $adt = (int) ($search?->adt ?? 0);
        $cnn = (int) ($search?->cnn ?? 0);
        $inf = (int) ($search?->inf ?? 0);
        $paxCount = $adt + $cnn + $inf;

        $gdsPnr      = $row->gds_pnr;
        $airlinePnr  = $row->airline_pnr;
        $airlineCode = $row->airline_code;
        $currency    = $price?->currency ?? 'BDT';
        $totalFare  = $price?->total_price;
        $creator    = $row->creator_name ?? '—';
        $creatorAvatar = $row->creator_avatar ?? null;
        $outcome    = self::resolveOutcome($row);
        $listStatus = self::resolveListStatus($row);

        return [
            'DT_RowIndex'            => $index + 1,
            'id'                     => hashid_encode(HashIdService::BOOKING_ATTEMPT, (int) $row->id),
            'attempt_ref'            => (int) $row->id,
            'booking_code'           => self::bookingCode((int) $row->id),
            'agency_name'            => $row->agency_name ?? '—',
            'agency_code'            => $row->agency_code ?? null,
            'journey_lines'          => $journeyLines,
            'sector'                 => collect($journeyLines)->pluck('sector')->implode(' / '),
            'dep_date'               => optional($search?->dep_date)->format('d-M-Y'),
            'booking_date'           => optional($row->confirmed_at ?? $row->created_at)->format('d-M-Y'),
            'pax_count'              => $paxCount,
            'pax_adt'                => $adt,
            'pax_cnn'                => $cnn,
            'pax_inf'                => $inf,
            'gds_pnr'                => $gdsPnr,
            'airline_pnr'            => $airlinePnr,
            'airline_code'           => $airlineCode,
            'airline_name'           => $row->airline_name,
            'cabin_class'            => $row->cabin_class,
            'total_fare'             => $totalFare,
            'currency'               => $currency,
            'total_fare_label'       => $totalFare !== null ? number_format((float) $totalFare, 0, '.', ',') : '—',
            'payment_deadline'       => self::extractPaymentDeadline($commitResponse),
            'payment_deadline_date'  => $deadline['date'],
            'payment_deadline_time'  => $deadline['time'],
            'payment_deadline_fmt'   => $deadline['full'],
            'way_type'               => $wayBadge,
            'way_badge_class'        => self::wayBadgeClass($wayBadge),
            'ticket_no'              => is_array($row->ticket_numbers) && !empty($row->ticket_numbers)
                ? implode(', ', $row->ticket_numbers)
                : null,
            'ticket_numbers'         => is_array($row->ticket_numbers) ? $row->ticket_numbers : [],
            'ticket_pax_map'         => self::buildTicketPaxMap($row),
            'ticket_date'            => optional($row->ticketed_at)->toIso8601String(),
            'ticket_at_fmt'          => optional($row->ticketed_at ?? $row->confirmed_at ?? $row->created_at)->format('d-M-Y h:i A'),
            'cancelled_at_fmt'       => optional($row->cancelled_at)->format('d-M-Y h:i A'),
            'cancelled_at_iso'       => optional($row->cancelled_at)->toIso8601String(),
            'voided_at_fmt'          => optional($row->voided_at)->format('d-M-Y h:i A'),
            'voided_at_iso'          => optional($row->voided_at)->toIso8601String(),
            'refund_status'          => self::resolveRefundStatus($row),
            'status'                 => $outcome['stage'],
            'status_raw'             => $outcome['stage_raw'] ?? $row->status,
            'stage'                  => $outcome['stage'],
            'stage_raw'              => $outcome['stage_raw'],
            'last_api_status'        => $outcome['last_api_status'],
            'last_api_raw'           => $outcome['last_api_raw'],
            'last_api_step'          => $outcome['last_api_step'],
            'last_api_error'         => $outcome['last_api_error'],
            'legacy_status'          => $listStatus['label'],
            'legacy_status_raw'      => $listStatus['raw'],
            'attempt_status'         => $row->status,
            'created_by'             => $creator,
            'created_by_initials'    => self::creatorInitials($creator !== '—' ? $creator : null),
            'created_by_avatar'      => $creatorAvatar,
            'created_at'             => optional($row->created_at)->format('d-M-Y H:i'),
            'created_at_iso'         => optional($row->created_at)?->format('Y-m-d H:i:s'),
            'created_at_date'        => optional($row->created_at)->format('d-M-Y'),
            'created_at_time'        => optional($row->created_at)->format('H:i'),
            'route'                  => $search
                ? $search->from_airport . ' → ' . $search->to_airport
                : null,
            'from_airport'           => $search?->from_airport
                ? strtoupper((string) $search->from_airport)
                : null,
            'to_airport'             => $search?->to_airport
                ? strtoupper((string) $search->to_airport)
                : null,
            'arrival_date'           => optional($search?->arrival_date)->format('d-M-Y'),
            'workbench_identifier'   => $row->workbench_identifier,
        ];
    }

    public static function buildTicketPaxMap(BookingAttempt $row): array
    {
        $tickets = is_array($row->ticket_numbers) ? $row->ticket_numbers : [];
        if (empty($tickets)) {
            return [];
        }

        $paxes = $row->relationLoaded('paxes')
            ? $row->paxes->sortBy('sequence')->values()
            : collect();

        $map = [];
        foreach ($tickets as $i => $ticketNo) {
            $pax = $paxes->get($i);
            $map[$ticketNo] = $pax
                ? trim($pax->first_name . ' ' . $pax->last_name)
                : null;
        }

        return $map;
    }

    public static function commitResponseForAttempt(BookingAttempt $attempt): ?array
    {
        $session = $attempt->relationLoaded('commitSession')
            ? $attempt->commitSession
            : BookingSession::query()
            ->where('booking_attempt_id', $attempt->id)
            ->where('session_type', 'commit')
            ->where('status', 'success')
            ->orderByDesc('id')
            ->first();

        $payload = $session?->response_payload;

        return is_array($payload) && $payload !== [] ? $payload : null;
    }

    public static function attachUserNames($rows): void
    {
        $ids = collect($rows)->pluck('created_by')->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $users = User::query()->whereIn('id', $ids)->get(['id', 'name', 'img_path']);
        $byId = $users->keyBy('id');

        foreach ($rows as $row) {
            if (!$row->created_by) {
                $row->creator_name = '—';
                $row->creator_avatar = null;
                continue;
            }

            $user = $byId->get($row->created_by);
            $row->creator_name = $user?->name ?? '—';
            $row->creator_avatar = $user?->img_path;
        }
    }

    public static function resolveAgencyIdForUser(?User $user): ?int
    {
        if (!$user) {
            return null;
        }

        if (!empty($user->agent_id)) {
            return (int) $user->agent_id;
        }

        $agentId = Agent::where('user_id', $user->id)->value('id');

        return $agentId ? (int) $agentId : null;
    }

    public static function agencyUserIds(?int $agencyId): array
    {
        if (!$agencyId) {
            return [];
        }

        $ids = User::query()->where('agent_id', $agencyId)->pluck('id')->all();
        $ownerId = Agent::where('id', $agencyId)->value('user_id');

        if ($ownerId) {
            $ids[] = (int) $ownerId;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    public static function applyAgencyScope($query, ?User $user): void
    {
        if (config('app.booking_list_all_users', false) || !$user) {
            return;
        }

        $agencyId = self::resolveAgencyIdForUser($user);

        if ($agencyId) {
            $query->whereIn('user_id', self::agencyUserIds($agencyId));

            return;
        }

        $query->where('user_id', $user->id);
    }

    public static function userCanAccessAttempt(BookingAttempt $attempt, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if (config('app.booking_list_all_users', false)) {
            return true;
        }

        $agencyId = self::resolveAgencyIdForUser($user);

        if ($agencyId) {
            return in_array((int) $attempt->user_id, self::agencyUserIds($agencyId), true);
        }

        return (int) $attempt->user_id === (int) $user->id;
    }

    public static function attachAgencyNames($rows): void
    {
        $userIds = collect($rows)
            ->map(fn($row) => $row->user_id ?: $row->created_by)
            ->filter()
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return;
        }

        $users = User::query()->whereIn('id', $userIds)->get(['id', 'agent_id']);
        $agentsByUserId = Agent::query()
            ->whereIn('user_id', $userIds)
            ->get(['id', 'user_id', 'name', 'agent_code'])
            ->keyBy('user_id');

        $agentIds = $users->pluck('agent_id')->filter()->unique()->values();
        $agentsById = Agent::query()
            ->whereIn('id', $agentIds)
            ->get(['id', 'user_id', 'name', 'agent_code'])
            ->keyBy('id');

        $usersById = $users->keyBy('id');

        foreach ($rows as $row) {
            $userId = $row->user_id ?: $row->created_by;
            $agent = $userId ? $agentsByUserId->get($userId) : null;

            if (!$agent && $userId) {
                $user = $usersById->get($userId);
                $agent = $user?->agent_id ? $agentsById->get($user->agent_id) : null;
            }

            $row->agency_name = $agent?->name;
            $row->agency_code = $agent?->agent_code;
        }
    }
}
