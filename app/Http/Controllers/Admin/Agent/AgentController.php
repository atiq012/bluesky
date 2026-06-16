<?php

namespace App\Http\Controllers\Admin\Agent;

use App\Http\Controllers\BaseController;
use App\Models\Agent\Agent;
use App\Models\Agent\AgentApprovalLog;
use App\Models\Agent\AgentImage;
use App\Models\Agent\AgentUser;
use App\Models\User;
use App\Services\ImageService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Yajra\DataTables\DataTables;

class AgentController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = DB::table('agents as ag')
            ->join('users as u', 'ag.user_id', 'u.id')
            ->join('agent_users as au', 'ag.id', 'au.agent_id')
            ->selectRaw('ag.id as idd,u.name as owner,ag.name,ag.phone,ag.agent_code as agent_code,ag.email as email,ag.created_at,ag.status,ag.updated_at,f_username(ag.updated_by) updated_by,f_username(ag.created_by) created_by,au.designation,ag.country,ag.city,ag.address,f_zonename(ag.zone) as zone,ag.trade_licence,ag.ca_number,ag.established_date,ag.reg_number,ag.postal_code,ag.fax,ag.iata_number,ag.hajj_agency_number,f_username(ag.kam) as kam,ag.remarks,ag.net_balance')->get();

        return DataTables::of($data)->addIndexColumn()->make(true);
    }

    public function getKam()
    {
        $kams = DB::table('users')->where('type', 1)->get();
        return response()->json($kams);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function viewAgent(Request $request)
    {
        $data = DB::table('agents as ag')
            ->join('agent_users as au', 'ag.id', 'au.agent_id')
            ->join('agent_approval_logs as aal', 'ag.id', 'aal.agent_id')
            ->join('districts as dis', 'dis.id', 'ag.city')
            ->where('ag.id', $request->id)
            ->selectRaw('ag.id as idd,au.name as owner,au.designation as designation,au.nid as owner_nid,au.dob as dob,au.email as owner_email,au.phone as owner_phone,ag.name as name,ag.phone as phone,ag.agent_code as agent_code,ag.email as email,ag.created_at,ag.status,ag.updated_at,f_username(ag.updated_by) updated_by,f_username(ag.created_by) created_by,ag.country,ag.address,f_zonename(ag.zone) as zone,ag.trade_licence,ag.ca_number,ag.established_date,ag.reg_number,ag.postal_code,ag.fax,ag.iata_number,ag.hajj_agency_number,f_username(ag.kam) as kam,ag.remarks,ag.net_balance,ag.remarks as remarks,ag.logo_path as logo_path,dis.name as city')->get();
        return response()->json($data);
    }
    public function recommendedAgentDetails(Request $request)
    {
        $data = DB::table('agents as ag')
            ->join('agent_users as au', 'ag.id', 'au.agent_id')
            ->join('districts as dis', 'dis.id', 'ag.city')
            ->where('ag.id', $request->id)
            ->selectRaw('ag.id as idd,au.name as owner,au.designation as designation,au.nid as owner_nid,au.dob as dob,au.email as owner_email,au.phone as owner_phone,ag.name as name,ag.phone as phone,ag.agent_code as agent_code,ag.email as email,ag.created_at,ag.status,ag.updated_at,f_username(ag.updated_by) updated_by,f_username(ag.created_by) created_by,ag.country,ag.address,f_zonename(ag.zone) as zone,ag.trade_licence,ag.ca_number,ag.established_date,ag.reg_number,ag.postal_code,ag.fax,ag.iata_number,ag.hajj_agency_number,f_username(ag.kam) as kam,ag.remarks,ag.net_balance,ag.remarks as remarks,ag.logo_path as logo_path,dis.name as city')->get();
        return response()->json($data);
    }

    public function agentRecomendation(Request $request)
    {

        $agent         = Agent::where('id', $request->agent_id)->first();
        $agent->status = $request->status;
        $agent->save();

        $agent_approver_log              = new AgentApprovalLog;
        $agent_approver_log->agent_id    = $request->agent_id;
        $agent_approver_log->status      = $request->status;
        $agent_approver_log->remarks     = $request->note;
        $agent_approver_log->approver_id = $request->approver;
        $agent_approver_log->created_by  = auth()->user()->id;
        $agent_approver_log->save();
        $success = '';

        return $this->SuccessResponse($success, 'Successfully Agent Saved.');
    }

    public function agentApproval(Request $request)
    {
        // dd($request->all());
        $agent         = Agent::where('id', $request->agent_id)->first();
        $agent->status = $request->status;
        $agent->save();

        $agent_approver_log              = new AgentApprovalLog;
        $agent_approver_log->agent_id    = $request->agent_id;
        $agent_approver_log->status      = $request->status;
        $agent_approver_log->remarks     = $request->note;
        $agent_approver_log->approver_id = auth()->user()->id;
        $agent_approver_log->created_by  = auth()->user()->id;
        $agent_approver_log->save();
        $success = '';

        return $this->SuccessResponse($success, 'Successfully Agent Saved.');
    }

    public function AgentAllImage(Request $request)
    {
        $data = AgentImage::where('agent_id', $request->id)->get();
        return response()->json($data);
    }
    public function getAgentApprovalLog(Request $request)
    {
        $data = DB::table('agent_approval_logs as aal')->where('agent_id', $request->id)
            ->selectRaw('aal.remarks as remarks,aal.status as status,f_username(aal.approver_id) as approver_name')
            ->get();
        return response()->json($data);
    }
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $agent                     = new Agent;
        $agent->name               = $request->name;
        $agent->agent_code         = $request->agent_code;
        $agent->email              = $request->email;
        $agent->phone              = $request->phone;
        $agent->country            = $request->country;
        $agent->city               = $request->city;
        $agent->zone               = $request->zone;
        $agent->address            = $request->address;
        $agent->established_date   = $request->established_date;
        $agent->postal_code        = $request->postal_code;
        $agent->ca_number          = $request->ca_number;
        $agent->iata_number        = $request->iata_number;
        $agent->reg_number         = $request->reg_number;
        $agent->fax                = $request->fax;
        $agent->trade_licence      = $request->trade_licence;
        $agent->hajj_agency_number = $request->hajj_no;
        $agent->kam                = $request->kam_id;
        $agent->remarks            = $request->remarks;
        $agent->status             = 'Pending';
        $agent->account_ledger_id  = 1;
        // $agent->designation        = $request->designation;
        $agent->created_by = auth()->user()->id;

        if (($request->hasFile('agency_img'))) {

            $request_image = $request->file('agency_img');
            $image_name    = str_replace(' ', '', (now()->format('dmY-') . time())) . '.' . $request_image->extension();

            $image_path = public_path('/uploads/agents/agency_img/');
            if (! File::exists($image_path)) {
                File::makeDirectory($image_path, 0777, true);
            }

            $request_image->move($image_path, $image_name);

            $agent->logo_path = '/uploads/agents/agency_img/' . $image_name;
        }

        $agent->save();

        $agent_user              = new AgentUser;
        $agent_user->name        = $request->ownername;
        $agent_user->nid         = $request->nid_number;
        $agent_user->email       = $request->email_address;
        $agent_user->designation = $request->designation;
        $agent_user->dob         = $request->dob;
        $agent_user->phone       = $request->owner_phone;
        $agent_user->agent_id    = $agent->id;
        $agent_user->created_by  = auth()->user()->id;

        if ($request->hasFile('owner_pro_img')) {
            $request_image = $request->file('owner_pro_img');
            $image_name    = str_replace(' ', '', (now()->format('dmY-') . time())) . '.' . $request_image->extension();

            $image_path = public_path('/uploads/agents/agent_owner/');
            if (! File::exists($image_path)) {
                File::makeDirectory($image_path, 0777, true);
            }

            $request_image->move($image_path, $image_name);
            $agent_user->img_path = '/uploads/agents/agent_owner/' . $image_name;
        }

        $agent_user->save();
        $agent->user_id = $agent_user->id;
        $agent->save();

        if (($request->hasFile('nid_img'))) {
            $agent_img                  = new AgentImage;
            $agent_img->agent_id        = $agent->id;
            $agent_img->attachment_type = 'nid_img';

            $request_image = $request->file('nid_img');
            // dd($request_image);
            $image_name = str_replace(' ', '', (now()->format('dmY-') . time())) . '.' . $request_image->extension();

            $image_path = public_path('/uploads/agents/nid_img/');
            if (! File::exists($image_path)) {
                File::makeDirectory($image_path, 0777, true);
            }

            $request_image->move($image_path, $image_name);
            $agent_img->attachment_path = '/uploads/agents/nid_img/' . $image_name;
            $agent_img->save();
        }

        if (($request->hasFile('trade_licence_img'))) {
            $agent_img                  = new AgentImage;
            $agent_img->agent_id        = $agent->id;
            $agent_img->attachment_type = 'trade_licence_img';

            $request_image = $request->file('trade_licence_img');
            $image_name    = str_replace(' ', '', (now()->format('dmY-') . time())) . '.' . $request_image->extension();
            $image_path    = public_path('/uploads/agents/trade_licence_img/');
            if (! File::exists($image_path)) {
                File::makeDirectory($image_path, 0777, true);
            }

            $request_image->move($image_path, $image_name);
            $agent_img->attachment_path = '/uploads/agents/trade_licence_img/' . $image_name;
            $agent_img->save();
        }

        if (($request->hasFile('ca_img'))) {
            $agent_img                  = new AgentImage;
            $agent_img->agent_id        = $agent->id;
            $agent_img->attachment_type = 'ca_img';

            $request_image = $request->file('ca_img');
            $image_name    = str_replace(' ', '', (now()->format('dmY-') . time())) . '.' . $request_image->extension();

            $image_path = public_path('/uploads/agents/ca_img/');
            if (! File::exists($image_path)) {
                File::makeDirectory($image_path, 0777, true);
            }

            $request_image->move($image_path, $image_name);
            $agent_img->attachment_path = '/uploads/agents/ca_img/' . $image_name;
            $agent_img->save();
        }

        if (($request->hasFile('iata_img'))) {
            $agent_img                  = new AgentImage;
            $agent_img->agent_id        = $agent->id;
            $agent_img->attachment_type = 'iata_img';

            $request_image = $request->file('iata_img');
            $image_name    = str_replace(' ', '', (now()->format('dmY-') . time())) . '.' . $request_image->extension();

            $image_path = public_path('/uploads/agents/iata_img/');
            if (! File::exists($image_path)) {
                File::makeDirectory($image_path, 0777, true);
            }

            $request_image->move($image_path, $image_name);
            $agent_img->attachment_path = '/uploads/agents/iata_img/' . $image_name;
            $agent_img->save();
        }

        if (($request->hasFile('hajj_licence_img'))) {
            $agent_img                  = new AgentImage;
            $agent_img->agent_id        = $agent->id;
            $agent_img->attachment_type = 'hajj_licence_img';

            $request_image = $request->file('hajj_licence_img');
            $image_name    = str_replace(' ', '', (now()->format('dmY-') . time())) . '.' . $request_image->extension();

            $image_path = public_path('/uploads/agents/hajj_licence_img/');
            if (! File::exists($image_path)) {
                File::makeDirectory($image_path, 0777, true);
            }

            $request_image->move($image_path, $image_name);
            $agent_img->attachment_path = '/uploads/agents/hajj_licence_img/' . $image_name;
            $agent_img->save();
        }

        if (($request->hasFile('tin_img'))) {
            $agent_img                  = new AgentImage;
            $agent_img->agent_id        = $agent->id;
            $agent_img->attachment_type = 'tin_img';

            $request_image = $request->file('tin_img');
            $image_name    = str_replace(' ', '', (now()->format('dmY-') . time())) . '.' . $request_image->extension();

            $image_path = public_path('/uploads/agents/tin_img/');
            if (! File::exists($image_path)) {
                File::makeDirectory($image_path, 0777, true);
            }

            $request_image->move($image_path, $image_name);
            $agent_img->attachment_path = '/uploads/agents/tin_img/' . $image_name;
            $agent_img->save();
        }

        $success = '';

        return $this->SuccessResponse($success, 'Successfully Agent Saved.');
    }

    public function registration(Request $request)
    {
        // dd($request->all());

        $nullIfEmpty = static function ($value) {
            if (is_string($value)) {
                $value = trim($value);
            }
            return $value === '' ? null : $value;
        };

        $agent = new Agent;

        $agent->name             = $request->agencyName;        // done
        $agent->agent_code       = "BS-" . mt_rand(1000, 9999); // done
        $agent->email            = $request->agencyEmail;       // done
        $agent->phone            = $request->agencyPhone;       // done
        $agent->country          = $request->country;           // done
        $agent->city             = $request->city;              // done
        // $agent->zone             = 1;
        $agent->address          = $request->address;         // done
        $agent->established_date = null;
        if (!empty($request->establishedDate)) {
            try {
                $agent->established_date = Carbon::parse($request->establishedDate)->format('Y-m-d');
            } catch (\Throwable $e) {
                // keep null if parsing fails; validation layer can enforce format if needed
                $agent->established_date = null;
            }
        }
        $agent->postal_code      = $nullIfEmpty($request->postalCode);   // done
        $agent->ca_number        = $nullIfEmpty($request->cacNumber);    // done
        $agent->iata_number      = $nullIfEmpty($request->iataNumber);   // done
        // $agent->reg_number         = $request->reg_number;
        // $agent->fax                = $request->fax;
        $agent->trade_licence      = $nullIfEmpty($request->tradeLicense); // done
        $agent->hajj_agency_number = $nullIfEmpty($request->hajjNumber);   // done
        // $agent->kam                = $request->kam_id;
        // $agent->remarks            = $request->remarks;
        $agent->status = 'Pending';
        $agent->account_ledger_id  = 1;
        // $agent->designation        = $request->designation;
        $agent->created_by = 1;

        /** @var ImageService $imageService */
        $imageService = app(ImageService::class);
        if ($request->hasFile('logo')) {
            $agent->logo_path = $imageService->uploadAgentImage($request->file('logo'), 'logo');
        }

        $agent->save();

        $agent_user              = new AgentUser;
        $agent_user->name        = $request->firstName;
        $agent_user->nid         = $request->nidNumber;
        $agent_user->email       = $request->email;
        $agent_user->designation = $request->designation;
        $agent_user->dob         = $request->birthDate;
        $agent_user->phone       = $request->userPhone;
        $agent_user->agent_id    = $agent->id;
        $agent_user->created_by  = 1;

        $agent_user->save();

        // $agent->user_id = $agent_user->id;
        // $agent->save();

        $fileFields = ['nidFiles', 'tradeFiles', 'cacFiles', 'iataFiles', 'hajjFiles', 'tinFiles'];
        foreach ($fileFields as $field) {
            if (! $request->hasFile($field)) {
                continue;
            }

            $requestFiles = $request->file($field);
            $files = is_array($requestFiles) ? $requestFiles : [$requestFiles];

            foreach ($files as $singleImage) {
                if (! $singleImage) {
                    continue;
                }

                $agentImg = new AgentImage;
                $agentImg->agent_id = $agent->id;
                $agentImg->attachment_type = $imageService->resolveAttachmentTypeByField($field);
                $agentImg->attachment_path = $imageService->uploadAgentImage($singleImage, $field);
                $agentImg->save();
            }
        }

        $success = '';

        return $this->SuccessResponse($success, 'Successfully Agent Saved.');
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
