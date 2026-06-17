<?php
namespace App\Http\Controllers\Admin\Deposit;

use App\Http\Controllers\BaseController;
use App\Models\Agent\Agent;
use App\Models\Deposit\Deposit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class DepositController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = DB::table('deposits as dpt')
            ->leftJoin('agents as agt', 'dpt.agent_id', 'agt.id')
            ->leftJoin('payment_accounts as pa', 'dpt.paid_account_no', 'pa.id')
            ->selectRaw('dpt.id as idd,dpt.type as name, dpt.paid_account_no, dpt.reference_no,dpt.reference_date,dpt.agent_id, dpt.total,dpt.status,dpt.issued_bank,dpt.remarks,dpt.updated_at,f_username(dpt.updated_by) updated_by,f_username(dpt.created_by) created_by,dpt.created_at,dpt.updated_at,agt.name as agent,pa.bank_name as bank,pa.acc_no as acct_no')->get();

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
        // Get the authenticated user
        $user = auth()->user();

        $agent = Agent::where('user_id', $user->id)->first();

        if ($request->payment_type == 'Cash') {

            $depo                  = new Deposit;
            $depo->type            = $request->payment_type;
            $depo->agent_id        = $agent->id;
            $depo->paid_account_no = $request->payment_acc;
            $depo->amount          = $request->requested_amount;
            $depo->charge          = $request->service_charge;
            $depo->total           = $request->total_amount;
            $depo->reference_no    = $request->reference_number;
            $depo->reference_date  = $request->reference_date;
            $depo->reference_file  = $request->reference_file;
            $depo->remarks         = $request->remarks;
            $depo->status          = 'Requested';
            $depo->created_by      = auth()->user()->id;
            $depo->save();
        } else if ($request->payment_type == 'MFS') {

            $depo                  = new Deposit;
            $depo->type            = $request->payment_type;
            $depo->agent_id        = $agent->id;
            $depo->paid_account_no = $request->payment_acc;
            $depo->amount          = $request->requested_amount;
            $depo->charge          = $request->service_charge;
            $depo->issued_bank     = $request->issued_bank;
            $depo->total           = $request->total_amount;
            // $depo->reference_no =  $request->reference_number;
            $depo->reference_date = $request->reference_date;
            $depo->reference_file = $request->reference_file;
            $depo->remarks        = $request->remarks;
            $depo->status         = 'Requested';
            $depo->created_by     = auth()->user()->id;
            $depo->save();
        } else if ($request->payment_type == 'Cheque') {

            $depo                  = new Deposit;
            $depo->type            = $request->payment_type;
            $depo->agent_id        = $agent->id;
            $depo->paid_account_no = $request->payment_acc;
            $depo->amount          = $request->requested_amount;
            $depo->charge          = $request->service_charge;
            $depo->total           = $request->total_amount;
            $depo->issued_bank     = $request->issued_bank;
            // $depo->reference_no =  $request->reference_number;
            $depo->reference_date = $request->reference_date;
            $depo->reference_file = $request->reference_file;
            $depo->remarks        = $request->remarks;
            $depo->status         = 'Requested';
            $depo->created_by     = auth()->user()->id;
            $depo->save();
        } else if ($request->payment_type == 'Bank_Transfer') {

            $depo                  = new Deposit;
            $depo->type            = 'Bank Transfer';
            $depo->agent_id        = $agent->id;
            $depo->paid_account_no = $request->payment_acc;
            $depo->amount          = $request->requested_amount;
            $depo->charge          = $request->service_charge;
            $depo->total           = $request->total_amount;
            $depo->issued_bank     = $request->issued_bank;
            // $depo->reference_no =  $request->reference_number;
            $depo->reference_date = $request->reference_date;
            $depo->reference_file = $request->reference_file;
            $depo->remarks        = $request->remarks;
            $depo->status         = 'Requested';
            $depo->created_by     = auth()->user()->id;
            $depo->save();
        } else if ($request->payment_type == 'Credit_Request') {

            $depo                  = new Deposit;
            $depo->type            = 'Credit Request';
            $depo->agent_id        = $agent->id;
            $depo->paid_account_no = $request->payment_acc;
            $depo->amount          = $request->requested_amount;
            $depo->charge          = $request->service_charge;
            $depo->total           = $request->total_amount;
            // $depo->reference_no =  $request->reference_number;
            $depo->reference_date = $request->reference_date;
            $depo->reference_file = $request->reference_file;
            $depo->remarks        = $request->remarks;
            $depo->status         = 'Requested';
            $depo->created_by     = auth()->user()->id;
            $depo->save();
        }
        // Prepare the success response
        $success = '';

        return $this->SuccessResponse($success, 'Successfully Deposit Saved.');

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
    public function destroy(Request $request)
    {
        $depo = Deposit::find($request->id);
        $depo->delete();

        return $this->SuccessResponse('', 'Successfully Deposit Deleted.');
    }
}
