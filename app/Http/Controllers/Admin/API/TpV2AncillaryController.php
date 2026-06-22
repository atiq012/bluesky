<?php

namespace App\Http\Controllers\Admin\API;

use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use App\Services\SearchV2\TpV2AncillaryShopService;
use App\Services\SearchV2\TpV2BookAncillaryService;

class TpV2AncillaryController extends BaseController
{
    public function __construct(
        private readonly TpV2AncillaryShopService  $shopService,
        private readonly TpV2BookAncillaryService  $bookService
    ) {}

    public function shop(Request $request)
    {
        $request->validate([
            'workbench_identifier' => ['required', 'string'],
            'session_id'           => ['required', 'integer'],
        ]);

        try {
            $result = $this->shopService->shop(
                [
                    'workbench_identifier' => $request->input('workbench_identifier'),
                    'session_id'           => $request->input('session_id'),
                ],
                optional(auth()->user())->id
            );

            $noAncillaries = (bool) ($result['no_ancillaries'] ?? false);

            return response()->json([
                'status'              => true,
                'message'             => $result['message'] ?? ($noAncillaries
                    ? TpV2AncillaryShopService::EMPTY_MESSAGE
                    : 'Ancillary options loaded.'),
                'ancillary_items'     => $result['ancillary_items'],
                'no_ancillaries'      => $noAncillaries,
                'travelport_response' => $result['travelport_response'] ?? null,
            ]);
        } catch (Exception $exception) {
            report($exception);

            if ($this->shopService->isHardFailureMessage($exception->getMessage())) {
                return response()->json([
                    'status'  => false,
                    'message' => $exception->getMessage(),
                ], 500);
            }

            return response()->json([
                'status'          => true,
                'message'         => TpV2AncillaryShopService::EMPTY_MESSAGE,
                'ancillary_items' => [],
                'no_ancillaries'  => true,
            ]);
        }
    }

    public function book(Request $request)
    {
        $request->validate([
            'workbench_identifier'                          => ['required', 'string'],
            'session_id'                                    => ['required', 'integer'],
            'ancillaries'                                   => ['required', 'array', 'min:1'],
            'ancillaries.*.catalog_offerings_group_id'      => ['required', 'string'],
            'ancillaries.*.catalog_offerings_identifier_value' => ['required', 'string'],
            'ancillaries.*.catalog_offering_id'             => ['required', 'string'],
            'ancillaries.*.product_id'                      => ['required', 'string'],
            'ancillaries.*.traveler_ref_id'                 => ['required', 'string'],
            'ancillaries.*.quantity'                        => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $result = $this->bookService->book(
                [
                    'workbench_identifier' => $request->input('workbench_identifier'),
                    'session_id'           => $request->input('session_id'),
                    'ancillaries'          => $request->input('ancillaries'),
                ],
                optional(auth()->user())->id
            );

            return response()->json([
                'status'              => true,
                'message'             => 'Ancillary added to booking.',
                'travelport_response' => $result['travelport_response'] ?? null,
            ]);
        } catch (Exception $exception) {
            report($exception);
            return response()->json([
                'status'  => false,
                'message' => $exception->getMessage() ?: 'Failed to add ancillary.',
            ], 500);
        }
    }
}
