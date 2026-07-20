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

        $data = DB::table('group_requests')
            ->where('group_requests.agent_code', Auth::user()->agent->agent_code)
            ->leftJoin('users as u', 'u.id', '=', 'group_requests.created_by')
            ->leftJoin('users as u2', 'u2.id', '=', 'group_requests.updated_by')
            ->leftJoin('agents as a', 'a.agent_code', '=', 'group_requests.agent_code')
            ->leftJoin('group_request_segments', 'group_requests.id', '=', 'group_request_segments.group_request_id')
            ->select('group_requests.*', 'u.name as createdby', 'u2.name as updatedby','a.name as agent_name',
                DB::raw('f_username(NULLIF(group_requests.assigned_to, "")) as assigned_to_kam'),
                DB::raw('GROUP_CONCAT(
                CONCAT_WS("- ",
                    group_request_segments.origin,
                    group_request_segments.destination
                )
                ORDER BY group_request_segments.segment_order
                SEPARATOR " | "
            ) as segments_info'),
                DB::raw('GROUP_CONCAT(
                CONCAT_WS("- ",
                    DATE_FORMAT(group_request_segments.departure_date, "%d-%b-%Y %l:%i %p")
                )
                ORDER BY group_request_segments.segment_order
                SEPARATOR " | "
            ) as segments_date')
            )
            ->groupBy('group_requests.id');

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('route_display', function ($row) {
                if ($row->request_type === 'multicity' && $row->segments_info) {
                    return $row->segments_info;
                }
                $route = $row->origin . ' -> ' . $row->destination;
                if ($row->request_type === 'roundway' && $row->return_origin) {
                    $route .= ' | ' . $row->return_origin . ' -> ' . $row->return_destination;
                }

                return $route;
            })
            ->addColumn('route_date_display', function ($row) {
                if ($row->request_type === 'multicity' && $row->segments_date) {
                    return $row->segments_date;
                }

                $route = "<i class='fa-regular fa-calendar me-1' style='font-size: 0.65rem;'></i> " . $row->departure_date;

                return $route;
            })
            ->make(true);
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

        $groupCode = $this->generateGroupCode();

        $groupRequest = GroupRequest::create([
            'agent_code'              => $request->agent_code ?? auth()->user()->agent->agent_code ?? null,
            'group_code'              => $groupCode,
            'status'                  => 'New Request',
            'request_type'            => $request->tripType,
            'group_type'              => $request->groupType,
            'class_type'              => $request->preferredClass ?? null,
            'class_code'              => $request->code ?? null,

            // One-way / Round-way
            'origin'                  => $request->from ?? null,
            'destination'             => $request->to ?? null,
            'departure_date'          => $request->departureDate ?? null,
            'return_origin'           => $request->returnFrom ?? null,
            'return_destination'      => $request->returnTo ?? null,
            'return_date'             => $request->returnDate ?? null,
            'preferred_flight'        => $request->preferredAirlines ?? null,
            'flight_no'               => $request->flightNo ?? null,

            // Multi-city airline stored here
            'preferred_return_flight' => $request->tripType === 'multicity'
                ? ($request->preferredAirline ?? null)
                : null,

            // Passengers
            'adult_traveler'          => $request->adult ?? 0,
            'child_traveler'          => $request->children ?? 0,
            'infant_traveler'         => $request->infants ?? 0,
            'total_traveler'          => ($request->adult ?? 0)
             + ($request->children ?? 0)
             + ($request->infants ?? 0),

            // Fare
            'per_person_fare'         => $request->perPersonFare ?? null,
            'currency'                => $request->currency ?? 'BDT',

            // Requirements
            'special_requirements'    => $request->specialRequirements ?? null,
            'details_requirements'    => $request->detailsRequirements ?? null,
            'remarks'                 => $request->remarks ?? null,

            // Audit
            'created_by'              => auth()->id(),
            'updated_by'              => auth()->id(),
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
        // Get the highest ID
        $nextId           = \DB::table('group_requests')->max('id') + 1;
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
    public function edit(Request $request)
    {
        $groupId = $request->id;

        if (! $groupId) {
            return $this->ErrorResponse('Group ID is required for editing.', 'Group PNR ID is required for editing.');
        }

        $groupData = GroupRequest::with(['segments'])->find($groupId);
        if (! $groupData) {
            return $this->ErrorResponse('Group not found.', 'Group PNR not found.');
        }

        return $this->SuccessResponse($groupData, 'Group retrieved successfully for editing.');
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
    public function destroy(Request $request)
    {
        $id = $request->id;

        if (! $id) {
            return $this->ErrorResponse('Group ID is required for deleting.', 'Group ID is required for deleting.');
        }

        $group = GroupRequest::find($id);
        if (! $group) {
            return $this->ErrorResponse('Group not found.', 'Group not found.');
        }

        $group->status = 'Request Cancelled';
        $group->remarks = $request->note;
        $group->save();

        return $this->SuccessResponse($group, 'Group deleted successfully.');
    }
}
