<?php

namespace App\Services\SearchV2;

use App\Models\BookingAttempt;
use App\Models\BookingPriceLog;
use App\Services\HashIdService;
use App\Models\BookingPax;
use App\Models\BookingSession;

class BookingSnapshotBuilder
{
    public function build(BookingAttempt $attempt): array
    {
        $attempt->load(['searchLog', 'priceLog', 'paxes', 'sessions']);

        $search = $attempt->searchLog;
        $price  = $attempt->priceLog;

        $ancillaries = $attempt->sessions
            ->where('session_type', 'add_ancillary')
            ->values()
            ->map(fn($s) => [
                'id'           => $s->id,
                'status'       => $s->status,
                'request'      => $s->request_payload,
                'response'     => $s->response_payload,
            ])
            ->all();

        return [
            'attempt_id'   => $attempt->id,
            'status'       => $attempt->status,
            'generated_at' => now()->toIso8601String(),
            'search'       => $search ? [
                'id'           => $search->id,
                'from'         => $search->from_airport,
                'to'           => $search->to_airport,
                'dep_date'     => optional($search->dep_date)->format('Y-m-d'),
                'arrival_date' => optional($search->arrival_date)->format('Y-m-d'),
                'way'          => $search->way,
                'adt'          => $search->adt,
                'cnn'          => $search->cnn,
                'inf'          => $search->inf,
                'cabin_class'  => $search->cabin_class,
                'payload'      => $search->search_payload,
            ] : null,
            'selection'    => $attempt->selection_json ?? $price?->selection_json,
            'price'        => $price ? [
                'id'              => $price->id,
                'offer_identifier' => $price->offer_identifier,
                'total_price'     => $price->total_price,
                'base_fare'       => $price->base_fare,
                'total_taxes'     => $price->total_taxes,
                'total_fees'      => data_get($price->price_payload, 'mapped.total_fees')
                    ?? data_get($price->price_payload, 'total_fees'),
                'currency'        => $price->currency,
                'price_breakdown' => data_get($price->price_payload, 'mapped.price_breakdown')
                    ?? data_get($price->price_payload, 'price_breakdown'),
                'brand'           => data_get($price->price_payload, 'mapped.brand')
                    ?? data_get($price->price_payload, 'brand'),
                'products'        => data_get($price->price_payload, 'mapped.products')
                    ?? data_get($price->price_payload, 'products'),
            ] : null,
            'workbench_identifier' => $attempt->workbench_identifier,
            'travelers'    => $attempt->paxes->map(fn(BookingPax $p) => [
                'sequence'          => $p->sequence,
                'pax_type'          => $p->pax_type,
                'name'              => trim("{$p->title} {$p->first_name} {$p->last_name}"),
                'dob'               => optional($p->dob)->format('Y-m-d'),
                'gender'            => $p->gender,
                'passport_no'       => $p->passport_number,
                'email'             => $p->email,
                'phone'             => $p->phone,
                'meal_preference'   => $p->meal_preference,
                'wheelchair_needed' => $p->wheelchair_needed,
            ])->values()->all(),
            'ancillaries'  => $ancillaries,
            'ssr_applied'  => $attempt->sessions->contains(fn($s) => str_starts_with((string) $s->session_type, 'add_ssr')
                && $s->status === 'success'),
            'agency_saved' => $attempt->sessions->contains(fn($s) => $s->session_type === 'add_travel_agency'
                && $s->status === 'success'),
            'content_source' => data_get($attempt->selection_json, 'content_source'),
        ];
    }

    public function buildPostCommit(BookingAttempt $attempt, array $commitBody, array $parsed): array
    {
        $attempt->load(['searchLog', 'priceLog', 'paxes', 'sessions']);

        return [
            'snapshot_type'          => 'post_commit',
            'attempt_id'             => $attempt->id,
            'status'                 => $attempt->status,
            'generated_at'           => now()->toIso8601String(),
            'pnr'                    => $parsed['pnr'] ?? $attempt->pnr,
            'reservation_identifier' => $parsed['reservation_identifier'] ?? $attempt->reservation_identifier,
            'reservation_status'     => $parsed['reservation_status'] ?? null,
            'user_booking_summary'   => $this->build($attempt),
            'commit_api_response'    => $commitBody,
        ];
    }

    public function timeline(BookingAttempt $attempt): array
    {
        $attempt->loadMissing(['searchLog', 'priceLog']);

        $rows = [];

        if ($attempt->searchLog) {
            $search = $attempt->searchLog;
            $rows[] = [
                'id'                   => hashid_encode(HashIdService::SEARCH_LOG, (int) $search->id),
                'log_ref'              => (int) $search->id,
                'source'               => 'search',
                'session_type'         => 'search',
                'type_label'           => self::timelineTypeLabel('search', null),
                'status'               => $search->status,
                'http_status'          => $search->http_status,
                'error_message'        => $search->error_message,
                'identifier_value'     => null,
                'response_file_path'   => $search->response_file_path,
                'has_request_payload'  => !empty($search->search_payload),
                'has_response_payload' => !empty($search->response_file_path),
                'created_at'           => optional($search->created_at)->format('Y-m-d H:i:s'),
            ];
        }

        $priceLogs = $attempt->booking_search_log_id
            ? BookingPriceLog::query()
            ->where('booking_search_log_id', $attempt->booking_search_log_id)
            ->orderBy('id')
            ->get()
            : collect($attempt->priceLog ? [$attempt->priceLog] : []);

        $priceLogsById = $priceLogs->keyBy('id');

        foreach ($priceLogs as $price) {
            $airlineCode = self::airlineCodeFromPriceLog($price);
            $rows[] = [
                'id'                   => hashid_encode(HashIdService::PRICE_LOG, (int) $price->id),
                'log_ref'              => (int) $price->id,
                'source'               => 'price',
                'session_type'         => 'price',
                'type_label'           => self::timelineTypeLabel('price', $airlineCode),
                'airline_code'         => $airlineCode,
                'status'               => $price->status,
                'http_status'          => $price->http_status,
                'error_message'        => $price->error_message,
                'identifier_value'     => $price->offer_identifier,
                'response_file_path'   => $price->response_file_path,
                'has_request_payload'  => !empty(data_get($price->price_payload, 'request')),
                'has_response_payload' => !empty($price->response_file_path),
                'created_at'           => optional($price->created_at)->format('Y-m-d H:i:s'),
            ];
        }

        foreach ($attempt->sessions()->orderBy('id')->get() as $session) {
            $priceLogId  = $session->booking_price_log_id ?? $attempt->booking_price_log_id;
            $priceLog    = $priceLogId ? ($priceLogsById->get($priceLogId) ?? BookingPriceLog::find($priceLogId)) : null;
            $airlineCode = $priceLog ? self::airlineCodeFromPriceLog($priceLog) : null;

            $rows[] = [
                'id'                   => hashid_encode(HashIdService::BOOKING_SESSION, (int) $session->id),
                'log_ref'              => (int) $session->id,
                'source'               => 'session',
                'session_type'         => $session->session_type,
                'type_label'           => self::timelineTypeLabel((string) $session->session_type, $airlineCode),
                'airline_code'         => $airlineCode,
                'status'               => $session->status,
                'http_status'          => $session->http_status,
                'error_message'        => $session->error_message,
                'identifier_value'     => $session->identifier_value,
                'response_file_path'   => $session->response_file_path,
                'has_request_payload'  => !empty($session->request_payload),
                'has_response_payload' => !empty($session->response_payload) || !empty($session->response_file_path),
                'created_at'           => optional($session->created_at)->format('Y-m-d H:i:s'),
            ];
        }

        usort($rows, fn(array $a, array $b) => strcmp((string) $a['created_at'], (string) $b['created_at']));

        return $rows;
    }

    public static function timelineTypeLabel(string $sessionType, ?string $airlineCode): string
    {
        $base = match ($sessionType) {
            'search'                => 'Search',
            'price'                 => 'Price',
            'reservation_workbench' => 'Reservation workbench',
            'add_offer'             => 'Add offer',
            'add_traveler'          => 'Add traveler',
            'add_travel_agency'     => 'Travel agency',
            'add_ssr_meal'          => 'SSR meal',
            'add_ssr_wheelchair'    => 'SSR wheelchair',
            'pre_commit_snapshot'   => 'Pre-commit snapshot',
            'commit'                => 'Commit',
            'post_commit_snapshot'  => 'Post-commit snapshot',
            'add_ancillary'         => 'Add ancillary',
            'fare_rules_outbound'   => 'Fare Rules',
            'fare_rules_inbound'    => 'Fare Rules (Return)',
            'cancel'                => 'Booking cancel',
            default                 => ucwords(str_replace('_', ' ', $sessionType)),
        };

        $code = strtoupper(trim((string) $airlineCode));
        if ($sessionType !== 'search' && $code !== '') {
            return $base . ' | ' . $code;
        }

        return $base;
    }

    public static function airlineCodeFromPriceLog(BookingPriceLog $price): ?string
    {
        $candidates = [
            data_get($price->selection_json, 'outbound.carrier_code'),
            data_get($price->selection_json, 'outbound.carrier'),
            data_get($price->price_payload, 'mapped.validating_airline'),
            data_get($price->price_payload, 'mapped.products.0.flight.carrier'),
        ];

        foreach ($candidates as $code) {
            $code = strtoupper(trim((string) $code));
            if (strlen($code) === 2 || strlen($code) === 3) {
                return $code;
            }
        }

        return null;
    }
}
