<?php
namespace App\Http\Controllers\Admin\Group;

use App\Http\Controllers\BaseController;
use App\Models\Agent\Agent;
use App\Models\GroupPNR\Group_pnr;
use App\Models\GroupRequest\GroupPayment;
use App\Models\GroupRequest\GroupRequest;
use App\Models\GroupRequest\OfferPricePaymentTerm;
use App\Models\GroupRequest\PriceOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\DataTables;

class GroupPaymentController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // dd(auth()->user()->agent_id);
        $data = DB::table('group_payments as gp')
            ->join('group_requests as gr', 'gr.id', '=', 'gp.group_req_id')
            ->leftJoin('agents as a', 'a.id', '=', 'gp.agent_id')
            ->leftJoin('price_offers as po', 'po.group_req_id', '=', 'gp.group_req_id')
            ->leftJoin('offer_price_payment_terms as opt', 'opt.id', '=', 'gp.payment_term_id')
            ->leftJoin('users as u', 'u.id', '=', DB::raw('CAST(gp.created_by AS UNSIGNED)'))
            ->select(
                'gp.id',
                'gp.group_req_id',
                'gp.paid_amount',
                'gp.status',
                'gp.void_note',
                'gp.voided_by',
                'gp.voided_at',
                'gp.payment_date',
                'gp.created_at',
                'gr.group_code',
                'gr.created_at as group_created_at',
                'a.id as agent_id',
                'a.name as agent_name',
                'a.agent_code',
                'a.email as agent_email',
                'a.phone as agent_phone',
                'po.currency',
                'po.pnr',
                'po.adult_traveler',
                'po.adult_base_fare',
                'po.adult_tax',
                'po.adult_ait',
                'po.child_traveler',
                'po.child_base_fare',
                'po.child_tax',
                'po.child_ait',
                'po.est_total_fare',
                'po.estimate_net_payable',
                'opt.sequence as payment_sequence',
                'u.name as transacted_by',
                DB::raw("CONCAT('GPAY', LPAD(gp.id, 7, '0')) as trn_id"),
                DB::raw('(SELECT COALESCE(SUM(paid_amount), 0)
                    FROM group_payments
                    WHERE group_req_id = gp.group_req_id
                      AND status = "Success"
                      AND id <= gp.id
                ) as paid_till_here'),
            )
            ->where('gp.agent_id', auth()->user()->agent_id)
            ->orderBy('gp.id', 'desc');

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('due_amount', function ($row) {
                return max(0, ($row->est_total_fare ?? 0) - ($row->paid_till_here ?? 0));
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

        $validator = Validator::make($request->all(), [
            'group_id'         => ['required', 'integer', 'exists:group_requests,id'],
            'payment_term_id'  => ['nullable', 'integer'],
            'share_pnr'        => ['nullable', 'boolean'],
            'remainingBalance' => ['nullable', 'numeric', 'min:0'],
            'dueAmount'        => ['nullable', 'numeric', 'min:0'],
            'netPayable'       => ['required', 'numeric', 'min:0'],
            'paidAmount'       => ['required', 'numeric', 'min:0'],
        ]);

        if($request->remainingBalance < 0){
            return $this->ErrorResponse('Innsufficient balance.Please Deposit.');
        }
        if ($validator->fails()) {
            return $this->ErrorResponse('Validation failed', $validator->errors(), 422);
        }

        $validated = $validator->validated();
        // dd($request->all());
        $offerPricePaymentTerm = OfferPricePaymentTerm::find($validated['payment_term_id']);
        if($offerPricePaymentTerm->value == 100){
            if($offerPricePaymentTerm->amount > $validated['paidAmount']){
            return $this->ErrorResponse('Paid Amount should be less than or equal to 100% of Net Payable.');

            }
        }
        $reqGroup = GroupRequest::find($validated['group_id']);
        if (empty($reqGroup->agent_code)) {
            return $this->ErrorResponse('Agent code not found', 'Agent code not found');
        }

        $agent      = Agent::where('agent_code', $reqGroup->agent_code)->first();
        $priceOffer = PriceOffer::where('group_req_id', $validated['group_id'])->first();

        $gr = GroupRequest::find($priceOffer->group_req_id);
        if ($validated['share_pnr'] == true) {

            if ($validated['dueAmount'] == 0) {
                $priceOffer->status = 'Paid';
            } else {
                $priceOffer->status = 'Partial Paid';
            }
            $priceOffer->save();

            // $gr->status = 'PNR Shared';
            // $gr->save();

            $grPNR           = Group_pnr::find($gr->group_pnr_id);
            $priceOffer->pnr = $grPNR->pnr;
            $priceOffer->save();
        } else {
            if ($validated['dueAmount'] == 0) {
                $priceOffer->status = 'Paid';
            } else {
                $priceOffer->status = 'Partial Paid';
            }
            $priceOffer->save();
        }
        $payment = GroupPayment::create([
            'group_req_id'    => $validated['group_id'],
            'payment_term_id' => $validated['payment_term_id'] ?? null,
            'agent_id'        => $agent->id,
            'paid_amount'     => $validated['paidAmount'],
            'payment_date'    => now()->toDateString(),
            'created_by'      => auth()->id(),
        ]);

        $off_pay_term         = OfferPricePaymentTerm::where('offer_price_id', $priceOffer->id)->where('id', $validated['payment_term_id'])->first();
        $off_pay_term->status = 'paid';
        $off_pay_term->save();

        $agent->net_balance = $agent->net_balance - $payment->paid_amount;
        $agent->save();
        return $this->SuccessResponse($payment, 'Group payment saved successfully');
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
     * Void a payment. Reverses the agent balance deduction made at payment time.
     */
    public function voidPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'   => ['required', 'integer', 'exists:group_payments,id'],
            'note' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->ErrorResponse('Validation failed', $validator->errors(), 422);
        }

        $payment = GroupPayment::find($request->id);

        if ($payment->status === 'Void') {
            return $this->ErrorResponse('Payment already void', 'Payment already void');
        }

        $payment->status    = 'Void';
        $payment->void_note = $request->note;
        $payment->voided_by = auth()->id();
        $payment->voided_at = now();
        $payment->save();

        $agent = Agent::find($payment->agent_id);
        if ($agent) {
            $agent->net_balance = $agent->net_balance + $payment->paid_amount;
            $agent->save();
        }

        return $this->SuccessResponse($payment, 'Payment voided successfully');
    }

    /**
     * Restore a voided payment back to Success.
     */
    public function restorePayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer', 'exists:group_payments,id'],
        ]);

        if ($validator->fails()) {
            return $this->ErrorResponse('Validation failed', $validator->errors(), 422);
        }

        $payment = GroupPayment::find($request->id);

        if ($payment->status !== 'Void') {
            return $this->ErrorResponse('Payment is not void', 'Payment is not void');
        }

        $payment->status    = 'Success';
        $payment->void_note = null;
        $payment->voided_by = null;
        $payment->voided_at = null;
        $payment->save();

        $agent = Agent::find($payment->agent_id);
        if ($agent) {
            $agent->net_balance = $agent->net_balance - $payment->paid_amount;
            $agent->save();
        }

        return $this->SuccessResponse($payment, 'Payment restored successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
