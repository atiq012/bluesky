<?php

namespace App\Http\Controllers\Admin\API;

use App\Models\BookingAttempt;
use App\Models\BookingPriceLog;
use App\Models\BookingSearchLog;
use App\Models\BookingSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Controllers\BaseController;
use App\Services\HashIdService;
use App\Services\SearchV2\BookingSnapshotBuilder;
use App\Services\SearchV2\BookingListMapper;

class BookingAttemptAdminController extends BaseController
{
    public function __construct(
        private readonly BookingSnapshotBuilder $snapshotBuilder
    ) {}

    public function index(Request $request)
    {
        $query = BookingAttempt::query()
            ->with([
                'searchLog:id,from_airport,to_airport,dep_date,arrival_date,way,adt,cnn,inf,cabin_class',
                'priceLog:id,total_price,currency,base_fare,total_taxes,price_payload',
                'commitSession:id,booking_attempt_id,response_payload,session_type,status',
                'paxes:id,booking_attempt_id,first_name,last_name,pax_type,sequence',
            ])
            ->orderByDesc('id');

        if ($request->input('scope') === 'booking') {
            BookingListMapper::applyBookingListScope($query);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        } elseif ($request->user()) {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->filled('workbench_identifier')) {
            $query->where('workbench_identifier', $request->input('workbench_identifier'));
        }

        $attempts = $query->limit(500)->get();
        BookingListMapper::attachUserNames($attempts);

        $rows = $attempts->map(function ($row, $index) {
            $commitResponse = BookingListMapper::commitResponseForAttempt($row);

            return BookingListMapper::mapRow($row, $index, $commitResponse);
        });

        return $this->SuccessResponse($rows, 'Booking list fetched.');
    }

    public function show(string $id)
    {
        $attemptId = hashid_decode(HashIdService::BOOKING_ATTEMPT, $id);
        if (!$attemptId) {
            return $this->ErrorResponse('Booking attempt not found', [], 404);
        }

        $attempt = BookingAttempt::with(['searchLog', 'priceLog', 'paxes', 'sessions'])->find($attemptId);
        if (!$attempt) {
            return $this->ErrorResponse('Booking attempt not found', [], 404);
        }

        $snapshot = $attempt->snapshot_json ?? $this->snapshotBuilder->build($attempt);
        $outcome  = BookingListMapper::resolveOutcome($attempt);

        return $this->SuccessResponse([
            'attempt'  => [
                'id'                   => hashid_encode(HashIdService::BOOKING_ATTEMPT, (int) $attempt->id),
                'attempt_ref'          => (int) $attempt->id,
                'status'               => $attempt->status,
                'stage'                => $outcome['stage'],
                'stage_raw'            => $outcome['stage_raw'],
                'last_api_status'      => $outcome['last_api_status'],
                'last_api_raw'         => $outcome['last_api_raw'],
                'last_api_step'        => $outcome['last_api_step'],
                'last_api_error'       => $outcome['last_api_error'],
                'workbench_identifier' => $attempt->workbench_identifier,
                'selection_json'       => $attempt->selection_json,
                'commit_response'      => BookingListMapper::commitResponseForAttempt($attempt),
                'snapshot_json'        => $snapshot,
                'pre_commit_snapshot'  => $snapshot,
                'post_commit_snapshot' => $attempt->post_commit_snapshot_json,
                'confirmed_at'         => optional($attempt->confirmed_at)->format('Y-m-d H:i:s'),
                'pnr'                    => $attempt->gds_pnr,
                'gds_pnr'                => $attempt->gds_pnr,
                'airline_pnr'            => $attempt->airline_pnr,
                'reservation_identifier' => $attempt->reservation_identifier,
                'commit_error'           => $attempt->commit_error,
                'created_at'           => optional($attempt->created_at)->format('Y-m-d H:i:s'),
            ],
            'timeline' => $this->snapshotBuilder->timeline($attempt),
        ], 'Booking attempt detail loaded.');
    }

    public function downloadSearchLogRequest(string $id)
    {
        $logId = hashid_decode(HashIdService::SEARCH_LOG, $id);
        $log = $logId ? BookingSearchLog::find($logId) : null;
        if (!$log) {
            return $this->ErrorResponse('Search log not found', [], 404);
        }

        return $this->downloadJsonPayload(
            [
                'headers' => $this->buildTravelportRequestHeaders(true),
                'body'    => $log->search_payload,
            ],
            null,
            $this->searchLogDownloadFilename($log, 'request')
        );
    }

    public function downloadSearchLogResponse(string $id)
    {
        $logId = hashid_decode(HashIdService::SEARCH_LOG, $id);
        $log = $logId ? BookingSearchLog::find($logId) : null;
        if (!$log) {
            return $this->ErrorResponse('Search log not found', [], 404);
        }

        return $this->downloadJsonPayload(
            null,
            $log->response_file_path,
            $this->searchLogDownloadFilename($log, 'response')
        );
    }

    public function downloadPriceLogRequest(string $id)
    {
        $logId = hashid_decode(HashIdService::PRICE_LOG, $id);
        $log = $logId ? BookingPriceLog::find($logId) : null;
        if (!$log) {
            return $this->ErrorResponse('Price log not found', [], 404);
        }

        return $this->downloadJsonPayload(
            [
                'headers' => $this->buildTravelportRequestHeaders(true),
                'body'    => data_get($log->price_payload, 'request'),
            ],
            null,
            $this->priceLogDownloadFilename($log, 'request')
        );
    }

    public function downloadPriceLogResponse(string $id)
    {
        $logId = hashid_decode(HashIdService::PRICE_LOG, $id);
        $log = $logId ? BookingPriceLog::find($logId) : null;
        if (!$log) {
            return $this->ErrorResponse('Price log not found', [], 404);
        }

        return $this->downloadJsonPayload(
            null,
            $log->response_file_path,
            $this->priceLogDownloadFilename($log, 'response')
        );
    }

    public function downloadSessionRequest(string $id)
    {
        $sessionId = hashid_decode(HashIdService::BOOKING_SESSION, $id);
        $session = $sessionId ? BookingSession::find($sessionId) : null;
        if (!$session) {
            return $this->ErrorResponse('Session not found', [], 404);
        }

        $name = $this->sessionDownloadFilename($session, 'request');

        return $this->downloadJsonPayload(
            [
                'headers' => $this->buildTravelportRequestHeaders(false),
                'body'    => $session->request_payload,
            ],
            null,
            $name
        );
    }

    public function downloadSessionResponse(string $id)
    {
        $sessionId = hashid_decode(HashIdService::BOOKING_SESSION, $id);
        $session = $sessionId ? BookingSession::find($sessionId) : null;
        if (!$session) {
            return $this->ErrorResponse('Session not found', [], 404);
        }

        $name = $this->sessionDownloadFilename($session, 'response');

        return $this->downloadJsonPayload(
            $session->response_payload,
            $session->response_file_path,
            $name
        );
    }

    private function searchLogDownloadFilename(BookingSearchLog $log, string $kind): string
    {
        $attempt = BookingAttempt::query()
            ->where('booking_search_log_id', $log->id)
            ->first();

        $attemptId = $attempt
            ? (int) $attempt->id
            : 0;

        return $attemptId . '-search-' . $kind . '.json';
    }

    private function priceLogDownloadFilename(BookingPriceLog $log, string $kind): string
    {
        $attemptId = (int) ($log->booking_attempt_id ?? 0);

        if ($attemptId === 0) {
            $attempt = BookingAttempt::query()
                ->where('booking_price_log_id', $log->id)
                ->first();
            $attemptId = $attempt ? (int) $attempt->id : 0;
        }

        return $attemptId . '-price-' . $kind . '.json';
    }

    private function sessionDownloadFilename(BookingSession $session, string $kind): string
    {
        $attemptId = (int) ($session->booking_attempt_id ?? 0);
        $sessionType = (string) ($session->session_type ?? 'session');

        return $attemptId . '-' . $sessionType . '-' . $kind . '.json';
    }

    private function buildTravelportRequestHeaders(bool $includeTaxBreakdown = false): array
    {
        $version     = (string) config('services.travelport_v2.version', '11');
        $accessGroup = (string) config('services.travelport_v2.access_group', '');

        $headers = [
            'Accept'                       => 'application/json',
            'Content-Type'                 => 'application/json',
            'Accept-Encoding'              => 'gzip, deflate',
            'XAUTH_TRAVELPORT_ACCESSGROUP' => $accessGroup,
            'Accept-Version'               => $version,
            'Content-Version'              => $version,
            'Authorization'                => 'Bearer <access-token>',
        ];

        if ($includeTaxBreakdown) {
            $taxBreakdown = filter_var(config('services.travelport_v2.tax_breakdown', true), FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
            $headers['taxBreakDown'] = $taxBreakdown;
        }

        return $headers;
    }

    private function downloadJsonPayload(mixed $payload, ?string $filePath, string $filename): Response
    {
        if (!empty($filePath)) {
            $path = storage_path('app/' . ltrim((string) $filePath, '/'));
            if (File::exists($path)) {
                return response()->download($path, $filename, [
                    'Content-Type' => 'application/json',
                ]);
            }
        }

        if ($payload !== null && $payload !== '' && $payload !== []) {
            $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return response($encoded, 200, [
                'Content-Type'        => 'application/json',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        return $this->ErrorResponse('Payload not found', [], 404);
    }
}
