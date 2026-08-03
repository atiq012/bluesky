<?php
namespace App\Http\Controllers\Admin\Group;

use App\Http\Controllers\Controller;
use App\Models\Agent\Agent;
use App\Models\GroupRequest\GroupPAXInfo;
use App\Models\GroupRequest\PriceOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GroupPAXController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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

        $agent = Agent::where('id', Auth::user()->agent_id)->first();
        $price_offer = PriceOffer::where('group_req_id', $request['id'])->first();
        $preUpload = GroupPAXInfo::where('group_id', $request['id'])->count();
        if(!$price_offer){
            return response()->json(['message' => 'Price offer not found'], 404);
        }
        $total_pax = count($request->pax);

        foreach ($request->pax as $key => $value) {

            // Create a new GroupPAXInfo record
            $groupPAXInfo              = new GroupPAXInfo;
            $groupPAXInfo->group_id    = $request['id'];
            $groupPAXInfo->title       = $value['title'] ?? null;
            $groupPAXInfo->first_name  = $value['first_name'];
            $groupPAXInfo->last_name   = $value['last_name'];
            $groupPAXInfo->passport_no = $value['passport_no'];
            // If you need to format the date
            $groupPAXInfo->expiry_date = isset($value['expiry_date'])
                ? date('Y-m-d', strtotime($value['expiry_date']))
                : null;
            $groupPAXInfo->email       = $value['email'] ?? null;
            $groupPAXInfo->phone       = $value['contact'] ?? null;
            $groupPAXInfo->nationality = $value['nationality'] ?? null;
            $groupPAXInfo->pax_type    = $value['pax_type'] ?? null;
            $groupPAXInfo->gender      = $value['gender'] ?? null;
            $groupPAXInfo->dob         = $value['dob'] ? date('Y-m-d', strtotime($value['dob'])) : null;
            $groupPAXInfo->created_by  = auth()->id(); // Assuming you have authentication set up
            $groupPAXInfo->save();

            // in traveller table check the passport number is already exist or not if not then insert the data in traveller table
            $existingTraveller = DB::table('travellers')->where('passport_number', $value['passport_no'])->first();
            if (! $existingTraveller) {
                DB::table('travellers')->insert([
                    'title'                => $value['title'] ?? null,
                    'first_name'           => $value['first_name'],
                    'last_name'            => $value['last_name'],
                    'full_name'            => $value['title'] ?? null.' '.$value['first_name'].' '.$value['last_name'],
                    'agent_id'             => $agent->id, // Set agent_id to null or provide a default value if needed
                    'passport_number'      => $value['passport_no'],
                    'passport_expiry_date' => $value['expiry_date'] ? date('Y-m-d', strtotime($value['expiry_date'])) : null,
                    'email'                => $value['email'] ?? null,
                    'phone'                => $value['phone'] ?? null,
                    'nationality'          => $value['nationality'] ?? null, // Add nationality field
                    'pax_type'             => $value['pax_type'] ?? null,
                    'gender'               => $value['gender'] ?? null,
                    'dob'                  => $value['dob'] ? date('Y-m-d', strtotime($value['dob'])) : null,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }
        }



        if($preUpload > 0){
            $total_pax = $preUpload + $total_pax;
        }

        if($price_offer->total_traveler == $total_pax){
            $price_offer->status = 'PAX Uploaded';
            $price_offer->save();
        }else if($total_pax > $price_offer->total_traveler){
            return response()->json(['message' => 'Total PAX exceeds the offered total traveler count'], 400);
        }else{
            $price_offer->status = 'PAX Partially Uploaded';
            $price_offer->save();
        }

        return response()->json(['message' => 'Group PAX info stored successfully', 'data' => $groupPAXInfo], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $groupPAXInfo = GroupPAXInfo::where('group_id', $id)->get();

        return response()->json(['data' => $groupPAXInfo], 200);
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
