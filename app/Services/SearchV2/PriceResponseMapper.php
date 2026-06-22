<?php

namespace App\Services\SearchV2;

class PriceResponseMapper
{
    public function map(array $providerResponse): array
    {
        $root = $providerResponse['OfferListResponse'] ?? $providerResponse;

        $errors = $root['Result']['Error'] ?? [];
        if (!empty($errors)) {
            $msg = $errors[0]['Message'] ?? 'Unknown error from Travelport price API';
            throw new \Exception('Travelport price error: ' . $msg);
        }

        $rawWarnings = $root['Result']['Warning'] ?? [];
        if (!empty($rawWarnings) && !array_is_list($rawWarnings)) {
            $rawWarnings = [$rawWarnings];
        }
        $warnings = array_values(array_filter(array_map(
            fn($w) => isset($w['Message']) ? (string) $w['Message'] : null,
            $rawWarnings
        )));

        $offerIdentifier = $root['Identifier']['value'] ?? null;
        $offers          = $root['OfferID'] ?? [];
        if (!empty($offers) && !array_is_list($offers)) {
            $offers = [$offers];
        }

        $brandMap = [];
        foreach ($root['ReferenceList'] ?? [] as $refList) {
            if (($refList['@type'] ?? '') === 'ReferenceListBrand') {
                foreach ($refList['Brand'] ?? [] as $brand) {
                    $brandMap[$brand['id']] = $brand;
                }
            }
        }

        $offer = $offers[0] ?? null;
        if (!$offer) {
            return $this->emptyResult($offerIdentifier);
        }

        $price     = $offer['Price'] ?? [];
        $products  = $offer['Product'] ?? [];
        if (!empty($products) && !array_is_list($products)) {
            $products = [$products];
        }

        $tncs = $offer['TermsAndConditionsFull'] ?? [];
        if (!empty($tncs) && !array_is_list($tncs)) {
            $tncs = [$tncs];
        }
        $tnc = $tncs[0] ?? [];

        return [
            'offer_id'                     => $offer['id'] ?? null,
            'offer_identifier'             => $offerIdentifier,
            'currency'                     => $price['CurrencyCode']['value'] ?? 'BDT',
            'base_fare'                    => (float) ($price['Base']        ?? 0),
            'total_taxes'                  => (float) ($price['TotalTaxes']  ?? 0),
            'total_fees'                   => (float) ($price['TotalFees']   ?? 0),
            'total_price'                  => (float) ($price['TotalPrice']  ?? 0),
            'price_breakdown'              => $this->mapPriceBreakdown($price['PriceBreakdown'] ?? []),
            'products'                     => $this->mapProducts($products, $tnc),
            'brand'                        => $this->mapBrand($products, $brandMap),
            'restrictions'                 => array_map(fn($r) => $r['value'] ?? '', $tnc['Restriction'] ?? []),
            'penalties'                    => $this->mapPenalties($tnc['Penalties'] ?? []),
            'payment_time_limit'           => $tnc['PaymentTimeLimit'] ?? null,
            'expiry_date'                  => $tnc['ExpiryDate'] ?? null,
            'validating_airline'           => $tnc['ValidatingAirline'][0]['ValidatingAirline'] ?? null,
            'secure_flight_required'       => (bool) ($tnc['secureFlightPassengerDataRequiredInd'] ?? false),
            'fare_guarantee_policy'        => $tnc['FareGuaranteePolicy'][0]['Code']['value'] ?? null,
            'warnings'                     => $warnings,
        ];
    }

    private function mapPriceBreakdown(array $breakdowns): array
    {
        $ptcMap = ['ADT' => 'Adult', 'CNN' => 'Child', 'CHD' => 'Child', 'INF' => 'Infant', 'INS' => 'Infant'];
        $result = [];

        foreach ($breakdowns as $bd) {
            $ptc    = $bd['requestedPassengerType'] ?? '';
            $amount = $bd['Amount'] ?? [];
            $taxes  = $amount['Taxes']['Tax'] ?? [];

            $result[] = [
                'type'              => $ptcMap[$ptc] ?? $ptc,
                'passenger_type_code' => $ptc,
                'quantity'          => (int) ($bd['quantity'] ?? 1),
                'base_fare'         => (float) ($amount['Base'] ?? 0),
                'total_taxes'       => (float) ($amount['Taxes']['TotalTaxes'] ?? 0),
                'total_fees'        => (float) ($amount['Fees']['TotalFees'] ?? 0),
                'total_price'       => (float) ($amount['Total'] ?? 0),
                'filed_amount'      => [
                    'currency' => $bd['FiledAmount']['currencyCode'] ?? 'USD',
                    'value'    => (float) ($bd['FiledAmount']['value'] ?? 0),
                ],
                'fare_calculation'  => $bd['FareCalculation'] ?? '',
                'taxes'             => array_map(fn($t) => [
                    'code'        => $t['taxCode']     ?? '',
                    'description' => $t['description'] ?? '',
                    'amount'      => (float) ($t['value'] ?? 0),
                ], $taxes),
            ];
        }

        return $result;
    }

    private function mapProducts(array $products, array $tnc): array
    {
        $baggageByProduct = $this->groupBaggageByProduct($tnc['BaggageAllowance'] ?? []);
        $result = [];
        $directions = ['outbound', 'inbound'];

        foreach ($products as $i => $product) {
            $pf  = $product['PassengerFlight'][0] ?? [];
            $fps = $pf['FlightProduct'][0] ?? [];

            $brandRef = $fps['Brand']['BrandRef'] ?? null;

            $result[] = [
                'id'              => $product['id'] ?? null,
                'direction'       => $directions[$i] ?? 'segment_' . $i,
                'total_duration'  => $product['totalDuration'] ?? '',
                'brand_ref'       => $brandRef,
                'cabin'           => $fps['cabin'] ?? '',
                'class_of_service' => $fps['classOfService'] ?? '',
                'fare_basis_code' => $fps['fareBasisCode'] ?? '',
                'fare_type'       => $fps['fareType'] ?? '',
                'flight'          => $this->mapProductFlight($product),
                'baggage' => $this->extractBaggageForProduct($product['id'] ?? null, $baggageByProduct),
            ];
        }

        return $result;
    }

    private function normalizeFlightSegments(array $product): array
    {
        $segments = $product['FlightSegment'] ?? [];
        if ($segments === []) {
            return [];
        }
        if (!array_is_list($segments)) {
            return [$segments];
        }

        return $segments;
    }

    private function mapProductFlight(array $product): array
    {
        $segments = $this->normalizeFlightSegments($product);
        $firstFlt = $segments[0]['Flight'] ?? [];
        $lastFlt  = $segments[count($segments) - 1]['Flight'] ?? $firstFlt;

        $flightNumbers = [];
        $equipment     = [];
        foreach ($segments as $segment) {
            $flt = $segment['Flight'] ?? [];
            $no  = trim((string) ($flt['carrier'] ?? '') . (string) ($flt['number'] ?? ''));
            if ($no !== '') {
                $flightNumbers[] = $no;
            }
            $equip = trim((string) ($flt['equipment'] ?? ''));
            if ($equip !== '' && !in_array($equip, $equipment, true)) {
                $equipment[] = $equip;
            }
        }

        $stops = max(0, count($segments) - 1);

        return [
            'carrier'        => $firstFlt['carrier'] ?? '',
            'number'         => $firstFlt['number'] ?? '',
            'equipment'      => $firstFlt['equipment'] ?? '',
            'equipment_list' => $equipment,
            'flight_numbers' => implode(' · ', $flightNumbers),
            'stops'          => $stops,
            'departure'      => [
                'location' => $firstFlt['Departure']['location'] ?? '',
                'date'     => $firstFlt['Departure']['date'] ?? '',
                'time'     => $firstFlt['Departure']['time'] ?? '',
                'terminal' => $firstFlt['Departure']['terminal'] ?? '',
            ],
            'arrival' => [
                'location' => $lastFlt['Arrival']['location'] ?? '',
                'date'     => $lastFlt['Arrival']['date'] ?? '',
                'time'     => $lastFlt['Arrival']['time'] ?? '',
                'terminal' => $lastFlt['Arrival']['terminal'] ?? '',
            ],
        ];
    }

    private function groupBaggageByProduct(array $baggageAllowances): array
    {
        $map = [];
        foreach ($baggageAllowances as $bag) {
            foreach ($bag['ProductRef'] ?? [] as $pRef) {
                $map[$pRef][] = $bag;
            }
        }
        return $map;
    }

    private function extractBaggageForProduct(?string $productId, array $baggageByProduct): array
    {
        if (!$productId || empty($baggageByProduct[$productId])) {
            return [];
        }

        $checkedBags = [];
        $result      = [];

        foreach ($baggageByProduct[$productId] as $bag) {
            $item     = $bag['BaggageItem'][0] ?? [];
            $rawType  = $bag['baggageType'] ?? '';
            $included = ($item['includedInOfferPrice'] ?? '') === 'Yes';

            $weight = '';
            $measurements = $item['Measurement'] ?? [];
            foreach ($measurements as $m) {
                if (($m['measurementType'] ?? '') === 'Weight') {
                    $weight = $m['value'] . strtolower($m['unit'] === 'Kilograms' ? 'kg' : $m['unit']);
                }
            }
            if (empty($weight)) {
                $texts = $bag['Text'] ?? [];
                if (!empty($texts[0])) {
                    $weight = $texts[0];
                }
            }

            $isChecked = in_array($rawType, ['FirstCheckedBag', 'SecondCheckedBag', 'Checked', 'checked']);
            $isCarryOn = in_array($rawType, ['CarryOn', 'Carry-On', 'carryon', 'CARRYON']);

            if ($isChecked) {
                $checkedBags[] = ['weight' => $weight, 'included' => $included];
            } elseif ($isCarryOn) {
                $result[] = [
                    'type'     => 'carry_on',
                    'label'    => 'Carry-On',
                    'quantity' => 1,
                    'weight'   => $weight,
                    'included' => $included,
                ];
            }
        }

        if (!empty($checkedBags)) {
            $qty = count($checkedBags);
            array_unshift($result, [
                'type'     => 'checked',
                'label'    => 'Checked Bag',
                'quantity' => $qty,
                'weight'   => $checkedBags[0]['weight'],
                'included' => $checkedBags[0]['included'],
            ]);
        }

        return $result;
    }

    private function mapBrand(array $products, array $brandMap): ?array
    {
        $brandRef = null;
        foreach ($products as $product) {
            $pf  = $product['PassengerFlight'][0] ?? [];
            $fps = $pf['FlightProduct'][0] ?? [];
            $brandRef = $fps['Brand']['BrandRef'] ?? null;
            if ($brandRef) {
                break;
            }
        }

        if (!$brandRef || empty($brandMap[$brandRef])) {
            return null;
        }

        $brand = $brandMap[$brandRef];

        return [
            'id'         => $brand['id'] ?? null,
            'name'       => $brand['name'] ?? '',
            'tier'       => $brand['tier'] ?? null,
            'code'       => $brand['code'] ?? '',
            'image_url'  => $brand['ImageURL'][0] ?? null,
            'attributes' => array_map(fn($a) => [
                'classification' => $a['classification'] ?? '',
                'inclusion'      => $a['inclusion']      ?? '',
            ], $brand['BrandAttribute'] ?? []),
            'additional_attributes' => array_map(fn($a) => [
                'classification' => $a['Classification'] ?? '',
                'inclusion'      => $a['Inclusion']      ?? '',
            ], $brand['AdditionalBrandAttribute'] ?? []),
        ];
    }

    private function mapPenalties(array $penaltiesList): array
    {
        $result = ['change' => null, 'cancel' => null];

        foreach ($penaltiesList as $penalties) {
            $change = $penalties['Change'][0] ?? null;
            $cancel = $penalties['Cancel'][0] ?? null;

            if ($change) {
                $result['change'] = [
                    'amount'     => (float) ($change['Penalty'][0]['Amount']['value'] ?? 0),
                    'currency'   => $change['Penalty'][0]['Amount']['code'] ?? 'BDT',
                    'applies_to' => $change['PenaltyAppliesTo'] ?? 'PerTicket',
                    'types'      => $change['penaltyTypes'] ?? [],
                ];
            }

            if ($cancel) {
                $result['cancel'] = [
                    'amount'     => (float) ($cancel['Penalty'][0]['Amount']['value'] ?? 0),
                    'currency'   => $cancel['Penalty'][0]['Amount']['code'] ?? 'BDT',
                    'applies_to' => $cancel['PenaltyAppliesTo'] ?? 'PerTicket',
                    'types'      => $cancel['penaltyTypes'] ?? [],
                ];
            }
        }

        return $result;
    }

    private function emptyResult(?string $offerIdentifier): array
    {
        return [
            'offer_id'               => null,
            'offer_identifier'       => $offerIdentifier,
            'currency'               => 'BDT',
            'base_fare'              => 0,
            'total_taxes'            => 0,
            'total_fees'             => 0,
            'total_price'            => 0,
            'price_breakdown'        => [],
            'products'               => [],
            'brand'                  => null,
            'restrictions'           => [],
            'penalties'              => ['change' => null, 'cancel' => null],
            'payment_time_limit'     => null,
            'expiry_date'            => null,
            'validating_airline'     => null,
            'secure_flight_required' => false,
            'fare_guarantee_policy'  => null,
            'warnings'               => [],
        ];
    }
}
