<?php
namespace App\Http\Controllers\Admin\PaymentAccount;

use App\Http\Controllers\BaseController;
use App\Models\PaymentAccount\PaymentAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class PaymentAccountSController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = DB::table('payment_accounts as pa')
            ->selectRaw('pa.id as idd,pa.acc_type,pa.bank_name,pa.acc_name,pa.acc_no,pa.branch,
            pa.routing_no,pa.service_charge,pa.created_at,pa.status,pa.updated_at,f_username(pa.updated_by) updated_by,f_username(pa.created_by) created_by')->get();
        // dd($data);
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
        $auth = Auth::user();

        $validator = validator($request->all(),
            ['acc_type' => 'required'],
            ['bank_name' => 'required'],
            ['acc_name' => 'required'],
            ['acc_no' => 'required'],
            ['branch' => 'required'],
            // ['routing_no' => 'required'],
            ['service_charge' => 'required'],
        );
        if ($validator->fails()) {
            return $this->ErrorResponse($validator->errors()->all());
        }

        $pay_acc                 = new PaymentAccount;
        $pay_acc->acc_type       = $request->acc_type;
        $pay_acc->bank_name      = $request->bank_name;
        $pay_acc->acc_name       = $request->acc_name;
        $pay_acc->acc_no         = $request->acc_no;
        $pay_acc->branch         = $request->branch;
        $pay_acc->routing_no     = $request->routing_no;
        $pay_acc->service_charge = $request->service_charge;
        $pay_acc->status         = 1;
        $pay_acc->created_by     = $auth->id;
        $pay_acc->save();

        $success = '';

        return $this->SuccessResponse($success, 'Successfully Payment Account Saved.');
    }

    public function changePaymentAcctStatus(Request $request)
    {
        if ($request->id) {

            $pay_acc = PaymentAccount::where('id', $request->id)->first();
            if ($pay_acc->status == 1) {

                $pay_acc->status = 2;
            } else if ($pay_acc->status == 2) {
                $pay_acc->status = 1;
            }
            $pay_acc->save();
            $success = '';
            return $this->SuccessResponse($success, 'Successfully Payment Account status updated.');

        } else {
            $error = 'Id can not be null.';
            return $this->ErrorResponse($error);

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
    public function edit(Request $request)
    {
        $pay_acc = PaymentAccount::find($request->id);
        return response()->json($pay_acc);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {

        $auth = Auth::user();

        $pay_acc = PaymentAccount::where('id', $request->pay_acct_id)->first();

        $pay_acc->acc_type       = $request->acc_type ? $request->acc_type : $pay_acc->acc_type;
        $pay_acc->bank_name      = $request->bank_name ? $request->bank_name : $pay_acc->bank_name;
        $pay_acc->acc_name       = $request->acc_name ? $request->acc_name : $pay_acc->acc_name;
        $pay_acc->acc_no         = $request->acc_no ? $request->acc_no : $pay_acc->acc_no;
        $pay_acc->branch         = $request->branch ? $request->branch : $pay_acc->branch;
        $pay_acc->routing_no     = $request->routing_no ? $request->routing_no : $pay_acc->routing_no;
        $pay_acc->service_charge = $request->service_charge ? $request->service_charge : $pay_acc->service_charge;

        $pay_acc->status     = $request->status_val != null ? $request->status_val : $pay_acc->status;
        $pay_acc->updated_by = $auth->id;
        $pay_acc->save();

        $success = '';
        return $this->SuccessResponse($success, 'Successfully Payment Account Updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        if ($request->id) {

            $pay_acc = PaymentAccount::where('id', $request->id)->first();
            $pay_acc->delete();
            $success = '';
            return $this->SuccessResponse($success, 'Successfully Department deleted.');

        } else {
            $error = 'Id can not be null.';
            return $this->ErrorResponse($error);

        }
    }

    public function getAllPaymentAccount()
    {
        $data = DB::table('payment_accounts as pa')
            ->where('status', 1)
            ->select('pa.id', 'pa.bank_name as name', 'pa.acc_no', 'pa.branch')
            ->get();

        return response()->json($data);
    }
}
