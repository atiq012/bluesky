<?php

namespace App\Http\Controllers\Admin\Group;

use App\Http\Controllers\Controller;
use App\Models\GroupRequest;
use App\Models\GroupRequestSegment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    // ─── List All ───────────────────────────────────
    public function index(Request $request)
    {
        $query = GroupRequest::with('segments');

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('request_type')) {
            $query->where('request_type', $request->request_type);
        }
        if ($request->filled('group_type')) {
            $query->where('group_type', $request->group_type);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('group_code', 'LIKE', "%{$search}%")
                  ->orWhere('origin', 'LIKE', "%{$search}%")
                  ->orWhere('destination', 'LIKE', "%{$search}%")
                  ->orWhere('preferred_flight', 'LIKE', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'id');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        // Paginate
        $perPage = $request->get('per_page', 15);
        $data = $query->paginate($perPage);

        return response()->json([
            'message' => 'Group requests retrieved successfully',
            'data'    => $data,
        ]);
    }

    // ─── Single View ────────────────────────────────
    public function show($id)
    {
        $groupRequest = GroupRequest::with('segments')->findOrFail($id);

        return response()->json([
            'message' => 'Group request retrieved successfully',
            'data'    => $groupRequest,
        ]);
    }

    // ─── Create ─────────────────────────────────────
    public function store(Request $request)
    {
        $groupCode = $this->generateGroupCode();

        $groupRequest = GroupRequest::create([
            'agent_code'             => $request->agent_code ?? auth()->user()->agent_code ?? null,
            'group_code'             => $groupCode,
            'status'                 => 'New Request',
            'request_type'           => $request->tripType,
            'group_type'             => $request->groupType,
            'class_type'             => $request->preferredClass ?? null,
            'class_code'             => $request->code ?? null,

            // One-way / Round-way
            'origin'                 => $request->from ?? null,
            'destination'            => $request->to ?? null,
            'departure_date'         => $request->departureDate ?? null,
            'return_origin'          => $request->returnFrom ?? null,
            'return_destination'     => $request->returnTo ?? null,
            'return_date'            => $request->returnDate ?? null,
            'preferred_flight'       => $request->preferredAirlines ?? null,
            'flight_no'              => $request->flightNo ?? null,

            // Multi-city airline stored here
            'preferred_return_flight' => $request->tripType === 'multicity'
                                         ? ($request->preferredAirline ?? null)
                                         : null,

            // Passengers
            'adult_traveler'         => $request->adult ?? 0,
            'child_traveler'         => $request->children ?? 0,
            'infant_traveler'        => $request->infants ?? 0,
            'total_traveler'         => ($request->adult ?? 0)
                                         + ($request->children ?? 0)
                                         + ($request->infants ?? 0),

            // Fare
            'per_person_fare'        => $request->perPersonFare ?? null,
            'currency'               => $request->currency ?? 'BDT',

            // Requirements
            'special_requirements'   => $request->specialRequirements ?? null,
            'details_requirements'   => $request->detailsRequirements ?? null,
            'remarks'                => $request->remarks ?? null,

            // Audit
            'created_by'             => auth()->id(),
            'updated_by'             => auth()->id(),
        ]);

        // Multi-city segments
        if ($request->tripType === 'multicity' && $request->filled('flights')) {
            $segments = [];
            foreach ($request->flights as $index => $flight) {
                $segments[] = [
                    'group_request_id' => $groupRequest->id,
                    'segment_order'    => $index + 1,
                    'origin'           => $flight['from'] ?? null,
                    'destination'      => $flight['to'] ?? null,
                    'departure_date'   => $flight['departureDate'] ?? null,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
            }
            GroupRequestSegment::insert($segments);
        }

        return response()->json([
            'message' => 'Group request created successfully',
            'data'    => $groupRequest->load('segments'),
        ], 201);
    }

    // ─── Update ─────────────────────────────────────
    public function update(Request $request, $id)
    {
        $groupRequest = GroupRequest::findOrFail($id);

        $groupRequest->update([
            'agent_code'             => $request->agent_code ?? $groupRequest->agent_code,
            'request_type'           => $request->tripType ?? $groupRequest->request_type,
            'group_type'             => $request->groupType ?? $groupRequest->group_type,
            'class_type'             => $request->preferredClass ?? $groupRequest->class_type,
            'class_code'             => $request->code ?? $groupRequest->class_code,

            'origin'                 => $request->from ?? $groupRequest->origin,
            'destination'            => $request->to ?? $groupRequest->destination,
            'departure_date'         => $request->departureDate ?? $groupRequest->departure_date,
            'return_origin'          => $request->returnFrom ?? $groupRequest->return_origin,
            'return_destination'     => $request->returnTo ?? $groupRequest->return_destination,
            'return_date'            => $request->returnDate ?? $groupRequest->return_date,
            'preferred_flight'       => $request->preferredAirlines ?? $groupRequest->preferred_flight,
            'flight_no'              => $request->flightNo ?? $groupRequest->flight_no,

            'preferred_return_flight' => $request->tripType === 'multicity'
                                         ? ($request->preferredAirline ?? $groupRequest->preferred_return_flight)
                                         : $groupRequest->preferred_return_flight,

            'adult_traveler'         => $request->adult ?? $groupRequest->adult_traveler,
            'child_traveler'         => $request->children ?? $groupRequest->child_traveler,
            'infant_traveler'        => $request->infants ?? $groupRequest->infant_traveler,
            'total_traveler'         => ($request->adult ?? $groupRequest->adult_traveler)
                                         + ($request->children ?? $groupRequest->child_traveler)
                                         + ($request->infants ?? $groupRequest->infant_traveler),

            'per_person_fare'        => $request->perPersonFare ?? $groupRequest->per_person_fare,
            'currency'               => $request->currency ?? $groupRequest->currency,

            'special_requirements'   => $request->specialRequirements ?? $groupRequest->special_requirements,
            'details_requirements'   => $request->detailsRequirements ?? $groupRequest->details_requirements,
            'remarks'                => $request->remarks ?? $groupRequest->remarks,

            'updated_by'             => auth()->id(),
        ]);

        // Rebuild segments if multicity
        if ($request->tripType === 'multicity' && $request->filled('flights')) {
            // Delete old segments
            GroupRequestSegment::where('group_request_id', $groupRequest->id)->delete();

            // Insert new segments
            $segments = [];
            foreach ($request->flights as $index => $flight) {
                $segments[] = [
                    'group_request_id' => $groupRequest->id,
                    'segment_order'    => $index + 1,
                    'origin'           => $flight['from'] ?? null,
                    'destination'      => $flight['to'] ?? null,
                    'departure_date'   => $flight['departureDate'] ?? null,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
            }
            GroupRequestSegment::insert($segments);
        }

        return response()->json([
            'message' => 'Group request updated successfully',
            'data'    => $groupRequest->load('segments'),
        ]);
    }

    // ─── Delete ─────────────────────────────────────
    public function destroy($id)
    {
        $groupRequest = GroupRequest::findOrFail($id);

        // Segments auto-deleted via cascadeOnDelete foreign key
        $groupRequest->delete();

        return response()->json([
            'message' => 'Group request deleted successfully',
        ]);
    }

    // ─── Status Update ──────────────────────────────
    public function updateStatus(Request $request, $id)
    {
        $groupRequest = GroupRequest::findOrFail($id);

        $groupRequest->update([
            'status'      => $request->status ?? $groupRequest->status,
            'decline_note' => $request->decline_note ?? $groupRequest->decline_note,
            'assigned_to'  => $request->assigned_to ?? $groupRequest->assigned_to,
            'assigned_date' => $request->assigned_to ? now() : $groupRequest->assigned_date,
            'min_proposed_fare'  => $request->min_proposed_fare ?? $groupRequest->min_proposed_fare,
            'max_proposed_fare'  => $request->max_proposed_fare ?? $groupRequest->max_proposed_fare,
            'ticketing_passenger' => $request->ticketing_passenger ?? $groupRequest->ticketing_passenger,
            'updated_by'  => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Status updated successfully',
            'data'    => $groupRequest,
        ]);
    }

    // ─── Generate Group Code ────────────────────────
    private function generateGroupCode()
    {
        $lastCode = GroupRequest::orderBy('id', 'desc')->value('group_code');

        if (!$lastCode || !preg_match('/GR(\d+)/', $lastCode, $matches)) {
            return 'GR00000001';
        }

        $nextNumber = (int) $matches[1] + 1;

        return 'GR' . str_pad($nextNumber, 8, '0', STR_PAD_LEFT);
    }
}
