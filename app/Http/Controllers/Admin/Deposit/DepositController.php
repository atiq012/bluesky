<?php

namespace App\Http\Controllers\Admin\Deposit;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DepositController extends Controller
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
        if($request->payment_type =='Cash'){

            $depo = new Deposit;
            $depo->type = $request->payment_type;
            $depo->agent_id = $request->agent_id;
            $depo->paid_account_no = $request->payment_acc;
            $depo->amount = $request->requested_amount;
            $depo->charge = $request->service_charge;
            $depo->total = $request->total_amount;
            $depo->reference_no =  $request->reference_number;
            $depo->reference_date = $request->reference_date;
            $depo->reference_file = $request->reference_file;
            $depo->remarks =$request->remarks;
            $depo->status = 'pending';
            $depo->created_by = auth()->user()->id;
            $depo->save();
        }
        else if($request->payment_type == 'MFS'){

            $depo = new Deposit;
            $depo->type = $request->payment_type;
            $depo->agent_id = $request->agent_id;
            $depo->paid_account_no = $request->payment_acc;
            $depo->amount = $request->requested_amount;
            $depo->charge = $request->service_charge;
            $depo->issued_bank = $request->issued_bank;
            $depo->total = $request->total_amount;
            // $depo->reference_no =  $request->reference_number;
            $depo->reference_date = $request->reference_date;
            $depo->reference_file = $request->reference_file;
            $depo->remarks =$request->remarks;
            $depo->status = 'pending';
            $depo->created_by = auth()->user()->id;
            $depo->save();
        }
        else if($request->payment_type == 'Cheque'){

            dd($request->all());
            $depo = new Deposit;
            $depo->type = $request->payment_type;
            $depo->agent_id = $request->agent_id;
            $depo->paid_account_no = $request->payment_acc;
            $depo->amount = $request->requested_amount;
            $depo->charge = $request->service_charge;
            $depo->total = $request->total_amount;
            $depo->issued_bank = $request->issued_bank;
            // $depo->reference_no =  $request->reference_number;
            $depo->reference_date = $request->reference_date;
            $depo->reference_file = $request->reference_file;
            $depo->remarks =$request->remarks;
            $depo->status = 'pending';
            $depo->created_by = auth()->user()->id;
            $depo->save();
        }
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
