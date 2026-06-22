<?php

namespace App\Http\Controllers\Admin\API;

use Exception;
use Illuminate\Http\Request;
use App\Services\HashIdService;
use App\Services\SearchV2\TravelportFareRulesService;
use App\Http\Controllers\BaseController;

class TravelportFareRulesController extends BaseController
{
    public function __construct(
        private readonly TravelportFareRulesService $fareRulesService
    ) {}

    public function index(Request $request)
    {
        $request->validate([
            'catalogProductOfferingsIdentifier' => ['required', 'string'],
            'catalogProductOfferingID'          => ['required', 'string'],
            'productIDs'                        => ['required', 'string'],
            'fareRuleType'                      => ['nullable', 'string'],
            'direction'                         => ['nullable', 'string', 'in:outbound,inbound'],
            'booking_attempt_id'                => ['nullable', 'string'],
        ]);

        try {
            $params = [
                'catalogProductOfferingsIdentifier' => $request->input('catalogProductOfferingsIdentifier'),
                'catalogProductOfferingID'          => $request->input('catalogProductOfferingID'),
                'productIDs'                        => $request->input('productIDs'),
                'fareRuleType'                      => $request->input('fareRuleType', 'Structured'),
            ];

            $rawAttemptId = null;
            if ($request->filled('booking_attempt_id')) {
                $rawAttemptId = hashid_decode(HashIdService::BOOKING_ATTEMPT, (string) $request->input('booking_attempt_id'));
            }

            $result = $this->fareRulesService->getFareRules(
                $params,
                optional(auth()->user())->id,
                $request->input('direction', 'outbound'),
                $rawAttemptId
            );

            return response()->json([
                'status'       => true,
                'fare_rules'   => ['segments' => $result['segments']],
                'download_key' => $result['download_key'],
            ]);
        } catch (Exception $exception) {
            report($exception);
            return response()->json([
                'status'  => false,
                'message' => 'Failed to fetch fare rules. Please try again.',
            ], 500);
        }
    }

    public function download(Request $request)
    {
        $request->validate(['key' => ['required', 'string']]);

        try {
            $files = $this->fareRulesService->getDownloadFiles(
                $request->input('key'),
                optional(auth()->user())->id
            );

            return response()->json([
                'status'   => true,
                'payload'  => $files['payload'],
                'response' => $files['response'],
            ]);
        } catch (Exception $exception) {
            $msg = $exception->getMessage();
            $code = $msg === 'Unauthorized.' ? 403 : ($msg === 'Download files not found.' ? 404 : 500);
            return response()->json(['status' => false, 'message' => $msg], $code);
        }
    }
}
