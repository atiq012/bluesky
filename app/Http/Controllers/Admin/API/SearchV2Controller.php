<?php

namespace App\Http\Controllers\Admin\API;

use Exception;
use Illuminate\Http\Request;
use App\Models\BookingSearchLog;
use App\Http\Controllers\BaseController;
use App\Services\HashIdService;
use App\Services\SearchV2\TravelportSearchService;
use Illuminate\Support\Facades\File;

class SearchV2Controller extends BaseController
{
    public function __construct(
        private readonly TravelportSearchService $travelportSearchService
    ) {}

    public function search(Request $request)
    {
        $request->validate([
            'from' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'to' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/', 'different:from'],
            'dep_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'arrival_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dep_date'],
            'Way' => ['nullable', 'integer', 'in:1,2'],
            'ADT' => ['nullable', 'integer', 'min:1', 'max:9'],
            'CNN' => ['nullable', 'integer', 'min:0', 'max:8'],
            'KID' => ['nullable', 'integer', 'min:0', 'max:8'],
            'INF' => ['nullable', 'integer', 'min:0', 'max:4'],
            'INS' => ['nullable', 'integer', 'min:0', 'max:4'],
            'UNN' => ['nullable', 'integer', 'min:0', 'max:4'],
        ]);

        try {
            $result = $this->travelportSearchService->search(
                $request->all(),
                optional(auth()->user())->id
            );

            return response()->json([
                'status'              => true,
                'message'             => 'Flight search completed.',
                'search_enabled'      => true,
                'flights'             => $result['flights'] ?? [],
                'raw_response'        => $result['provider_response'] ?? [],
                'snapshot_key'        => $result['snapshot_key'] ?? null,
                'catalog_identifier'  => $result['catalog_identifier'] ?? null,
                'search_log_id'       => $result['search_log_id'] ?? null,
                'booking_attempt_id'  => !empty($result['booking_attempt_id'])
                    ? hashid_encode(HashIdService::BOOKING_ATTEMPT, (int) $result['booking_attempt_id'])
                    : null,
                'request_id'          => $result['request_id'] ?? null,
                'response_file_path'  => $result['response_file_path'] ?? null,
            ]);
        } catch (Exception $exception) {
            report($exception);
            return response()->json([
                'status' => false,
                'message' => 'Travelport search failed. Please try again.',
                'search_enabled' => true,
                'flights' => [],
            ], 500);
        }
    }

    public function latestSnapshot()
    {
        $snapshot = $this->travelportSearchService->getLatestSnapshot(optional(auth()->user())->id);

        return response()->json([
            'status' => true,
            'message' => 'Snapshot fetched.',
            'data' => $snapshot,
        ]);
    }

    public function getFlightSearchLogs(Request $request)
    {
        $query = BookingSearchLog::query()->orderByDesc('id');

        if ($request->filled('from')) {
            $query->where('from_airport', strtoupper((string) $request->input('from')));
        }

        if ($request->filled('to')) {
            $query->where('to_airport', strtoupper((string) $request->input('to')));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        $logs = $query->limit(1000)->get()->map(function ($row, $index) {
            return [
                'DT_RowIndex' => $index + 1,
                'id' => $row->id,
                'request_id' => $row->request_id,
                'way' => $row->way,
                'from_airport' => $row->from_airport,
                'to_airport' => $row->to_airport,
                'dep_date' => optional($row->dep_date)->format('Y-m-d'),
                'arrival_date' => optional($row->arrival_date)->format('Y-m-d'),
                'adt' => $row->adt,
                'cnn' => $row->cnn,
                'kid' => $row->kid,
                'inf' => $row->inf,
                'cabin_class' => $row->cabin_class,
                'provider' => $row->provider,
                'endpoint' => $row->endpoint,
                'response_file_path' => $row->response_file_path,
                'response_size_bytes' => $row->response_size_bytes,
                'flight_count' => $row->flight_count,
                'status' => $row->status,
                'error_message' => $row->error_message,
                'created_at' => optional($row->created_at)->format('Y-m-d H:i:s'),
            ];
        });

        return $this->SuccessResponse($logs, 'Flight search logs fetched successfully.');
    }

    public function viewFlightSearchLog(Request $request)
    {
        $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $log = BookingSearchLog::find($request->input('id'));
        if (!$log) {
            return $this->ErrorResponse('Log not found', [], 404);
        }

        $path = storage_path('app/' . ltrim((string) $log->response_file_path, '/'));
        if (empty($log->response_file_path) || !File::exists($path)) {
            return $this->ErrorResponse('Response file not found', [], 404);
        }

        $content = json_decode(File::get($path), true);

        return $this->SuccessResponse([
            'id' => $log->id,
            'request_id' => $log->request_id,
            'response_file_path' => $log->response_file_path,
            'response_json' => $content,
            'search_payload' => $log->search_payload,
        ], 'Flight search response loaded successfully.');
    }
}
