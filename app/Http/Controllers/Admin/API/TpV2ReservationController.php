<?php

namespace App\Http\Controllers\Admin\API;

use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use App\Services\HashIdService;
use App\Services\SearchV2\TpV2AddOfferService;
use App\Services\SearchV2\TpV2ReservationService;

class TpV2ReservationController extends BaseController
{
    public function __construct(
        private readonly TpV2ReservationService $reservationService,
        private readonly TpV2AddOfferService    $addOfferService
    ) {}

    public function initiateWorkbench(Request $request)
    {
        $request->validate([
            'price_log_id'     => ['required', 'integer'],
            'offer_identifier' => ['nullable', 'string'],
            'selection_json'   => ['nullable', 'array'],
        ]);

        try {
            $result = $this->reservationService->initiateWorkbench(
                [
                    'price_log_id'     => $request->input('price_log_id'),
                    'offer_identifier' => $request->input('offer_identifier'),
                    'selection_json'   => $request->input('selection_json'),
                ],
                optional(auth()->user())->id
            );

            return response()->json([
                'status'               => true,
                'message'              => 'Workbench initiated.',
                'workbench_identifier' => $result['workbench_identifier'],
                'session_id'           => $result['session_id'],
                'booking_attempt_id'   => !empty($result['booking_attempt_id'])
                    ? hashid_encode(HashIdService::BOOKING_ATTEMPT, (int) $result['booking_attempt_id'])
                    : null,
                'travelport_response'  => $result['travelport_response'] ?? null,
            ]);
        } catch (Exception $exception) {
            report($exception);
            return response()->json([
                'status'  => false,
                'message' => 'Failed to initiate reservation workbench. Please try again.',
            ], 500);
        }
    }

    public function addOffer(Request $request)
    {
        $request->validate([
            'workbench_identifier' => ['required', 'string'],
            'session_id'           => ['required', 'integer'],
        ]);

        try {
            $result = $this->addOfferService->addOffer(
                [
                    'workbench_identifier' => $request->input('workbench_identifier'),
                    'session_id'           => $request->input('session_id'),
                ],
                optional(auth()->user())->id
            );

            return response()->json([
                'status'              => true,
                'message'             => 'Offer added to workbench.',
                'content_source'      => $result['content_source'] ?? 'GDS',
                'travelport_response' => $result['travelport_response'] ?? null,
            ]);
        } catch (Exception $exception) {
            report($exception);
            $message = 'Failed to add offer to workbench. Please try again.';

            if (stripos($exception->getMessage(), 'COMMUNICATION ERROR') !== false) {
                $message = 'Travelport is temporarily unavailable while adding the offer. Please retry in a few moments.';
            }

            return response()->json([
                'status'  => false,
                'message' => $message,
            ], 500);
        }
    }
}
