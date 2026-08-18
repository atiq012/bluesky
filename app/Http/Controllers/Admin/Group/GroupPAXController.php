<?php
namespace App\Http\Controllers\Admin\Group;

use App\Http\Controllers\Controller;
use App\Models\Agent\Agent;
use App\Models\GroupRequest\GroupPAXInfo;
use App\Models\GroupRequest\GroupRequest;
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
        // dd($request->all());
        $agent       = Agent::where('id', Auth::user()->agent_id)->first();
        $price_offer = PriceOffer::where('group_req_id', $request['id'])->first();
        $preUpload   = GroupPAXInfo::where('group_id', $request['id'])->count();
        if (! $price_offer) {
            return response()->json(['message' => 'Price offer not found'], 404);
        }
        $total_pax = count($request->pax);

        foreach ($request->pax as $key => $value) {

            $paxTypeLabel = $this->resolvePaxType($value['dob'] ?? null, $value['pax_type'] ?? null);

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
            $groupPAXInfo->pax_type    = $paxTypeLabel;
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
                    'full_name'            => $value['title'] ?? null . ' ' . $value['first_name'] . ' ' . $value['last_name'],
                    'agent_id'             => $agent->id, // Set agent_id to null or provide a default value if needed
                    'passport_number'      => $value['passport_no'],
                    'passport_expiry_date' => $value['expiry_date'] ? date('Y-m-d', strtotime($value['expiry_date'])) : null,
                    'email'                => $value['email'] ?? null,
                    'phone'                => $value['contact'] ?? null,
                    'nationality'          => $value['nationality'] ?? null, // Add nationality field
                    'pax_type'             => $this->paxTypeLabelToCode($paxTypeLabel),
                    'gender'               => $value['gender'] ?? null,
                    'dob'                  => $value['dob'] ? date('Y-m-d', strtotime($value['dob'])) : null,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }
        }

        if ($preUpload > 0) {
            $total_pax = $preUpload + $total_pax;
        }

        if ($price_offer->total_traveler == $total_pax) {
            $price_offer->status = 'PAX Uploaded';
            $price_offer->save();
        } else if ($total_pax > $price_offer->total_traveler) {
            return response()->json(['message' => 'Total PAX exceeds the offered total traveler count'], 400);
        } else {
            $price_offer->status = 'PAX Partially Uploaded';
            $price_offer->save();
        }

        return response()->json(['message' => 'Group PAX info stored successfully', 'data' => $groupPAXInfo], 201);
    }

    /**
     * Resolve pax type (Adult/Child/Infant) from DOB. Falls back to given value if DOB missing.
     */
    private function resolvePaxType(?string $dob, ?string $fallback = null): ?string
    {
        if (! $dob) {
            return $fallback;
        }

        $age = \Carbon\Carbon::parse($dob)->age;

        if ($age < 2) {
            return 'Infant';
        } elseif ($age < 12) {
            return 'Child';
        }

        return 'Adult';
    }

    /**
     * Map pax type label to travellers table code: Adult=1, Child=2, Infant=3.
     */
    private function paxTypeLabelToCode(?string $label): ?int
    {
        return match ($label) {
            'Adult'  => 1,
            'Child'  => 2,
            'Infant' => 3,
            default  => null,
        };
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $groupPAXInfo = GroupPAXInfo::where('group_id', $id)->get();

        return response()->json(['data' => $groupPAXInfo], 200);
    }

    public function generateETicket(Request $request, string $id)
    {
        $validated = $request->validate([
            'pax_ids'   => ['nullable', 'array', 'min:1'],
            'pax_ids.*' => ['integer', 'exists:group_p_a_x_infos,id'],
        ]);

        $groupData = GroupRequest::with(['assignedGroup.segments'])->find($id);
        if (! $groupData) {
            return response()->json(['message' => 'Group not found.'], 404);
        }

        $paxQuery = GroupPAXInfo::where('group_id', $id);
        if (! empty($validated['pax_ids'])) {
            $paxQuery->whereIn('id', $validated['pax_ids']);
        }
        $paxList = $paxQuery->get();

        if ($paxList->isEmpty()) {
            return response()->json(['message' => 'No PAX found to generate e-ticket for.'], 422);
        }

        return response()->json([
            'message' => 'E-Ticket generated successfully.',
            'data'    => [
                'group' => $groupData,
                'pax'   => $paxList,
            ],
        ], 200);
    }

    public function groupPNR(Request $request, string $id)
    {
        $groupData = GroupRequest::with(['assignedGroup.segments'])->find($id);
        if (! $groupData) {
            return response()->json(['message' => 'Group not found.'], 404);
        }

        return response()->json([
            'message' => 'Group PNR retrieved successfully.',
            'data'    => [
                'pnr' => $groupData->assignedGroup->pnr,
            ],
        ], 200);
    }
}
