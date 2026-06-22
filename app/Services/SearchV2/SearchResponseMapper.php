<?php

namespace App\Services\SearchV2;

use App\Services\AccessControl\AirlineRestrictionResolver;
use Illuminate\Support\Facades\DB;

class SearchResponseMapper
{
    private array $flightMap    = [];
    private array $productMap   = [];
    private array $brandMap     = [];
    private array $tncMap       = [];
    private array $airportMap   = [];
    private array $airlineDbMap = [];

    public function __construct(
        private readonly AirlineRestrictionResolver $restrictionResolver,
    ) {}

    public function map(array $providerResponse, array $form, ?int $agencyId = null): array
    {
        $root = $providerResponse['CatalogProductOfferingsResponse'] ?? $providerResponse;

        $this->buildReferenceMaps($root['ReferenceList'] ?? []);
        $this->buildAirportMap();
        $this->buildAirlineDbMap();

        $catalogIdentifier = $root['CatalogProductOfferings']['Identifier']['value'] ?? null;

        if (empty($this->flightMap)) {
            return ['flights' => [], 'catalog_identifier' => $catalogIdentifier];
        }

        $offerings = $root['CatalogProductOfferings']['CatalogProductOffering'] ?? [];
        if (!empty($offerings) && !array_is_list($offerings)) {
            $offerings = [$offerings];
        }

        $wayType        = (int) ($form['Way'] ?? 1);
        $preferredCabin = (string) ($form['cabin_class'] ?? '');

        $outboundOfferings = array_values(array_filter($offerings, fn($o) => (int) ($o['sequence'] ?? 1) === 1));
        $inboundOfferings  = array_values(array_filter($offerings, fn($o) => (int) ($o['sequence'] ?? 0) === 2));

        $flights = [];

        foreach ($outboundOfferings as $outOffering) {
            $outOfferingId = $outOffering['id'] ?? null;

            foreach ($outOffering['ProductBrandOptions'] ?? [] as $outPbo) {
                $outRefs = $outPbo['flightRefs'] ?? [];
                $outLeg  = $this->mapLegFromRefs($outRefs, $outPbo, $preferredCabin);
                if ($outLeg === null) {
                    continue;
                }
                $outLeg['_offering_id'] = $outOfferingId;

                if ($wayType !== 2 || empty($inboundOfferings)) {
                    $flights[] = [
                        'outbound'     => $outLeg,
                        'inbound'      => null,
                        'all_segments' => $outLeg['segments'],
                    ];
                    continue;
                }

                $outCodes = $outPbo['ProductBrandOffering'][0]['CombinabilityCode'] ?? [];

                foreach ($inboundOfferings as $inOffering) {
                    $inOfferingId = $inOffering['id'] ?? null;

                    foreach ($inOffering['ProductBrandOptions'] ?? [] as $inPbo) {
                        $inCodes = $inPbo['ProductBrandOffering'][0]['CombinabilityCode'] ?? [];

                        if (!empty($outCodes) && !empty($inCodes) && empty(array_intersect($outCodes, $inCodes))) {
                            continue;
                        }

                        $inRefs = $inPbo['flightRefs'] ?? [];
                        $inLeg  = $this->mapLegFromRefs($inRefs, $inPbo, $preferredCabin);
                        if ($inLeg === null) {
                            continue;
                        }
                        $inLeg['_offering_id'] = $inOfferingId;

                        $flights[] = [
                            'outbound'     => $outLeg,
                            'inbound'      => $inLeg,
                            'all_segments' => array_merge($outLeg['segments'], $inLeg['segments']),
                        ];
                    }
                }
            }
        }

        usort($flights, fn($a, $b) => $a['outbound']['totalPrice'] <=> $b['outbound']['totalPrice']);

        $blocked = $this->restrictionResolver->getBlockedCodes($agencyId);
        if (!empty($blocked)) {
            $flights = array_values(array_filter($flights, function ($flight) use ($blocked) {
                $outCode = $flight['outbound']['first_carrier_code'] ?? '';
                $inCode  = $flight['inbound']['first_carrier_code'] ?? '';
                return !isset($blocked[$outCode]) && ($inCode === '' || !isset($blocked[$inCode]));
            }));
        }

        return [
            'flights'            => array_values($flights),
            'catalog_identifier' => $catalogIdentifier,
        ];
    }

    private function mapLegFromRefs(array $refs, ?array $pbo, string $preferredCabin = ''): ?array
    {
        if (empty($refs)) {
            return null;
        }

        $pboffer          = null;
        $cheapest         = PHP_INT_MAX;
        $cabinMatch       = null;
        $cabinMatchPrice  = PHP_INT_MAX;

        foreach ($pbo['ProductBrandOffering'] ?? [] as $candidate) {
            $price      = (float) ($candidate['BestCombinablePrice']['TotalPrice'] ?? PHP_INT_MAX);
            $productRef = $candidate['Product'][0]['productRef'] ?? null;
            $product    = $productRef ? ($this->productMap[$productRef] ?? null) : null;
            $cabin      = $product['PassengerFlight'][0]['FlightProduct'][0]['cabin'] ?? '';

            if ($price < $cheapest) {
                $cheapest = $price;
                $pboffer  = $candidate;
            }

            if (!empty($preferredCabin) && strcasecmp($cabin, $preferredCabin) === 0 && $price < $cabinMatchPrice) {
                $cabinMatchPrice = $price;
                $cabinMatch      = $candidate;
            }
        }

        if ($cabinMatch !== null) {
            $pboffer = $cabinMatch;
        }

        $productRef = $pboffer['Product'][0]['productRef']                    ?? null;
        $brandRef   = $pboffer['Brand']['BrandRef']                           ?? null;
        $tncRef     = $pboffer['TermsAndConditions']['termsAndConditionsRef']  ?? null;

        $product = $productRef ? ($this->productMap[$productRef] ?? null) : null;
        $brand   = $brandRef   ? ($this->brandMap[$brandRef]    ?? null) : null;
        $tnc     = $tncRef     ? ($this->tncMap[$tncRef]        ?? null) : null;

        $classMap = $this->buildSegmentClassMap($product);
        $segments = $this->buildSegments($refs, $classMap);

        if (empty($segments)) {
            return null;
        }

        $first = $segments[0];
        $last  = $segments[count($segments) - 1];

        $priceDetail = $pboffer['BestCombinablePrice'] ?? [];
        $currency    = $priceDetail['CurrencyCode']['value'] ?? 'BDT';
        $totalPrice  = (float) ($priceDetail['TotalPrice'] ?? 0);

        $fp = $product['PassengerFlight'][0]['FlightProduct'][0] ?? [];

        return [
            'origin'             => $first['departure_code'],
            'destination'        => $last['arrival_code'],
            'departure_time'     => $first['departure_time'],
            'departure_date'     => $first['departure_date'],
            'arrival_time'       => $last['arrival_time'],
            'arrival_date'       => $last['arrival_date'],
            'first_carrier_code'  => $first['carrier_code'],
            'first_flight_number' => $first['flight_number'],
            'first_airline_name'  => $first['airline_name'],
            'first_logo_path'     => $first['logo_path'],
            'total_flight_time'  => $this->calcTotalDuration($refs),
            'connections'        => ['stops' => $this->buildStops($segments)],
            'refundable'         => $this->isRefundable($brand),
            'refund_type'        => $this->getRefundType($brand),
            'totalPrice'         => $totalPrice,
            'currency'           => $currency,
            'cabin'              => $fp['cabin']          ?? '',
            'classOfService'     => $fp['classOfService'] ?? '',
            'fareBasisCode'      => $fp['fareBasisCode']  ?? '',
            'fareType'           => $fp['fareType']       ?? '',
            'validatingAirline'       => $tnc['ValidatingAirline'][0]['ValidatingAirline'] ?? $first['carrier_code'],
            'priceBreakdown'          => $this->buildPriceBreakdown($priceDetail['PriceBreakdown'] ?? []),
            'baggage_allowance'       => $this->extractBaggage($tnc),
            'segments'                => $segments,
            'key'                     => implode('-', $refs),
            'pricingMethod'           => 'JSON_V2',
            'brand_options'           => $this->buildBrandOptions($pbo),
            '_selected_productRef'    => $productRef,
            '_selected_brandRef'      => $brandRef,
            '_selected_tncRef'        => $tncRef,
        ];
    }

    private function buildBrandOptions(?array $pbo): array
    {
        if (!$pbo) {
            return [];
        }

        $rawFlightRefs = $pbo['flightRefs'] ?? [];
        $flightRefs    = implode(',', $rawFlightRefs);

        $availSourceCodes = [];
        foreach ($rawFlightRefs as $fRef) {
            $code = $this->flightMap[$fRef]['AvailabilitySourceCode'] ?? null;
            if ($code !== null) {
                $availSourceCodes[$fRef] = $code;
            }
        }

        $options = [];
        foreach ($pbo['ProductBrandOffering'] ?? [] as $pboffer) {
            $productRef = $pboffer['Product'][0]['productRef']                   ?? null;
            $brandRef   = $pboffer['Brand']['BrandRef']                          ?? null;
            $tncRef     = $pboffer['TermsAndConditions']['termsAndConditionsRef'] ?? null;

            $product = $productRef ? ($this->productMap[$productRef] ?? null) : null;
            $brand   = $brandRef   ? ($this->brandMap[$brandRef]    ?? null) : null;
            $tnc     = $tncRef     ? ($this->tncMap[$tncRef]        ?? null) : null;

            $priceDetail    = $pboffer['BestCombinablePrice'] ?? [];
            $fp             = $product['PassengerFlight'][0]['FlightProduct'][0] ?? [];
            $rawAttributes  = $brand['BrandAttribute'] ?? [];
            $rawAdditional  = $brand['AdditionalBrandAttribute'] ?? [];
            $isDefaultBrand = $brand === null;

            $attributes = $isDefaultBrand
                ? [
                    ['classification' => 'CarryOn',        'inclusion' => 'Chargeable'],
                    ['classification' => 'CheckedBag',     'inclusion' => 'Chargeable'],
                    ['classification' => 'Refund',         'inclusion' => 'Chargeable'],
                    ['classification' => 'Rebooking',      'inclusion' => 'Chargeable'],
                    ['classification' => 'Meals',          'inclusion' => 'Not Offered'],
                    ['classification' => 'SeatAssignment', 'inclusion' => 'Chargeable'],
                ]
                : [
                    ...array_map(fn($a) => [
                        'classification' => $a['classification'] ?? '',
                        'inclusion'      => $a['inclusion']      ?? '',
                    ], $rawAttributes),
                    ...array_map(fn($a) => [
                        'classification' => $a['Classification'] ?? '',
                        'inclusion'      => $a['Inclusion']      ?? '',
                    ], $rawAdditional),
                ];

            $label = $brand['name'] ?? ($fp['cabin'] ?? 'Economy');

            $options[] = [
                'label'            => $label,
                'fare_basis_code'  => $fp['fareBasisCode'] ?? '',
                'cabin'            => $fp['cabin'] ?? 'Economy',
                'class_of_service' => $fp['classOfService'] ?? '',
                'price'            => (float) ($priceDetail['TotalPrice']           ?? 0),
                'currency'         => $priceDetail['CurrencyCode']['value']          ?? 'BDT',
                'price_breakdown'  => $this->buildPriceBreakdown($priceDetail['PriceBreakdown'] ?? []),
                'baggage_allowance'        => $this->extractBaggage($tnc),
                'attributes'               => $attributes,
                'is_default_brand'         => $isDefaultBrand,
                '_refs'                    => 'flights:' . $flightRefs . ' | ' . $productRef . ' | ' . $brandRef . ' | ' . $tncRef,
                '_productRef'              => $productRef,
                '_brandRef'                => $brandRef,
                '_tncRef'                  => $tncRef,
                '_combinabilityCode'       => $pboffer['CombinabilityCode'] ?? [],
                'availability_source_codes'=> $availSourceCodes,
            ];
        }

        return $options;
    }

    // Total door-to-door: sum ISO durations (timezone-aware) + layovers (same-airport timestamps = same tz)
    private function calcTotalDuration(array $refs): string
    {
        if (empty($refs)) {
            return '';
        }

        $totalMin = 0;

        foreach ($refs as $ref) {
            $iso = $this->flightMap[$ref]['duration'] ?? '';
            preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?/', $iso, $m);
            $totalMin += (int) ($m[1] ?? 0) * 60 + (int) ($m[2] ?? 0);
        }

        for ($i = 0; $i < count($refs) - 1; $i++) {
            $curr  = $this->flightMap[$refs[$i]]     ?? [];
            $next  = $this->flightMap[$refs[$i + 1]] ?? [];
            $arrTs = strtotime(($curr['Arrival']['date']   ?? '') . ' ' . ($curr['Arrival']['time']   ?? ''));
            $depTs = strtotime(($next['Departure']['date'] ?? '') . ' ' . ($next['Departure']['time'] ?? ''));
            if ($arrTs && $depTs && $depTs > $arrTs) {
                $totalMin += (int) (($depTs - $arrTs) / 60);
            }
        }

        if ($totalMin <= 0) {
            return '';
        }

        return (int) floor($totalMin / 60) . ' hr ' . str_pad((string) ($totalMin % 60), 2, '0', STR_PAD_LEFT) . ' min';
    }

    private function buildReferenceMaps(array $refList): void
    {
        $this->flightMap  = [];
        $this->productMap = [];
        $this->brandMap   = [];
        $this->tncMap     = [];

        foreach ($refList as $ref) {
            switch ($ref['@type'] ?? '') {
                case 'ReferenceListFlight':
                    foreach ($ref['Flight'] ?? [] as $f) {
                        $this->flightMap[$f['id']] = $f;
                    }
                    break;
                case 'ReferenceListProduct':
                    foreach ($ref['Product'] ?? [] as $p) {
                        $this->productMap[$p['id']] = $p;
                    }
                    break;
                case 'ReferenceListBrand':
                    foreach ($ref['Brand'] ?? [] as $b) {
                        $this->brandMap[$b['id']] = $b;
                    }
                    break;
                case 'ReferenceListTermsAndConditions':
                    foreach ($ref['TermsAndConditions'] ?? [] as $t) {
                        $this->tncMap[$t['id']] = $t;
                    }
                    break;
            }
        }
    }

    private function buildAirportMap(): void
    {
        $codes = [];
        foreach ($this->flightMap as $flight) {
            $dep = $flight['Departure']['location'] ?? null;
            $arr = $flight['Arrival']['location']   ?? null;
            if ($dep) $codes[] = $dep;
            if ($arr) $codes[] = $arr;
            foreach ($flight['IntermediateStop'] ?? [] as $stop) {
                if ($stop['value'] ?? null) $codes[] = $stop['value'];
            }
        }
        $codes = array_unique(array_filter($codes));
        if (empty($codes)) {
            return;
        }

        $rows = DB::table('airports')->whereIn('code', $codes)->get(['code', 'Airport_Name', 'City_name']);
        foreach ($rows as $row) {
            $this->airportMap[$row->code] = [
                'name' => $row->Airport_Name,
                'city' => $row->City_name,
            ];
        }
    }

    private function buildAirlineDbMap(): void
    {
        $codes = [];
        foreach ($this->flightMap as $flight) {
            $code = $flight['carrier'] ?? null;
            if ($code) $codes[] = $code;
        }
        $codes = array_unique(array_filter($codes));
        if (empty($codes)) {
            return;
        }

        $rows = DB::table('airline_logos')->whereIn('code', $codes)->get(['code', 'name', 'logo_path']);
        foreach ($rows as $row) {
            $this->airlineDbMap[$row->code] = [
                'name'      => $row->name,
                'logo_path' => $row->logo_path,
            ];
        }
    }

    private function buildSegmentClassMap(?array $product): array
    {
        if (!$product) {
            return [];
        }

        $seqToRef = [];
        foreach ($product['FlightSegment'] ?? [] as $seg) {
            $seq = (int) ($seg['sequence'] ?? 0);
            $ref = $seg['Flight']['FlightRef'] ?? null;
            if ($seq && $ref) {
                $seqToRef[$seq] = $ref;
            }
        }

        $refToClass = [];
        foreach ($product['PassengerFlight'] ?? [] as $pf) {
            foreach ($pf['FlightProduct'] ?? [] as $fp) {
                $cabin = $fp['cabin']          ?? '';
                $cos   = $fp['classOfService'] ?? '';
                foreach ($fp['segmentSequence'] ?? [] as $seq) {
                    $ref = $seqToRef[(int) $seq] ?? null;
                    if ($ref) {
                        $refToClass[$ref] = ['cabin' => $cabin, 'classOfService' => $cos];
                    }
                }
            }
        }

        return $refToClass;
    }

    private function buildSegments(array $refs, array $classMap = []): array
    {
        // Connection layovers between adjacent flights
        $connLayoverMap = [];
        for ($j = 0; $j < count($refs) - 1; $j++) {
            $curr  = $this->flightMap[$refs[$j]]     ?? [];
            $next  = $this->flightMap[$refs[$j + 1]] ?? [];
            $arrTs = strtotime(($curr['Arrival']['date']   ?? '') . ' ' . ($curr['Arrival']['time']   ?? ''));
            $depTs = strtotime(($next['Departure']['date'] ?? '') . ' ' . ($next['Departure']['time'] ?? ''));
            $min   = max(0, (int) (($depTs - $arrTs) / 60));
            $connLayoverMap[$refs[$j]] = (int) floor($min / 60) . ' hr ' . str_pad((string) ($min % 60), 2, '0', STR_PAD_LEFT) . ' min';
        }

        // Flatten refs into individual legs, expanding IntermediateStop into sub-legs
        $legs = [];
        foreach ($refs as $refIdx => $ref) {
            $flight       = $this->flightMap[$ref] ?? null;
            if (!$flight) continue;

            $isLastFlight = ($refIdx === count($refs) - 1);
            $connLayover  = $isLastFlight ? '' : ($connLayoverMap[$ref] ?? '');
            $intStops     = $flight['IntermediateStop'] ?? [];

            if (empty($intStops)) {
                $legs[] = ['ref' => $ref, 'flight' => $flight, 'dep' => $flight['Departure'], 'arr' => $flight['Arrival'], 'duration' => $flight['duration'] ?? '', 'layover' => $connLayover];
            } else {
                $currDep = $flight['Departure'];
                foreach ($intStops as $stop) {
                    $legs[] = [
                        'ref'      => $ref,
                        'flight'   => $flight,
                        'dep'      => $currDep,
                        'arr'      => ['location' => $stop['value'] ?? '', 'date' => $stop['arrivalDate'] ?? '', 'time' => $stop['arrivalTime'] ?? '', 'terminal' => $stop['arrivalTerminal'] ?? ''],
                        'duration' => $stop['arrivalFlightDuration'] ?? '',
                        'layover'  => $this->formatDuration($stop['duration'] ?? ''),
                    ];
                    $currDep = ['location' => $stop['value'] ?? '', 'date' => $stop['departureDate'] ?? '', 'time' => $stop['departurelTime'] ?? '', 'terminal' => $stop['departureTerminal'] ?? ''];
                }
                $lastStop = end($intStops);
                $legs[] = ['ref' => $ref, 'flight' => $flight, 'dep' => $currDep, 'arr' => $flight['Arrival'], 'duration' => $lastStop['departureFlightDuration'] ?? '', 'layover' => $connLayover];
            }
        }

        $segments  = [];
        $lastIndex = count($legs) - 1;

        foreach ($legs as $i => $leg) {
            $ref    = $leg['ref'];
            $flight = $leg['flight'];
            $dep    = $leg['dep'];
            $arr    = $leg['arr'];
            $isLast = ($i === $lastIndex);

            $carrier = $flight['carrier'] ?? '';
            $depCode = $dep['location']   ?? '';
            $arrCode = $arr['location']   ?? '';

            $airlineName = $this->airlineDbMap[$carrier]['name']      ?? 'No Information';
            $logoPath    = $this->airlineDbMap[$carrier]['logo_path'] ?? $this->resolveLogoPath($carrier);

            $depAirportName = $this->airportMap[$depCode]['name'] ?? $depCode;
            $depCityName    = $this->airportMap[$depCode]['city'] ?? $depCode;
            $arrAirportName = $this->airportMap[$arrCode]['name'] ?? $arrCode;
            $arrCityName    = $this->airportMap[$arrCode]['city'] ?? $arrCode;

            $cabinClass  = $classMap[$ref]['cabin']          ?? '';
            $bookingCode = $classMap[$ref]['classOfService'] ?? '';

            $opCarrier   = $flight['operatingCarrier']       ?? '';
            $opName      = $flight['operatingCarrierName']   ?? '';
            $opNumber    = $flight['operatingCarrierNumber'] ?? '';
            $isCodeshare = $opCarrier !== '' && $opCarrier !== $carrier;

            $segments[] = [
                'carrier_code'             => $carrier,
                'flight_number'            => $carrier . ($flight['number'] ?? ''),
                'airline_name'             => $airlineName,
                'logo_path'                => $logoPath,
                'departure_code'           => $depCode,
                'arrival_code'             => $arrCode,
                'departure_time'           => $this->formatTime($dep['date'] ?? '', $dep['time'] ?? ''),
                'departure_date'           => $dep['date'] ?? '',
                'arrival_time'             => $this->formatTime($arr['date'] ?? '', $arr['time'] ?? ''),
                'arrival_date'             => $arr['date'] ?? '',
                'departure_terminal'       => $dep['terminal'] ?? '',
                'arrival_terminal'         => $arr['terminal'] ?? '',
                'duration'                 => $this->formatDuration($leg['duration']),
                'equipment'                => $flight['equipment'] ?? '',
                'aircraft_name'            => $flight['equipment'] ?? '',
                'layover_time'             => $isLast ? '' : $leg['layover'],
                'is_codeshare'             => $isCodeshare,
                'codeshare_info'           => [
                    'operating_airline_name'  => $isCodeshare ? ($this->airlineDbMap[$opCarrier]['name'] ?? $opName) : '',
                    'logo_path'               => $isCodeshare ? ($this->airlineDbMap[$opCarrier]['logo_path'] ?? $this->resolveLogoPath($opCarrier)) : '',
                    'operating_carrier'       => $opCarrier,
                    'operating_flight_number' => $opNumber,
                ],
                'booking_code'             => $bookingCode,
                'cabin_class'              => $cabinClass,
                'flightRef'                => $ref,
                'lastitem'                 => $isLast,

                'flight'                   => $carrier . ($flight['number'] ?? ''),
                'flightTime1'              => $this->formatDuration($leg['duration']),
                'originTerminal'           => $dep['terminal'] ?? '',
                'destinationTerminal'      => $arr['terminal'] ?? '',

                'Origin_Airport_Name'      => $depAirportName,
                'Origin_City_Name'         => $depCityName,
                'Destination_Airport_Name' => $arrAirportName,
                'Destination_City_Name'    => $arrCityName,
            ];
        }

        return $segments;
    }

    private function buildStops(array $segments): array
    {
        $stops = [];

        foreach ($segments as $i => $seg) {
            if ($i < count($segments) - 1) {
                $stops[] = [
                    'airport_code' => $seg['arrival_code'],
                    'airport_name' => $seg['Destination_Airport_Name'],
                    'city_name'    => $seg['Destination_City_Name'],
                    'layover_time' => $seg['layover_time'] ?? '',
                ];
            }
        }

        return $stops;
    }

    private function buildPriceBreakdown(array $breakdowns): array
    {
        $ptcMap = ['ADT' => 'Adult', 'CNN' => 'Child', 'CHD' => 'Child', 'INF' => 'Infant', 'INS' => 'Infant'];
        $result = [];

        foreach ($breakdowns as $bd) {
            $ptc    = $bd['requestedPassengerType'] ?? '';
            $amount = $bd['Amount'] ?? [];

            $result[] = [
                'type'              => $ptcMap[$ptc] ?? $ptc,
                'passengerTypeCode' => $ptc,
                'quantity'          => (int) ($bd['quantity'] ?? 1),
                'baseFare'          => (float) ($amount['Base'] ?? 0),
                'taxes'             => (float) ($amount['Taxes']['TotalTaxes'] ?? 0),
                'totalPrice'        => (float) ($amount['Total'] ?? 0),
            ];
        }

        return $result ?: [[
            'type' => 'Adult', 'passengerTypeCode' => 'ADT',
            'quantity' => 1, 'baseFare' => 0, 'taxes' => 0, 'totalPrice' => 0,
        ]];
    }

    private function isRefundable(?array $brand): bool
    {
        if (!$brand) {
            return false;
        }

        foreach ($brand['BrandAttribute'] ?? [] as $attr) {
            if (($attr['classification'] ?? '') === 'Refund') {
                return ($attr['inclusion'] ?? '') !== 'Chargeable';
            }
        }

        return false;
    }

    private function getRefundType(?array $brand): string
    {
        if (!$brand) {
            return 'non_refundable';
        }

        foreach ($brand['BrandAttribute'] ?? [] as $attr) {
            if (($attr['classification'] ?? '') === 'Refund') {
                // Mirror isRefundable: anything that is not 'Chargeable' = refundable
                return ($attr['inclusion'] ?? '') === 'Chargeable' ? 'partial' : 'refundable';
            }
        }

        return 'non_refundable';
    }

    private function extractBaggage(?array $tnc): array
    {
        if (!$tnc) {
            return [];
        }

        $checkedBags = [];
        $result      = [];

        foreach ($tnc['BaggageAllowance'] ?? [] as $bag) {
            $item     = $bag['BaggageItem'][0] ?? [];
            $rawType  = $bag['baggageType'] ?? '';
            $included = ($item['includedInOfferPrice'] ?? '') === 'Yes';
            $desc     = $item['Text'] ?? '';

            $weight = '';
            if (preg_match('/(\d+)\s*KG/i', $desc, $m)) {
                $weight = $m[1] . 'kg';
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
            } else {
                $result[] = [
                    'type'     => strtolower($rawType),
                    'label'    => $rawType,
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'weight'   => $weight,
                    'included' => $included,
                ];
            }
        }

        if (!empty($checkedBags)) {
            $qty    = count($checkedBags);
            $weight = $checkedBags[0]['weight'];
            array_unshift($result, [
                'type'     => 'checked',
                'label'    => 'Checking',
                'quantity' => $qty,
                'weight'   => $weight,
                'included' => $checkedBags[0]['included'],
            ]);
        }

        return $result;
    }

    private function resolveLogoPath(string $carrier): string
    {
        static $logoIndex = null;

        if ($logoIndex === null) {
            $logoIndex = [];
            $dir       = public_path('uploads/airlines');

            if (is_dir($dir)) {
                foreach (scandir($dir) as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }
                    $name             = pathinfo($file, PATHINFO_FILENAME);
                    $ext              = pathinfo($file, PATHINFO_EXTENSION);
                    $logoIndex[$name] = $ext;
                }
            }
        }

        if (isset($logoIndex[$carrier])) {
            return 'uploads/airlines/' . $carrier . '.' . $logoIndex[$carrier];
        }

        return 'uploads/airlines/default.svg';
    }

    private function formatDuration(string $iso): string
    {
        if (empty($iso)) {
            return '';
        }

        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?/', $iso, $m);
        $h   = (int) ($m[1] ?? 0);
        $min = (int) ($m[2] ?? 0);

        return $h . ' hr ' . str_pad((string) $min, 2, '0', STR_PAD_LEFT) . ' min';
    }

    private function formatTime(string $date, string $time): string
    {
        if (empty($time)) {
            return '';
        }

        $ts = strtotime($date . ' ' . $time);

        return $ts !== false ? date('h:i A', $ts) : $time;
    }
}
