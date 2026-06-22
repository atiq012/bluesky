<?php

namespace App\Http\Controllers\Admin\API;

use Exception;
use Illuminate\Http\Request;
use App\Models\BookingPriceLog;
use App\Http\Controllers\BaseController;
use App\Services\HashIdService;
use App\Services\SearchV2\TravelportPriceService;
use Illuminate\Support\Facades\File;

class PriceV2Controller extends BaseController
{
    public function __construct(
        private readonly TravelportPriceService $travelportPriceService
    ) {}

    public function price(Request $request)
    {
        $request->validate([
            'catalog_identifier'    => ['required', 'string'],
            'outbound_offering_id'  => ['required', 'string'],
            'outbound_product_ref'  => ['required', 'string'],
            'inbound_offering_id'   => ['nullable', 'string'],
            'inbound_product_ref'   => ['nullable', 'string'],
            'form'                  => ['required', 'array'],
            'form.Way'              => ['nullable', 'integer', 'in:1,2'],
            'form.from'             => ['nullable', 'string'],
            'form.to'               => ['nullable', 'string'],
            'form.dep_date'         => ['nullable', 'string'],
            'form.ADT'              => ['nullable', 'integer', 'min:1'],
            'form.CNN'              => ['nullable', 'integer', 'min:0'],
            'form.KID'              => ['nullable', 'integer', 'min:0'],
            'form.INF'              => ['nullable', 'integer', 'min:0'],
            'search_log_id'         => ['nullable', 'integer'],
            'booking_attempt_id'  => ['nullable', 'string'],
            'selection_json'        => ['nullable', 'array'],
        ]);

        try {
            $attemptId = null;
            if ($request->filled('booking_attempt_id')) {
                $attemptId = hashid_decode(HashIdService::BOOKING_ATTEMPT, (string) $request->input('booking_attempt_id'));
            }

            $priceRequest = [
                'catalog_identifier'   => $request->input('catalog_identifier'),
                'outbound_offering_id' => $request->input('outbound_offering_id'),
                'outbound_product_ref' => $request->input('outbound_product_ref'),
                'inbound_offering_id'  => $request->input('inbound_offering_id'),
                'inbound_product_ref'  => $request->input('inbound_product_ref'),
                'search_log_id'        => $request->input('search_log_id'),
                'booking_attempt_id'   => $attemptId ?: null,
                'selection_json'       => $request->input('selection_json'),
                'form'                 => $request->input('form', []),
            ];

            $result = $this->travelportPriceService->price(
                $priceRequest,
                optional(auth()->user())->id
            );

            return response()->json([
                'status'             => true,
                'message'            => 'Price confirmed.',
                'price_data'         => $result['price_data'],
                'offer_identifier'   => $result['offer_identifier'],
                'price_log_id'       => $result['price_log_id'],
                'booking_attempt_id' => !empty($result['booking_attempt_id'])
                    ? hashid_encode(HashIdService::BOOKING_ATTEMPT, (int) $result['booking_attempt_id'])
                    : null,
                'request_id'         => $result['request_id'],
                'response_file_path' => $result['response_file_path'],
            ]);
        } catch (Exception $exception) {
            report($exception);
            return response()->json([
                'status'  => false,
                'message' => 'Price confirmation failed. Please try again.',
            ], 500);
        }
    }

    public function viewPriceLog(Request $request)
    {
        $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $log = BookingPriceLog::find($request->input('id'));
        if (!$log) {
            return $this->ErrorResponse('Price log not found', [], 404);
        }

        $path = storage_path('app/' . ltrim((string) $log->response_file_path, '/'));
        if (empty($log->response_file_path) || !File::exists($path)) {
            return $this->ErrorResponse('Response file not found', [], 404);
        }

        $content = json_decode(File::get($path), true);

        return $this->SuccessResponse([
            'id'                  => $log->id,
            'request_id'          => $log->request_id,
            'response_file_path'  => $log->response_file_path,
            'response_json'       => $content,
            'price_payload'       => $log->price_payload,
        ], 'Price response loaded successfully.');
    }
}
