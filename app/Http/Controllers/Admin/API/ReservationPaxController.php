<?php

namespace App\Http\Controllers\Admin\API;

use Exception;
use Illuminate\Http\Request;
use App\Models\BookingPax;
use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\Storage;
use App\Services\SearchV2\TpV2AddTravelerService;
use App\Services\SearchV2\BookingPaxPreferenceService;

class ReservationPaxController extends BaseController
{
    public function __construct(
        private readonly TpV2AddTravelerService $addTravelerService,
        private readonly BookingPaxPreferenceService $paxPreferenceService
    ) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'workbench_identifier' => ['required', 'string'],
            'session_id'           => ['required', 'integer', 'exists:booking_sessions,id'],
            'travelers'            => ['required', 'array', 'min:1', 'max:9'],
            'travelers.*.pax_type' => ['required', 'in:ADT,CNN,INF'],
            'travelers.*.sequence' => ['required', 'integer', 'min:1'],
            'travelers.*.first_name' => ['required', 'string', 'max:150'],
            'travelers.*.last_name'  => ['required', 'string', 'max:150'],
            'travelers.*.dob'        => ['required', 'string'],
            'travelers.*.gender'     => ['required', 'string', 'in:Male,Female,Others'],
            'travelers.*.is_primary_contact' => ['nullable', 'boolean'],
            'travelers.*.title' => ['nullable', 'string', 'max:10'],
            'travelers.*.middle_name' => ['nullable', 'string', 'max:150'],
            'travelers.*.nationality' => ['nullable', 'string', 'max:80'],
            'travelers.*.passport_number' => ['nullable', 'string', 'max:100'],
            'travelers.*.passport_expiry_date' => ['nullable', 'string'],
            'travelers.*.email' => ['nullable', 'string', 'max:150'],
            'travelers.*.phone' => ['nullable', 'string', 'max:30'],
            'travelers.*.meal_preference' => ['nullable', 'string', 'max:50'],
            'travelers.*.wheelchair_needed' => ['nullable', 'boolean'],
            'travelers.*.frequent_flyer_number' => ['nullable', 'string', 'max:100'],
            'travelers.*.traveller_id' => ['nullable', 'integer', 'exists:travellers,id'],
        ]);

        try {
            $result = $this->addTravelerService->addTravelers(
                $validated,
                optional(auth()->user())->id
            );

            return response()->json([
                'status'                  => true,
                'message'                 => 'Travelers added.',
                'pax_ids'                     => $result['pax_ids'],
                'travelport_traveler_ids'     => $result['travelport_traveler_ids'],
                'travelport_traveler_ref_ids' => $result['travelport_traveler_ref_ids'] ?? [],
                'travelport_response'         => $result['travelport_response'] ?? null,
            ]);
        } catch (Exception $exception) {
            report($exception);

            $code = str_contains($exception->getMessage(), 'Maximum')
                || str_contains($exception->getMessage(), 'required')
                || str_contains($exception->getMessage(), 'Invalid')
                || str_contains($exception->getMessage(), 'primary')
                ? 422
                : 500;

            return response()->json([
                'status'  => false,
                'message' => $exception->getMessage() ?: 'Failed to add travelers. Please try again.',
            ], $code);
        }
    }

    public function syncPreferences(Request $request)
    {
        $validated = $request->validate([
            'workbench_identifier'          => ['required', 'string'],
            'session_id'                    => ['required', 'integer', 'exists:booking_sessions,id'],
            'travelers'                     => ['required', 'array', 'min:1', 'max:9'],
            'travelers.*.sequence'          => ['required', 'integer', 'min:1'],
            'travelers.*.meal_preference'   => ['nullable', 'string', 'max:50'],
            'travelers.*.wheelchair_needed' => ['nullable', 'boolean'],
        ]);

        try {
            $updated = $this->paxPreferenceService->syncForWorkbench(
                $validated['workbench_identifier'],
                (int) $validated['session_id'],
                $validated['travelers'],
                optional(auth()->user())->id
            );

            return response()->json([
                'status'  => true,
                'message' => 'Traveler preferences updated.',
                'updated' => $updated,
            ]);
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'status'  => false,
                'message' => $exception->getMessage() ?: 'Failed to update traveler preferences.',
            ], 422);
        }
    }

    public function uploadFiles(Request $request, int $id)
    {
        $request->validate([
            'passport_image' => ['nullable', 'image', 'max:1024'],
            'visa_image'     => ['nullable', 'image', 'max:1024'],
        ]);

        $pax = BookingPax::find($id);

        if (!$pax) {
            return response()->json([
                'status'  => false,
                'message' => 'Traveler record not found.',
            ], 404);
        }

        $dir = "reservation/pax/{$id}";
        $updates = [];

        if ($request->hasFile('passport_image')) {
            $path = $request->file('passport_image')->store($dir, 'public');
            $updates['passport_image_path'] = $path;
        }

        if ($request->hasFile('visa_image')) {
            $path = $request->file('visa_image')->store($dir, 'public');
            $updates['visa_image_path'] = $path;
        }

        if (empty($updates)) {
            return response()->json([
                'status'  => false,
                'message' => 'No files uploaded.',
            ], 422);
        }

        $updates['updated_by'] = optional(auth()->user())->id;
        $pax->update($updates);

        return response()->json([
            'status'  => true,
            'message' => 'Files uploaded.',
            'passport_image_path' => $pax->passport_image_path,
            'visa_image_path'     => $pax->visa_image_path,
        ]);
    }
}
