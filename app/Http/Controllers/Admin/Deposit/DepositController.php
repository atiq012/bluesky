<?php

namespace App\Http\Controllers\Admin\Deposit;

use App\Http\Controllers\BaseController;
use App\Jobs\BroadcastResourceEvent;
use App\Models\Agent\Agent;
use App\Models\Deposit\Deposit;
use App\Models\User;
use App\Services\HashIdService;
use App\Services\ImageService;
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
        $agent = Agent::where('user_id', Auth::id())->first();

        $data = DB::table('deposits as dpt')
            ->leftJoin('agents as agt', 'dpt.agent_id', 'agt.id')
            ->leftJoin('payment_accounts as pa', 'dpt.paid_account_no', 'pa.id')
            ->selectRaw('dpt.id as idd,dpt.type as name, dpt.paid_account_no, dpt.reference_no,dpt.reference_date,dpt.agent_id, dpt.total,dpt.status,dpt.issued_bank,dpt.remarks,dpt.updated_at,f_username(dpt.updated_by) updated_by,f_username(dpt.created_by) created_by,dpt.created_at,dpt.updated_at,agt.name as agent,pa.bank_name as bank,pa.acc_no as acct_no')
            ->where('dpt.agent_id', $agent?->id)
            ->get();

        return DataTables::of($data)
            ->addIndexColumn()
            ->editColumn('idd', fn($row) => hashid_encode(HashIdService::DEPOSIT, (int) $row->idd))
            ->make(true);
    }

    public function store(Request $request, ImageService $imageService)
    {
        $request->validate([
            'referenceFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = auth()->user();
        $agent = Agent::where('user_id', $user->id)->first();

        $referenceFilePath = null;
        if ($request->hasFile('referenceFile')) {
            $referenceFilePath = $imageService->uploadAgentImage($request->file('referenceFile'), 'referenceFile');
        }

        try {
            if ($request->payment_type == 'Cash') {

                $depo                  = new Deposit;
                $depo->type            = $request->payment_type;
                $depo->agent_id        = $agent->id;
                $depo->paid_account_no = $request->payment_acc;
                $depo->amount          = $request->requested_amount;
                $depo->charge          = $request->service_charge;
                $depo->total           = $request->total_amount;
                $depo->reference_no    = $request->reference_number;
                $depo->reference_date  = date('Y-m-d', strtotime($request->reference_date));
                $depo->reference_file  = $referenceFilePath;
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
                $depo->reference_date = date('Y-m-d', strtotime($request->reference_date));
                $depo->reference_file = $referenceFilePath;
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
                $depo->reference_date = date('Y-m-d', strtotime($request->reference_date));
                $depo->reference_file = $referenceFilePath;
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
                $depo->reference_date = date('Y-m-d', strtotime($request->reference_date));
                $depo->reference_file = $referenceFilePath;
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
                $depo->reference_date = date('Y-m-d', strtotime($request->reference_date));
                $depo->reference_file = $referenceFilePath;
                $depo->remarks        = $request->remarks;
                $depo->status         = 'Requested';
                $depo->created_by     = auth()->user()->id;
                $depo->save();
            }
        } catch (\Throwable $e) {
            if ($referenceFilePath) {
                $imageService->deleteByDbPath($referenceFilePath);
            }

            throw $e;
        }

        BroadcastResourceEvent::dispatch('deposits', 'Created', [
            'id'       => $depo->id,
            'actor_id' => $user->id,
        ]);

        return $this->SuccessResponse('', 'Successfully Deposit Saved.');
    }

    public function show(string $id)
    {
        $depositId = hashid_decode(HashIdService::DEPOSIT, $id);
        if (! $depositId) {
            return $this->ErrorResponse('Deposit not found.', [], 404);
        }

        $agent = Agent::where('user_id', Auth::id())->first();
        if (! $agent) {
            return $this->ErrorResponse('Agent account not found.', [], 404);
        }

        $row = DB::table('deposits as d')
            ->join('agents as ag', 'd.agent_id', 'ag.id')
            ->leftJoin('payment_accounts as pa', 'd.paid_account_no', 'pa.id')
            ->leftJoin('issued_bank_m_f_s as ib', 'd.issued_bank', 'ib.id')
            ->where('d.id', $depositId)
            ->where('d.agent_id', $agent->id)
            ->selectRaw('
                d.id,
                d.type,
                d.amount,
                d.charge,
                d.total,
                d.reference_no,
                d.reference_date,
                d.reference_file,
                d.remarks,
                d.status,
                d.created_at,
                ag.name as agent_name,
                ag.agent_code,
                ag.logo_path,
                ag.net_balance,
                ag.credit_balance,
                ag.iata_number,
                pa.bank_name as payment_bank,
                pa.acc_no as payment_acc_no,
                pa.branch as payment_branch,
                ib.name as issued_bank_name,
                f_username(d.created_by) as requested_by
            ')
            ->first();

        if (! $row) {
            return $this->ErrorResponse('Deposit not found.', [], 404);
        }

        return $this->SuccessResponse([
            'id' => hashid_encode(HashIdService::DEPOSIT, (int) $row->id),
            'type' => $row->type,
            'amount' => $row->amount,
            'charge' => $row->charge,
            'total' => $row->total,
            'reference_no' => $row->reference_no,
            'reference_date' => $row->reference_date,
            'reference_file' => $this->normalizeUploadPath($row->reference_file),
            'remarks' => $row->remarks,
            'status' => $row->status,
            'created_at' => $row->created_at,
            'agent_name' => $row->agent_name,
            'agent_code' => $row->agent_code,
            'logo_path' => $this->normalizeUploadPath($row->logo_path),
            'net_balance' => $row->net_balance,
            'credit_balance' => $row->credit_balance,
            'iata_number' => $row->iata_number,
            'payment_bank' => $row->payment_bank,
            'payment_acc_no' => $row->payment_acc_no,
            'payment_branch' => $row->payment_branch,
            'issued_bank_name' => $row->issued_bank_name,
            'requested_by' => $row->requested_by,
        ], 'Deposit loaded.');
    }

    private function normalizeUploadPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $path = preg_replace('#^public/#', '', $path);
        if (! str_starts_with($path, '/')) {
            $path = '/' . ltrim($path, '/');
        }

        return $path;
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
        $id = hashid_decode(HashIdService::DEPOSIT, (string) $request->id);
        if (!$id) {
            return $this->ErrorResponse('Deposit not found.', [], 404);
        }

        $depo = Deposit::find($id);
        if (!$depo) {
            return $this->ErrorResponse('Deposit not found.', [], 404);
        }

        $depo->delete();

        return $this->SuccessResponse('', 'Successfully Deposit Deleted.');
    }

    public function cancel(Request $request)
    {
        $id = hashid_decode(HashIdService::DEPOSIT, (string) $request->id);
        if (!$id) {
            return $this->ErrorResponse('Deposit not found.', [], 404);
        }

        $depo = Deposit::find($id);
        if (!$depo) {
            return $this->ErrorResponse('Deposit not found.', [], 404);
        }

        $depo->status = 'Cancelled';
        $depo->updated_by = auth()->user()->id;
        $depo->save();

        return $this->SuccessResponse('', 'Deposit request cancelled successfully.');
    }
}
