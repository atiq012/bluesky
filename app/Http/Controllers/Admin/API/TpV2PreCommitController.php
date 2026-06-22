<?php

namespace App\Http\Controllers\Admin\API;

use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use App\Services\SearchV2\TpV2AddSsrService;
use App\Services\SearchV2\TpV2TravelAgencyService;
use App\Services\SearchV2\BookingPaxPreferenceService;

class TpV2PreCommitController extends BaseController
{
    public function __construct(
        private readonly TpV2AddSsrService $ssrService,
        private readonly TpV2TravelAgencyService $agencyService,
        private readonly BookingPaxPreferenceService $paxPreferenceService
    ) {}

    public function applySsr(Request $request)
    {
        $request->validate([
            'workbench_identifier'          => ['required', 'string'],
            'session_id'                    => ['required', 'integer'],
            'travelers'                     => ['nullable', 'array', 'max:9'],
            'travelers.*.sequence'          => ['required_with:travelers', 'integer', 'min:1'],
            'travelers.*.meal_preference'   => ['nullable', 'string', 'max:50'],
            'travelers.*.wheelchair_needed' => ['nullable', 'boolean'],
        ]);

        try {
            if ($request->filled('travelers')) {
                $this->paxPreferenceService->syncForWorkbench(
                    $request->input('workbench_identifier'),
                    (int) $request->input('session_id'),
                    $request->input('travelers'),
                    optional(auth()->user())->id
                );
            }

            $result = $this->ssrService->apply(
                [
                    'workbench_identifier' => $request->input('workbench_identifier'),
                    'session_id'           => $request->input('session_id'),
                ],
                optional(auth()->user())->id
            );

            return response()->json([
                'status'              => true,
                'message'             => $result['message'] ?? 'SSR applied.',
                'skipped'             => (bool) ($result['skipped'] ?? false),
                'travelport_response' => $result['travelport_response'] ?? null,
            ]);
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'status'  => false,
                'message' => $exception->getMessage() ?: 'Failed to apply special service requests.',
            ], 500);
        }
    }

    public function saveTravelAgency(Request $request)
    {
        $request->validate([
            'workbench_identifier'     => ['required', 'string'],
            'session_id'               => ['required', 'integer'],
            'agency'                   => ['required', 'array'],
            'agency.name'              => ['nullable', 'string', 'max:150'],
            'agency.iata_number'       => ['nullable', 'string', 'max:50'],
            'agency.phone'             => ['nullable', 'string', 'max:30'],
            'agency.email'             => ['nullable', 'email', 'max:150'],
            'agency.address_line'      => ['nullable', 'string', 'max:255'],
            'agency.city'              => ['nullable', 'string', 'max:100'],
            'agency.country_code'      => ['nullable', 'string', 'max:3'],
        ]);

        try {
            $result = $this->agencyService->save(
                [
                    'workbench_identifier' => $request->input('workbench_identifier'),
                    'session_id'           => $request->input('session_id'),
                    'agency'               => $request->input('agency'),
                ],
                optional(auth()->user())->id
            );

            return response()->json([
                'status'              => true,
                'message'             => 'Travel agency details saved.',
                'travelport_response' => $result['travelport_response'] ?? null,
            ]);
        } catch (Exception $exception) {
            report($exception);

            $code = str_contains($exception->getMessage(), 'required')
                || str_contains($exception->getMessage(), 'Invalid')
                ? 422
                : 500;

            return response()->json([
                'status'  => false,
                'message' => $exception->getMessage() ?: 'Failed to save travel agency details.',
            ], $code);
        }
    }
}
