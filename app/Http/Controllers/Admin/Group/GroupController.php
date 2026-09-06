<?php
namespace App\Http\Controllers\Admin\Group;

use App\Http\Controllers\BaseController;
use App\Models\AirlineLogo\AirlineLogo;
use App\Models\GroupRequest\GroupRequest;
use App\Models\GroupRequest\GroupRequestSegment;
use App\Models\GroupRequest\PriceOffer;
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
        // $data = DB::table('group_requests')
        //     ->where('group_requests.agent_code', Auth::user()->agent->agent_code)
        //     ->leftJoin('users as u', 'u.id', '=', 'group_requests.created_by')
        //     ->leftJoin('users as u2', 'u2.id', '=', 'group_requests.updated_by')
        //     ->leftJoin('users as u3', 'u3.id', '=', 'group_requests.assigned_to')
        //     ->leftJoin('agents as a', 'a.agent_code', '=', 'group_requests.agent_code')
        //     ->leftJoin('group_request_segments', 'group_requests.id', '=', 'group_request_segments.group_request_id')
        //     ->leftJoin('group_p_a_x_infos as pax', 'group_requests.id', '=', 'pax.group_id')
        //     ->leftJoin('price_offers as op', 'group_requests.id', '=', 'op.group_req_id')
        //     ->leftJoin('offer_price_payment_terms', 'op.id', '=', 'offer_price_payment_terms.offer_price_id')
        //     ->leftJoin('offer_price_segments as ops', 'op.id', '=', 'ops.offer_price_id')
        //     ->leftJoin('group_payments', 'group_requests.id', '=', 'group_payments.group_req_id')
        //     ->select(
        //         'group_requests.id',
        //         DB::raw("IF(group_requests.status = 'Accepted', op.request_type, group_requests.request_type) as request_type"),
        //         DB::raw("IF(group_requests.status = 'Accepted', op.group_type, group_requests.group_type) as group_type"),
        //         'group_requests.preferred_flight',
        //         'group_requests.created_at',
        //         'group_requests.updated_at',
        //         'group_requests.group_code',
        //         DB::raw("IF(group_requests.status = 'Accepted', op.origin, group_requests.origin) as origin"),
        //         DB::raw("IF(group_requests.status = 'Accepted', op.destination, group_requests.destination) as destination"),
        //         DB::raw("IF(group_requests.status = 'Accepted', op.return_origin, group_requests.return_origin) as return_origin"),
        //         DB::raw("IF(group_requests.status = 'Accepted', op.class_type, group_requests.class_type) as class_type"),
        //         DB::raw("IF(group_requests.status = 'Accepted', op.class_code, group_requests.class_code) as class_code"),
        //         DB::raw("IF(group_requests.status = 'Accepted', op.return_destination, group_requests.return_destination) as return_destination"),
        //         DB::raw("IF(group_requests.status = 'Accepted', op.departure_date, group_requests.departure_date) as departure_date"),
        //         DB::raw("IF(group_requests.status = 'Accepted', op.return_date, group_requests.return_date) as return_date"),
        //         'group_requests.assigned_to',
        //         'group_requests.created_by',
        //         'group_requests.updated_by',
        //         DB::raw("IF(group_requests.status = 'Accepted', op.adult_traveler, group_requests.adult_traveler) as adult_traveler"),
        //         DB::raw("IF(group_requests.status = 'Accepted', op.child_traveler, group_requests.child_traveler) as child_traveler"),
        //         DB::raw("IF(group_requests.status = 'Accepted', op.infant_traveler, group_requests.infant_traveler) as infant_traveler"),
        //         DB::raw("IF(group_requests.status = 'Accepted', op.total_traveler, group_requests.total_traveler) as total_traveler"),
        //         'group_requests.status',
        //         'group_requests.airline_code',
        //         'u3.phone as kam_phone',
        //         'u3.email as kam_email',
        //         // Add all other columns you need explicitly
        //         'u.name as createdby',
        //         'u2.name as updatedby',
        //         'a.name as agent_name',
        //         'a.agent_code as agent_code',
        //         'op.pnr as pnr',
        //         'op.currency as opCurrency',
        //         'op.estimate_net_payable as opEstimateNetPayable',
        //         'op.exchange_rate as opExchangeRate',
        //         'op.status as opstatus',
        //         DB::raw('(SELECT SUM(paid_amount)
        //   FROM group_payments
        //   WHERE group_req_id = group_requests.id
        //  ) as total_paid'),
        //         DB::raw('COUNT(pax.id) as pax_count'),
        //         DB::raw('f_username(NULLIF(group_requests.assigned_to, "")) as assigned_to_kam'),
        //         DB::raw('IF(group_requests.status = "Accepted",
        //     GROUP_CONCAT(DISTINCT
        //         CONCAT_WS("- ",
        //             ops.origin,
        //             ops.destination
        //         )
        //         ORDER BY ops.sequence
        //         SEPARATOR " | "
        //     ),
        //     GROUP_CONCAT(DISTINCT
        //         CONCAT_WS("- ",
        //             group_request_segments.origin,
        //             group_request_segments.destination
        //         )
        //         ORDER BY group_request_segments.segment_order
        //         SEPARATOR " | "
        //     )
        // ) as segments_info'),
        //         DB::raw('IF(group_requests.status = "Accepted",
        //     GROUP_CONCAT(DISTINCT
        //         CONCAT_WS("- ",
        //             DATE_FORMAT(ops.departure_date, "%d-%b-%Y %l:%i %p")
        //         )
        //         ORDER BY ops.sequence
        //         SEPARATOR " | "
        //     ),
        //     GROUP_CONCAT(DISTINCT
        //         CONCAT_WS("- ",
        //             DATE_FORMAT(group_request_segments.departure_date, "%d-%b-%Y %l:%i %p")
        //         )
        //         ORDER BY group_request_segments.segment_order
        //         SEPARATOR " | "
        //     )
        // ) as segments_date'),
        //  DB::raw('GROUP_CONCAT(DISTINCT
        //     CONCAT_WS("- ",
        //         offer_price_payment_terms.sequence,
        //         offer_price_payment_terms.amount
        //     ) ORDER BY offer_price_payment_terms.sequence
        //     SEPARATOR " | "
        // ) as segments_payment_info')
        //     )
        //     ->groupBy('group_requests.id')
        //     ->groupBy('group_requests.request_type')
        //     ->groupBy('group_requests.group_type',)
        //     ->groupBy('group_requests.preferred_flight')
        //     ->groupBy('group_requests.group_code')
        //     ->groupBy('group_requests.origin')
        //     ->groupBy('group_requests.destination')
        //     ->groupBy('group_requests.return_origin')
        //     ->groupBy('group_requests.return_destination')
        //     ->groupBy('group_requests.departure_date')
        //     ->groupBy('group_requests.class_type')
        //     ->groupBy('group_requests.class_code')
        //     ->groupBy('group_requests.return_date')
        //     ->groupBy('group_requests.assigned_to')
        //     ->groupBy('group_requests.created_by')
        //     ->groupBy('group_requests.updated_by')
        //     ->groupBy('group_requests.created_at')
        //     ->groupBy('group_requests.updated_at')
        //     ->groupBy('group_requests.adult_traveler')
        //     ->groupBy('group_requests.child_traveler')
        //     ->groupBy('group_requests.infant_traveler')
        //     ->groupBy('group_requests.total_traveler')
        //     ->groupBy('group_requests.status')
        //     ->groupBy('group_requests.airline_code')
        //     ->groupBy('u.name')
        //     ->groupBy('u3.phone')
        //     ->groupBy('u3.email')
        //     ->groupBy('u2.name')
        //     ->groupBy('a.name')
        //     ->groupBy('op.status')
        //     ->groupBy('op.pnr')
        //     ->groupBy('op.currency')
        //     ->groupBy('op.estimate_net_payable')
        //     ->groupBy('op.exchange_rate')
        //     ->groupBy('op.request_type')
        //     ->groupBy('op.group_type')
        //     ->groupBy('op.class_type')
        //     ->groupBy('op.class_code')
        //     ->groupBy('op.origin')
        //     ->groupBy('op.destination')
        //     ->groupBy('op.return_origin')
        //     ->groupBy('op.return_destination')
        //     ->groupBy('op.departure_date')
        //     ->groupBy('op.return_date')
        //     ->groupBy('op.adult_traveler')
        //     ->groupBy('op.child_traveler')
        //     ->groupBy('op.infant_traveler')
        //     ->groupBy('op.total_traveler')
        //     ->groupBy('a.agent_code')
        //     ->orderBy('group_requests.id', 'desc');

        // return DataTables::of($data)
        //     ->addIndexColumn()
        //     ->addColumn('route_display', function ($row) {
        //         if ($row->request_type === 'multicity' && $row->segments_info) {
        //             return $row->segments_info;
        //         }
        //         $route = $row->origin . ' -> ' . $row->destination;
        //         if ($row->request_type === 'roundway' && $row->return_origin) {
        //             $route .= ' | ' . $row->return_origin . ' -> ' . $row->return_destination;
        //         }

        //         return $route;
        //     })
        //     ->addColumn('route_date_display', function ($row) {
        //         if ($row->request_type === 'multicity' && $row->segments_date) {
        //             return $row->segments_date;
        //         }

        //         $route = "<i class='fa-regular fa-calendar me-1' style='font-size: 0.65rem;'></i> " . $row->departure_date;

        //         return $route;
        //     })->addColumn('payment_info', function ($row) {
        //         return $row->segments_payment_info;
        //     })
        //     ->make(true);

        $data = DB::table('group_requests')
        ->where('group_requests.agent_code', Auth::user()->agent->agent_code)
            ->leftJoin('users as u', 'u.id', '=', 'group_requests.created_by')
            ->leftJoin('users as u2', 'u2.id', '=', 'group_requests.updated_by')
            ->leftJoin('agents as a', 'a.agent_code', '=', 'group_requests.agent_code')
            ->leftJoin('group_request_segments', 'group_requests.id', '=', 'group_request_segments.group_request_id')
            ->leftJoin('price_offers as op', 'group_requests.id', '=', 'op.group_req_id')
            ->leftJoin('offer_price_payment_terms', 'op.id', '=', 'offer_price_payment_terms.offer_price_id')
            ->leftJoin('group_payments', 'group_requests.id', '=', 'group_payments.group_req_id')
            ->leftJoin('users as u3', 'u3.id', '=', 'group_requests.assigned_to')
            ->select(
                'group_requests.id',
                'group_requests.group_pnr_id',
                DB::raw('COALESCE(op.request_type, group_requests.request_type) as request_type'),
                DB::raw('COALESCE(op.group_type, group_requests.group_type) as group_type'),
                'group_requests.preferred_flight',
                'group_requests.created_at',
                'group_requests.updated_at',
                'group_requests.group_code',
                DB::raw('COALESCE(op.origin, group_requests.origin) as origin'),
                DB::raw('COALESCE(op.destination, group_requests.destination) as destination'),
                DB::raw('COALESCE(op.return_origin, group_requests.return_origin) as return_origin'),
                DB::raw('COALESCE(op.class_type, group_requests.class_type) as class_type'),
                DB::raw('COALESCE(op.class_code, group_requests.class_code) as class_code'),
                DB::raw('COALESCE(op.return_destination, group_requests.return_destination) as return_destination'),
                DB::raw('COALESCE(op.departure_date, group_requests.departure_date) as departure_date'),
                DB::raw('COALESCE(op.return_date, group_requests.return_date) as return_date'),
                'group_requests.assigned_to',
                'group_requests.created_by',
                'group_requests.updated_by',
                DB::raw('COALESCE(op.adult_traveler, group_requests.adult_traveler) as adult_traveler'),
                DB::raw('COALESCE(op.child_traveler, group_requests.child_traveler) as child_traveler'),
                DB::raw('COALESCE(op.infant_traveler, group_requests.infant_traveler) as infant_traveler'),
                DB::raw('COALESCE(op.total_traveler, group_requests.total_traveler) as total_traveler'),
                'group_requests.status',
                'group_requests.airline_code',
                'u.name as createdby',
                'u2.name as updatedby',
                'u3.phone as kam_phone',
                'u3.email as kam_email',
                'a.name as agent_name',
                'a.agent_code as agent_code',
                'a.email as agent_email',
                'a.phone as agent_phone',
                'op.pnr as pnr',
                'op.currency as opCurrency',
                'op.estimate_net_payable as opEstimateNetPayable',
                'op.exchange_rate as opExchangeRate',
                'op.status as opstatus',
                'op.updated_at as opupdated_at',
                DB::raw('(SELECT SUM(paid_amount)
          FROM group_payments
          WHERE group_req_id = group_requests.id
          AND status = "Success"
         ) as total_paid'),
                DB::raw('f_username(NULLIF(group_requests.assigned_to, "")) as assigned_to_kam'),
                DB::raw('GROUP_CONCAT(DISTINCT
            CONCAT_WS("- ",
                group_request_segments.origin,
                group_request_segments.destination
            )
            ORDER BY group_request_segments.segment_order
            SEPARATOR " | "
        ) as segments_info'),
                DB::raw('GROUP_CONCAT(DISTINCT
            CONCAT_WS("- ",
                DATE_FORMAT(group_request_segments.departure_date, "%d-%b-%Y %l:%i %p")
            )
            ORDER BY group_request_segments.segment_order
            SEPARATOR " | "
        ) as segments_date'),
                DB::raw('GROUP_CONCAT(DISTINCT
            CONCAT_WS("- ",
                offer_price_payment_terms.sequence,
                offer_price_payment_terms.amount
            ) ORDER BY offer_price_payment_terms.sequence
            SEPARATOR " | "
        ) as segments_payment_info')
            )
            ->groupBy('group_requests.id')
            ->groupBy('group_requests.group_pnr_id')
            ->groupBy('group_requests.request_type')
            ->groupBy('group_requests.group_code')
            ->groupBy('group_requests.group_type', )
            ->groupBy('group_requests.preferred_flight')
            ->groupBy('group_requests.origin')
            ->groupBy('group_requests.destination')
            ->groupBy('group_requests.return_origin')
            ->groupBy('group_requests.return_destination')
            ->groupBy('group_requests.departure_date')
            ->groupBy('group_requests.class_type')
            ->groupBy('group_requests.class_code')
            ->groupBy('group_requests.return_date')
            ->groupBy('group_requests.assigned_to')
            ->groupBy('group_requests.created_by')
            ->groupBy('group_requests.updated_by')
            ->groupBy('group_requests.created_at')
            ->groupBy('group_requests.updated_at')
            ->groupBy('group_requests.adult_traveler')
            ->groupBy('group_requests.child_traveler')
            ->groupBy('group_requests.infant_traveler')
            ->groupBy('group_requests.total_traveler')
            ->groupBy('group_requests.status')
            ->groupBy('group_requests.airline_code')
            ->groupBy('group_requests.updated_at')
            ->groupBy('op.request_type')
            ->groupBy('op.group_type')
            ->groupBy('op.origin')
            ->groupBy('op.destination')
            ->groupBy('op.return_origin')
            ->groupBy('op.return_destination')
            ->groupBy('op.departure_date')
            ->groupBy('op.return_date')
            ->groupBy('op.class_type')
            ->groupBy('op.class_code')
            ->groupBy('op.adult_traveler')
            ->groupBy('op.child_traveler')
            ->groupBy('op.infant_traveler')
            ->groupBy('op.total_traveler')
            ->groupBy('u.name')
            ->groupBy('u2.name')
            ->groupBy('u3.phone')
            ->groupBy('u3.email')
            ->groupBy('a.name')
            ->groupBy('a.email')
            ->groupBy('a.phone')
            ->groupBy('op.pnr')
            ->groupBy('op.status')
            ->groupBy('op.currency')
            ->groupBy('op.estimate_net_payable')
            ->groupBy('op.exchange_rate')
            ->groupBy('op.updated_at')
            ->groupBy('a.agent_code')
            ->orderBy('group_requests.id', 'desc');

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
            ->addColumn('payment_info', function ($row) {
                return $row->segments_payment_info;
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
        $airline = AirlineLogo::where('name', $request->preferredAirlines)->first();

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
            'airline_code'            => $airline->code ?? null,
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
            // 'updated_by'              => auth()->id(),
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

    public function showOffer(Request $request)
    {
        $groupId = $request->id;

        if (! $groupId) {
            return $this->ErrorResponse('Group ID is required for editing.', 'Group PNR ID is required for editing.');
        }

        $groupData = GroupRequest::with(['segments','agent'])->find($groupId);

        if (! $groupData) {
            return $this->ErrorResponse('Group not found.', 'Group PNR not found.');
        }

        //  offer price
        $offer = PriceOffer::with(['segments', 'paymentTerms', 'groupRequest'])
            ->where('group_req_id', $groupId)
            ->first();
        // return $offer and $groupData

        return $this->SuccessResponse([$offer, $groupData], 'Group retrieved successfully for editing.');
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

        $group->status  = 'Request Cancelled';
        $group->remarks = $request->note;
        $group->save();

        return $this->SuccessResponse($group, 'Group deleted successfully.');
    }

    /**
     * Decline a group request.
     */
    public function DeclineGroup(Request $request)
    {
        $id = $request->id;

        if (! $id) {
            return $this->ErrorResponse('Group ID is required.', 'Group ID is required.');
        }

        $group = GroupRequest::find($id);

        if (!$group) {
            return $this->ErrorResponse('Group not found.', 'Group not found.');
        }

        $group->status       = 'Offer declined';
        $group->decline_note = $request->note;
        $group->save();

        return $this->SuccessResponse($group, 'Group declined successfully.');
    }
}
