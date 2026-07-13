<?php
namespace App\Http\Controllers\Admin\Group;

use App\Http\Controllers\BaseController;
use App\Models\GroupRequest\GroupRequest;
use App\Models\GroupRequest\GroupRequestSegment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class GroupController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = DB::table('group_requests')->get();
        return DataTables::of($data)->addIndexColumn()->make(true);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */

    public function store(Request $request)
    {
        // dd($request->all());
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
                    'departure_date'   => date('Y-m-d H:i', strtotime($flight['departureDate'])) ?? null,
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

    private function generateGroupCode()
    {
                                                               // Assuming you have a 'groups' table with an auto-increment 'id'
                                                               // Get the next auto-increment value
        $nextId = \DB::table('group_requests')->max('id') + 1; // Or use your ORM

        // Pad with zeros to make it 8 digits
        $sequentialNumber = str_pad($nextId, 8, '0', STR_PAD_LEFT);

        return 'GR' . $sequentialNumber;
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
