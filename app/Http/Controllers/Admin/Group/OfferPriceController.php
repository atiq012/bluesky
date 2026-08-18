<?php

namespace App\Http\Controllers\Admin\Group;

use App\Http\Controllers\BaseController;
use App\Models\GroupRequest\GroupRequest;
use App\Models\GroupRequest\OfferPriceSegment;
use App\Models\GroupRequest\PriceOffer;
use App\Models\GroupRequest\OfferPricePaymentTerm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OfferPriceController extends BaseController
{
    /**
     * Show offer price by ID.
     */
    public function show($id)
    {
        $offer = PriceOffer::with(['segments', 'paymentTerms', 'groupRequest'])
            ->find($id);

        if (!$offer) {
            return $this->ErrorResponse('Offer not found.', 'Offer not found.');
        }

        return $this->SuccessResponse($offer, 'Offer retrieved successfully.');
    }

    /**
     * Accept an offer price.
     */
    public function accept(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer', 'exists:price_offers,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid offer ID.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $offer = PriceOffer::find($request->id);
            $offer->status = 'Offer confirmed';
            $offer->save();

            // Also update the group request status
            // $groupRequest = GroupRequest::find($offer->group_req_id);
            // if ($groupRequest) {
            //     $groupRequest->status = 'Offer confirmed';
            //     $groupRequest->save();
            // }

            return $this->SuccessResponse($offer, 'Offer accepted successfully.');
        } catch (\Throwable $e) {
            report($e);
            return $this->ErrorResponse('Failed to accept offer.', $e->getMessage());
        }
    }

    /**
     * Decline an offer price.
     */
    public function decline(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'   => ['required', 'integer', 'exists:price_offers,id'],
            'note' => ['required', 'string', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Please provide a reason for declining.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $offer = PriceOffer::find($request->id);
            $offer->status = 'Offer Declined';
            $offer->remarks = $request->note;
            $offer->save();

            // Also update the group request status
            $groupRequest = GroupRequest::find($offer->group_req_id);
            if ($groupRequest) {
                if($offer){
                    $groupRequest->status = 'Offer Declined';

                }else{

                    $groupRequest->status = 'Decline';
                }
                $groupRequest->decline_note = $request->note;
                $groupRequest->save();
            }

            return $this->SuccessResponse($offer, 'Offer declined successfully.');
        } catch (\Throwable $e) {
            report($e);
            return $this->ErrorResponse('Failed to decline offer.', $e->getMessage());
        }
    }

    /**
     * Store a new price offer.
     */
    public function store(Request $request)
    {
        $requestType = $request->input('request_type');

        $rules = [
            'group_req_id'   => ['required', 'integer', 'exists:group_requests,id'],
            'request_type'   => ['required', Rule::in(['oneway', 'roundway', 'multicity'])],
            'group_type'     => ['required', 'string', 'max:20'],
            'class_type'     => ['required', 'string', 'max:15'],
            'class_code'     => ['required', 'string', 'max:10'],
            'offered_flight' => ['required', 'string', 'max:100'],

            'currency'       => ['required', Rule::in(['BDT', 'USD'])],
            'exchange_rate'  => ['required_if:currency,USD', 'nullable', 'numeric', 'min:0'],

            'adult_base_fare' => ['required', 'numeric', 'min:0'],
            'adult_tax'        => ['required', 'numeric', 'min:0'],
            'adult_ait'        => ['required', 'numeric', 'min:0'],
            'adult_max_pax'    => ['required', 'integer', 'min:1'],

            'child_base_fare'  => ['nullable', 'numeric', 'min:0'],
            'child_tax'        => ['nullable', 'numeric', 'min:0'],
            'child_ait'        => ['nullable', 'numeric', 'min:0'],
            'child_max_pax'    => ['nullable', 'integer', 'min:0'],

            'infant_base_fare' => ['nullable', 'numeric', 'min:0'],
            'infant_tax'       => ['nullable', 'numeric', 'min:0'],
            'infant_ait'       => ['nullable', 'numeric', 'min:0'],
            'infant_max_pax'   => ['nullable', 'integer', 'min:0'],

            'markup_value'          => ['required', 'numeric'],
            'markup_type'           => ['required', Rule::in(['Percent', 'Flat'])],
            'service_charge_value'  => ['required', 'numeric'],
            'service_charge_type'   => ['required', Rule::in(['Percent', 'Flat'])],
            'est_total_fare'        => ['required', 'numeric', 'min:0'],
            'estimate_net_payable'  => ['required', 'numeric', 'min:0'],

            'policy_fare_rules' => ['required', 'string'],
            'remarks'           => ['nullable', 'string'],
            'status'            => ['nullable', 'string', 'max:50'],

            'payment_terms'                    => ['required', 'array', 'min:1'],
            'payment_terms.*.sequence'          => ['required', 'string', 'max:20'],
            'payment_terms.*.value'             => ['required', 'numeric', 'min:0.01'],
            'payment_terms.*.value_type'        => ['required', Rule::in(['Percent', 'Flat'])],
            'payment_terms.*.amount'            => ['required', 'numeric', 'min:0'],
            'payment_terms.*.due_date'          => ['nullable', 'date'],
        ];

        if ($requestType === 'oneway') {
            $rules['origin']          = ['required', 'string', 'max:50'];
            $rules['destination']     = ['required', 'string', 'max:50'];
            $rules['departure_date']  = ['required', 'date'];
        } elseif ($requestType === 'roundway') {
            $rules['origin']                = ['required', 'string', 'max:50'];
            $rules['destination']           = ['required', 'string', 'max:50'];
            $rules['departure_date']        = ['required', 'date'];
            $rules['return_origin']         = ['required', 'string', 'max:50'];
            $rules['return_destination']    = ['required', 'string', 'max:50'];
            $rules['return_date']           = ['required', 'date', 'after_or_equal:departure_date'];
            $rules['offered_return_flight'] = ['nullable', 'string', 'max:20'];
        } elseif ($requestType === 'multicity') {
            $rules['segments']                        = ['required', 'array', 'min:1'];
            $rules['segments.*.origin']                = ['required', 'string', 'max:50'];
            $rules['segments.*.destination']           = ['required', 'string', 'max:50'];
            $rules['segments.*.departure_date']        = ['required', 'date'];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Please fill in all required fields',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $offer = DB::transaction(function () use ($validated, $requestType, $request) {
                $groupRequest = GroupRequest::findOrFail($validated['group_req_id']);

                $offer = PriceOffer::create([
                    'group_req_id'   => $groupRequest->id,
                    'request_type'   => $requestType,
                    'group_type'     => $validated['group_type'],
                    'class_type'     => $validated['class_type'],
                    'class_code'     => $validated['class_code'],

                    'currency'       => $validated['currency'],
                    'exchange_rate'  => $validated['exchange_rate'] ?? null,

                    'offered_flight'        => $validated['offered_flight'],
                    'offered_flight_no'        => $request->offered_flight_no ?? null,
                    'offered_return_flight' => $validated['offered_return_flight'] ?? null,

                    'origin'              => $requestType !== 'multicity' ? $validated['origin'] : null,
                    'destination'         => $requestType !== 'multicity' ? $validated['destination'] : null,
                    'departure_date'      => $requestType !== 'multicity' ? $validated['departure_date'] : null,
                    'return_origin'       => $validated['return_origin'] ?? null,
                    'return_destination'  => $validated['return_destination'] ?? null,
                    'return_date'         => $validated['return_date'] ?? null,

                    'adult_traveler'  => $groupRequest->adult_traveler,
                    'child_traveler'  => $groupRequest->child_traveler,
                    'infant_traveler' => $groupRequest->infant_traveler,
                    'total_traveler'  => $groupRequest->total_traveler,

                    'adult_base_fare' => $validated['adult_base_fare'],
                    'adult_tax'       => $validated['adult_tax'],
                    'adult_ait'       => $validated['adult_ait'],
                    'adult_max_pax'   => $validated['adult_max_pax'],

                    'child_base_fare' => $validated['child_base_fare'] ?? null,
                    'child_tax'       => $validated['child_tax'] ?? null,
                    'child_ait'       => $validated['child_ait'] ?? null,
                    'child_max_pax'   => $validated['child_max_pax'] ?? null,

                    'infant_base_fare' => $validated['infant_base_fare'] ?? null,
                    'infant_tax'       => $validated['infant_tax'] ?? null,
                    'infant_ait'       => $validated['infant_ait'] ?? null,
                    'infant_max_pax'   => $validated['infant_max_pax'] ?? null,

                    'est_total_fare'       => $validated['est_total_fare'],
                    'markup_value'         => $validated['markup_value'],
                    'markup_type'          => $validated['markup_type'],
                    'service_charge_value' => $validated['service_charge_value'],
                    'service_charge_type'  => $validated['service_charge_type'],
                    'estimate_net_payable' => $validated['estimate_net_payable'],

                    'policy_fare_rules' => $validated['policy_fare_rules'],
                    'status'            => 'Price offer',
                    'remarks'           => $validated['remarks'] ?? null,

                    'created_by' => auth()->user()->name ?? auth()->id(),
                    'updated_by' => auth()->user()->name ?? auth()->id(),
                ]);

                if ($requestType === 'multicity') {
                    foreach ($validated['segments'] as $index => $seg) {
                        OfferPriceSegment::create([
                            'offer_price_id' => $offer->id,
                            'sequence'       => $index + 1,
                            'origin'         => $seg['origin'],
                            'destination'    => $seg['destination'],
                            'departure_date' => $seg['departure_date'],
                            'flight_no'      => $seg['flight_no'] ?? null,
                        ]);
                    }
                }

                foreach ($validated['payment_terms'] as $term) {
                    OfferPricePaymentTerm::create([
                        'offer_price_id' => $offer->id,
                        'sequence'       => $term['sequence'],
                        'value'          => $term['value'],
                        'value_type'     => $term['value_type'],
                        'amount'         => $term['amount'],
                        'due_date'       => $term['due_date'] ?? null,
                    ]);
                }

                $groupRequest->status = 'Price offer';
                $groupRequest->save();

                return $offer;
            });

            return response()->json([
                'message' => 'Price offer submitted successfully',
                'data'    => $offer->load(['segments', 'paymentTerms']),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
