<?php

namespace App\Http\Controllers\Admin\API;

use Exception;
use SimpleXMLElement;
use Illuminate\Support\Facades\DB;

class XmlToJson
{
    private $xml;
    private $segments = [];
    private $fareInfoCache = [];
    private $flightDetailsCache = [];
    private $currency;
    private $airlineNames = [];
    private $airportData = []; // New property for airport data
    private $aircraftTypes = []; // New property for aircraft types

    public function __construct($xmlContent)
    {
        $this->xml = new SimpleXMLElement($xmlContent);
        $this->xml->registerXPathNamespace('air', 'http://www.travelport.com/schema/air_v52_0');
        $this->currency = (string)$this->xml->xpath('//air:LowFareSearchRsp/@CurrencyType')[0] ?? 'USD';

        // Cache airline names and logo paths
        $airlines = DB::table('airline_logos')
            ->select('code', 'name', 'logo_path')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->code => [
                    'name' => $item->name,
                    'logo_path' => $item->logo_path
                ]];
            })
            ->toArray();

        // Cache airport data
        $airports = DB::table('airports')
            ->select('code', 'Airport_Name', 'City_Name')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->code => [
                    'airport_name' => $item->Airport_Name,
                    'city_name' => $item->City_Name
                ]];
            })
            ->toArray();

        // Cache aircraft types
        $this->aircraftTypes = DB::table('aircraft_type_designators')
            ->select('iata_code', 'model')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->iata_code => $item->model];
            })
            ->toArray();

        $this->airlineNames = $airlines;
        $this->airportData = $airports;

        $this->initializeCache();
    }

    private function initializeCache()
    {
        $fareInfos = $this->xml->xpath('//air:FareInfo');
        $flightDetails = $this->xml->xpath('//air:FlightDetailsList/air:FlightDetails');
        $segments = $this->xml->xpath('//air:AirSegment');

        // Cache all data in one pass
        foreach ($fareInfos as $fareInfo) {
            $this->cacheFareInfo($fareInfo);
        }
        foreach ($flightDetails as $detail) {
            $this->cacheFlightDetail($detail);
        }
        foreach ($segments as $segment) {
            $this->cacheSegment($segment);
        }
    }

    private function cacheFareInfo($fareInfo)
    {
        $key = (string)$fareInfo['Key'];
        $fareRuleKey = $fareInfo->xpath('.//air:FareRuleKey')[0] ?? null;

        $this->fareInfoCache[$key] = [
            'fareBasis' => (string)$fareInfo['FareBasis'],
            'passengerType' => (string)$fareInfo['PassengerTypeCode'] ?? 'ADT',
            'fareRuleKey' => [
                'fareinforef' => (string)($fareRuleKey['FareInfoRef'] ?? null),
                'providerCode' => (string)($fareRuleKey['ProviderCode'] ?? '1G'),
                'content' => (string)$fareRuleKey
            ]
        ];
    }

    private function cacheFlightDetail($detail)
    {
        $key = (string)$detail['Key'];
        $this->flightDetailsCache[$key] = [
            'originTerminal' => (string)$detail['OriginTerminal'],
            'destinationTerminal' => (string)$detail['DestinationTerminal']
        ];
    }

    private function cacheSegment($segment)
    {
        $key = (string)$segment['Key'];
        $flightDetailsKey = (string)($segment->xpath('./air:FlightDetailsRef/@Key')[0] ?? '');
        $flightDetails = $this->flightDetailsCache[$flightDetailsKey] ?? [];

        $this->segments[$key] = $this->parseSegment($segment, $flightDetails);
    }

    private function parseSegment($segment, $flightDetails)
    {
        // Helper function to format datetime
        $formatTime = function ($datetime) {
            return date('h:i A', strtotime($datetime));
        };

        $departureTime = strtotime((string)$segment['DepartureTime']);
        $arrivalTime = strtotime((string)$segment['ArrivalTime']);

        $carrierCode = (string)$segment['Carrier'];
        $airlineInfo = $this->airlineNames[$carrierCode] ?? [
            'name' => 'Unknown Airline',
            'logo_path' => 'uploads/airlines/default.svg'
        ];

        $originCode = (string)$segment['Origin'];
        $destinationCode = (string)$segment['Destination'];

        $originAirportInfo = $this->airportData[$originCode] ?? [
            'airport_name' => 'Unknown Airport',
            'city_name' => 'Unknown City'
        ];

        $destinationAirportInfo = $this->airportData[$destinationCode] ?? [
            'airport_name' => 'Unknown Airport',
            'city_name' => 'Unknown City'
        ];

        $equipmentCode = (string)$segment['Equipment'];
        $aircraftName = $this->aircraftTypes[$equipmentCode] ?? 'Unknown Aircraft';

        // Get codeshare information
        $codeshareInfo = null;
        $codeshareNode = $segment->xpath('./air:CodeshareInfo')[0] ?? null;
        if ($codeshareNode) {
            $codeshareInfo = [
                'operating_carrier' => (string)$codeshareNode['OperatingCarrier'],
                'operating_flight_number' => (string)$codeshareNode['OperatingFlightNumber'],
                'logo_path' => $this->airlineNames[(string)$codeshareNode['OperatingCarrier']]['logo_path'] ?? 'uploads/airlines/default.svg',
                'operating_airline_name' => $this->airlineNames[(string)$codeshareNode['OperatingCarrier']]['name'] ?? 'Unknown Airline'
            ];
        }

        return [
            'carrier_code' => $carrierCode,
            'airline_name' => $airlineInfo['name'],
            'logo_path' => $airlineInfo['logo_path'],
            'flightNumber' => (string)$segment['FlightNumber'],
            'flight' => (string)$segment['Carrier'] . (string)$segment['FlightNumber'],
            'departure_code' => $originCode,
            'arrival_code' => $destinationCode,
            'Origin_Airport_Name' => $originAirportInfo['airport_name'],
            'Origin_City_Name' => $originAirportInfo['city_name'],
            'Destination_Airport_Name' => $destinationAirportInfo['airport_name'],
            'Destination_City_Name' => $destinationAirportInfo['city_name'],
            'departure_time' => $formatTime((string)$segment['DepartureTime']),
            'arrival_time' => $formatTime((string)$segment['ArrivalTime']),
            'departure_date' => date('Y-m-d', $departureTime),
            'arrival_date' => date('Y-m-d', $arrivalTime),
            'flightTime' => (int)$segment['FlightTime'],
            'flightTime1' => $this->formatDuration((int)$segment['FlightTime']),
            'layover_time' => null,  // Will be calculated when segments are processed
            'originTerminal' => $flightDetails['originTerminal'] ?? '',
            'destinationTerminal' => $flightDetails['destinationTerminal'] ?? '',
            'booking_code' => '',
            'booking_count' => 0,
            'cabin_class' => '',
            'segmentKey' => '',
            'equipment' => $equipmentCode,
            'aircraft_name' => $aircraftName,
            // Add codeshare info
            'is_codeshare' => $codeshareInfo !== null,
            'codeshare_info' => $codeshareInfo,
        ];
    }

    private function processPricingSolution($solution)
    {
        $commonInfo = $this->processCommonInfo($solution);
        $segmentOrganizer = $this->processSegments($solution);

        return $this->buildJourney(
            $segmentOrganizer['outbound'],
            $segmentOrganizer['inbound'],
            $commonInfo,
            $segmentOrganizer['isReturn']
        );
    }

    private function processCommonInfo($solution)
    {
        // Get the solution key
        $solutionKey = (string)$solution['Key'];

        // Get baggage allowance
        $baggageAllowances = $this->getBaggageAllowances($solution);

        // Process connections first
        $connections = $this->processConnectionInfo($solution);

        $commonInfo = [
            'key' => $solutionKey,
            'totalPrice' => 0,
            'currency' => $this->currency,
            'refundable' => (string)($solution->xpath('.//air:AirPricingInfo/@Refundable')[0] ?? 'false') === 'true',
            'pricingMethod' => (string)($solution->xpath('.//air:AirPricingInfo/@PricingMethod')[0]) ?? 'Not Found',
            'priceBreakdown' => $this->calculatePriceBreakdown($solution),
            'baggage_allowance' => $baggageAllowances,
            'penalties' => $this->getPenalties($solution),
            'farerulekey' => $this->getFareRuleKeys($solution),
            'connections' => $connections // Add connections directly
        ];

        // Calculate total price
        $commonInfo['totalPrice'] = array_sum(array_column($commonInfo['priceBreakdown'], 'totalPrice'));

        return $commonInfo;
    }

    private function processConnectionInfo($solution, $isInbound = false)
    {
        $segmentRefs = $solution->xpath('.//air:AirSegmentRef');
        $segmentCount = count($segmentRefs);
        $midPoint = floor($segmentCount / 2);
        $isReturn = $this->isReturnJourney($segmentRefs);

        $currentSegments = [];
        $stops = [];

        // Collect segments
        foreach ($segmentRefs as $index => $segmentRef) {
            $segmentKey = (string)$segmentRef['Key'];
            if (!isset($this->segments[$segmentKey])) continue;
            $currentSegments[] = $this->segments[$segmentKey];
        }

        // Process either outbound or inbound segments based on $isInbound parameter
        if ($isReturn) {
            $segments = $isInbound
                ? array_slice($currentSegments, $midPoint)
                : array_slice($currentSegments, 0, $midPoint);
        } else {
            $segments = $currentSegments;
        }

        // Process connections between segments
        for ($i = 0; $i < count($segments) - 1; $i++) {
            $currentSegment = $segments[$i];
            $nextSegment = $segments[$i + 1];

            // Check if this is really a connection (same journey direction)
            if ($currentSegment['arrival_code'] === $nextSegment['departure_code']) {
                $connectionCode = $currentSegment['arrival_code'];
                $connectionInfo = $this->airportData[$connectionCode] ?? [
                    'airport_name' => 'Unknown Airport',
                    'city_name' => 'Unknown City'
                ];

                $currentArrival = strtotime($currentSegment['arrival_date'] . ' ' . $currentSegment['arrival_time']);
                $nextDeparture = strtotime($nextSegment['departure_date'] . ' ' . $nextSegment['departure_time']);

                $layoverMinutes = ($nextDeparture - $currentArrival) / 60;

                $stops[] = [
                    'airport_code' => $connectionCode,
                    'airport_name' => $connectionInfo['airport_name'],
                    'city_name' => $connectionInfo['city_name'],
                    'layover_time' => $this->formatDuration($layoverMinutes)
                ];
            }
        }

        return [
            'stops' => $stops
        ];
    }

    private function getBaggageAllowances($solution)
    {
        $baggageAllowances = [];

        // Get all AirPricingInfo elements for this solution
        $pricingInfos = $solution->xpath('.//air:AirPricingInfo');

        foreach ($pricingInfos as $pricingInfo) {
            $fareInfoRef = $pricingInfo->xpath('.//air:FareInfoRef')[0];

            if ($fareInfoRef) {
                $fareInfoKey = (string)$fareInfoRef['Key'];

                // Find matching FareInfo in FareInfoList using namespace
                $fareInfo = $this->xml->xpath("//air:FareInfo[@Key='$fareInfoKey']")[0];

                if ($fareInfo) {
                    $passengerTypeCode = (string)$fareInfo['PassengerTypeCode'];
                    $passengerType = $this->mapPassengerType($passengerTypeCode);

                    // Skip if already processed this passenger type
                    if (isset($baggageAllowances[$passengerType])) {
                        continue;
                    }

                    // Use xpath with namespace to get BaggageAllowance
                    $baggageAllowanceNodes = $fareInfo->xpath('.//air:BaggageAllowance');
                    if (!empty($baggageAllowanceNodes)) {
                        $baggageAllowance = $baggageAllowanceNodes[0];

                        // Get NumberOfPieces using xpath
                        $pieces = (int)($baggageAllowance->xpath('.//air:NumberOfPieces')[0] ?? 0);

                        // Get MaxWeight using xpath
                        $maxWeightNodes = $baggageAllowance->xpath('.//air:MaxWeight');
                        $maxWeight = '';

                        if (!empty($maxWeightNodes)) {
                            $maxWeightNode = $maxWeightNodes[0];
                            $value = (string)($maxWeightNode['Value'] ?? '');
                            $unit = (string)($maxWeightNode['Unit'] ?? '');
                            if ($value && $unit) {
                                $maxWeight = "$value $unit";
                            }
                        }

                        $baggageAllowances[$passengerType] = [
                            'type' => $passengerType,
                            'pieces' => $pieces,
                            'max_weight' => $maxWeight
                        ];
                    }
                }
            }
        }

        // Sort baggage allowances
        $typeOrder = ['Adult' => 1, 'Child' => 2, 'Infant' => 3];
        uksort($baggageAllowances, function($a, $b) use ($typeOrder) {
            return ($typeOrder[$a] ?? 999) - ($typeOrder[$b] ?? 999);
        });

        return array_values($baggageAllowances);
    }

    private function calculatePriceBreakdown($solution)
    {
        $priceBreakdown = [];

        foreach ($solution->xpath('.//air:AirPricingInfo') as $pricingInfo) {
            $fareInfoRef = (string)($pricingInfo->xpath('./air:FareInfoRef/@Key')[0] ?? '');
            $passengerType = $this->mapPassengerType($this->fareInfoCache[$fareInfoRef]['passengerType'] ?? 'ADT');
            $passengerCount = (int)($pricingInfo['NumberOfPassengers'] ?? 1);

            $baseAmount = $this->extractNumericValue((string)$pricingInfo['ApproximateBasePrice']);
            $taxAmount = $this->extractNumericValue((string)$pricingInfo['ApproximateTaxes']);
            $totalPrice = $this->extractNumericValue((string)$pricingInfo['ApproximateTotalPrice']);

            if (!isset($priceBreakdown[$passengerType])) {
                $priceBreakdown[$passengerType] = [
                    'type' => $passengerType,
                    'baseFare' => $baseAmount * $passengerCount,
                    'taxes' => $taxAmount * $passengerCount,
                    'totalPrice' => $totalPrice * $passengerCount
                ];
                continue;
            }

            $priceBreakdown[$passengerType]['baseFare'] += $baseAmount * $passengerCount;
            $priceBreakdown[$passengerType]['taxes'] += $taxAmount * $passengerCount;
            $priceBreakdown[$passengerType]['totalPrice'] += $totalPrice * $passengerCount;
        }

        return array_values($priceBreakdown);
    }

    private function processSegments($solution)
    {

        $segmentRefs = $solution->xpath('.//air:AirSegmentRef');
        $segmentCount = count($segmentRefs);
        $midPoint = floor($segmentCount / 2);
        $isReturn = $this->isReturnJourney($segmentRefs);

        $outboundSegments = [];
        $inboundSegments = [];

        // First pass: collect all segments
        $allSegments = [];
        foreach ($segmentRefs as $index => $segmentRef) {
            $segmentKey = (string)$segmentRef['Key'];
            if (!isset($this->segments[$segmentKey])) continue;

            $segment = $this->segments[$segmentKey];
            $segment = $this->enrichSegment($segment, $segmentKey, $solution);
            $segment['route_group'] = $segment['departure_code'] . '-' . $segment['arrival_code'];
            $allSegments[] = $segment;
        }

        // Split segments into outbound and inbound
        $outboundSegments = [];
        $inboundSegments = [];
        foreach ($allSegments as $index => $segment) {
            if ($isReturn && $index >= $midPoint) {
                $inboundSegments[] = $segment;
            } else {
                $outboundSegments[] = $segment;
            }
        }

        // Process outbound segments
        for ($i = 0; $i < count($outboundSegments) - 1; $i++) {
            $currentSegment = $outboundSegments[$i];
            $nextSegment = $outboundSegments[$i + 1];

            $currentArrival = strtotime($currentSegment['arrival_date'] . ' ' . $currentSegment['arrival_time']);
            $nextDeparture = strtotime($nextSegment['departure_date'] . ' ' . $nextSegment['departure_time']);

            $layoverMinutes = ($nextDeparture - $currentArrival) / 60;
            if ($layoverMinutes > 0) {
                $outboundSegments[$i]['layover_time'] = $this->formatDuration($layoverMinutes);
            }
        }

        // Process inbound segments if it's a return journey
        if ($isReturn) {
            for ($i = 0; $i< count($inboundSegments) - 1; $i++) {
                $currentSegment = $inboundSegments[$i];
                $nextSegment = $inboundSegments[$i + 1];

                $currentArrival = strtotime($currentSegment['arrival_date'] . ' ' . $currentSegment['arrival_time']);
                $nextDeparture = strtotime($nextSegment['departure_date'] . ' ' . $nextSegment['departure_time']);

                $layoverMinutes = ($nextDeparture - $currentArrival) / 60;
                if ($layoverMinutes > 0) {
                    $inboundSegments[$i]['layover_time'] = $this->formatDuration($layoverMinutes);
                }
            }
        }

        // Set last items and congratulatory messages
        if (!empty($outboundSegments)) {
            $lastOutboundIndex = count($outboundSegments) - 1;
            $outboundSegments[$lastOutboundIndex]['lastitem'] = true;
            $outboundSegments[$lastOutboundIndex]['layover_time'] = "Congrats you have reached destination";
        }

        if (!empty($inboundSegments)) {
            $lastInboundIndex = count($inboundSegments) - 1;
            $inboundSegments[$lastInboundIndex]['lastitem'] = true;
            $inboundSegments[$lastInboundIndex]['layover_time'] = "Congrats you have reached destination";
        }

        return [
            'outbound' => array_values($outboundSegments),
            'inbound' => array_values($inboundSegments),
            'isReturn' => $isReturn
        ];
    }

    private function enrichSegment($segment, $segmentKey, $solution)
    {
        $bookingInfo = $this->findBookingInfo($solution, $segmentKey);
        if ($bookingInfo) {
            $segment['booking_code'] = $bookingInfo['BookingCode'] ?? '';
            $segment['booking_count'] = (int)($bookingInfo['BookingCount'] ?? 0);
            $segment['cabin_class'] = $bookingInfo['CabinClass'] ?? '';
            $segment['segmentKey'] = $segmentKey;
        }
        return $segment;
    }

    private function buildJourney($outboundSegments, $inboundSegments, $commonInfo, $isReturn)
    {
        $solutionKey = $commonInfo['key'];
        $solution = $this->xml->xpath("//air:AirPricingSolution[@Key='$solutionKey']")[0] ?? null;

        $journey = [
            'all_segments' => [], // Add this new section
            'outbound' => array_merge(
                $this->buildJourneySection($outboundSegments, $commonInfo),
                ['connections' => ['count' => 0, 'stops' => []]]
            )
        ];

        if ($solution) {
            $journey['all_segments'] = $this->getAllSegmentsRaw($solution);
            $journey['outbound']['connections'] = $this->processConnectionInfo($solution, false);

            if ($isReturn) {
                $journey['inbound'] = array_merge(
                    $this->buildJourneySection($inboundSegments, $commonInfo),
                    ['connections' => $this->processConnectionInfo($solution, true)]
                );
            }
        }

        return $journey;
    }

    private function buildJourneySection($segments, $commonInfo)
    {
        if (empty($segments)) return null;

        $firstSegment = reset($segments);
        $lastSegment = end($segments);
        $totalSegments = count($segments);

        // Add lastitem flag to the last segment
        $segments[$totalSegments - 1]['lastitem'] = true;

        // Get carrier codes and info
        $firstCarrierCode = $firstSegment['carrier_code'];
        $lastCarrierCode = $lastSegment['carrier_code'];

        $firstAirlineInfo = $this->airlineNames[$firstCarrierCode] ?? [
            'name' => 'Unknown Airline',
            'logo_path' => 'uploads/airlines/default.svg'
        ];
        $lastAirlineInfo = $this->airlineNames[$lastCarrierCode] ?? [
            'name' => 'Unknown Airline',
            'logo_path' => 'uploads/airlines/default.svg'
        ];

        // Get origin and destination airport info
        $originCode = $firstSegment['departure_code'];
        $destinationCode = $lastSegment['arrival_code'];

        $originInfo = $this->airportData[$originCode] ?? [
            'airport_name' => 'Unknown Airport',
            'city_name' => 'Unknown City'
        ];

        $destinationInfo = $this->airportData[$destinationCode] ?? [
            'airport_name' => 'Unknown Airport',
            'city_name' => 'Unknown City'
        ];


        return array_merge([
            'origin' => $originCode,
            'destination' => $destinationCode,
            'origin_airport_name' => $originInfo['airport_name'],
            'origin_city_name' => $originInfo['city_name'],
            'destination_airport_name' => $destinationInfo['airport_name'],
            'destination_city_name' => $destinationInfo['city_name'],
            'departure_date' => $firstSegment['departure_date'],
            'departure_time' => $firstSegment['departure_time'],
            'arrival_date' => $lastSegment['arrival_date'],
            'arrival_time' => $lastSegment['arrival_time'],
            'first_carrier_code' => $firstCarrierCode,
            'first_airline_name' => $firstAirlineInfo['name'],
            'first_logo_path' => $firstAirlineInfo['logo_path'],
            'last_carrier_code' => $lastCarrierCode,
            'last_airline_name' => $lastAirlineInfo['name'],
            'last_logo_path' => $lastAirlineInfo['logo_path'],
            'total_flight_time' => $this->calculateTotalTravelTime($segments),
        ], $commonInfo, ['segments' => array_values($segments)]);
    }

    private function calculateTotalTravelTime($segments)
    {
        if (empty($segments)) return '0h 0m';

        $totalMinutes = 0;
        $prevArrivalTime = null;

        foreach ($segments as $segment) {
            $totalMinutes += $segment['flightTime'];

            if ($prevArrivalTime !== null) {
                $currentDeparture = strtotime($segment['departure_time']);
                $layoverMinutes = max(0, ($currentDeparture - $prevArrivalTime) / 60);
                $totalMinutes += $layoverMinutes;
            }

            $prevArrivalTime = strtotime($segment['arrival_time']);
        }

        return $this->formatDuration($totalMinutes);
    }

    private function formatDuration($minutes)
    {
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        // Use str_pad to ensure 2 digits for hours and minutes
        $paddedHours = str_pad($hours, 2, '0', STR_PAD_LEFT);
        $paddedMinutes = str_pad($remainingMinutes, 2, '0', STR_PAD_LEFT);
        return sprintf('%s hr %s Min', $paddedHours, $paddedMinutes);
    }

    //getPenalties
    private function getPenalties($solution)
    {
        $penalties = [
            'change' => ['applies' => 'Anytime', 'amount' => 0],
            'cancel' => ['applies' => 'Anytime', 'amount' => 0]
        ];

        $penaltyTypes = ['Change' => 'change', 'Cancel' => 'cancel'];
        foreach ($penaltyTypes as $type => $key) {
            $penalty = $solution->xpath(".//air:{$type}Penalty")[0] ?? null;
            if ($penalty) {
                $penalties[$key] = [
                    'applies' => (string)($penalty['PenaltyApplies'] ?? 'Anytime'),
                    'amount' => $this->extractNumericValue((string)($penalty->xpath('./air:Amount')[0] ?? 0))
                ];
            }
        }

        return $penalties;
    }

    private function extractNumericValue($value)
    {
        return (float)preg_replace('/[^0-9.]/', '', (string)($value ?? '0'));
    }

    private function mapPassengerType($code)
    {
        static $types = [
            'ADT' => 'Adult',
            'CHD' => 'Child',
            'INF' => 'Infant',
            'CNN' => 'Child',
            'INS' => 'Infant',
            'YTH' => 'Youth',
            'SNR' => 'Senior',
            'STU' => 'Student'
        ];
        return $types[strtoupper($code)] ?? $code;
    }

    private function isReturnJourney($segmentRefs)
    {
        if (count($segmentRefs) <= 1) return false;

        $firstSegmentKey = (string)$segmentRefs[0]['Key'];
        $lastSegmentKey = (string)$segmentRefs[count($segmentRefs) - 1]['Key'];

        $firstSegment = $this->segments[$firstSegmentKey] ?? null;
        $lastSegment = $this->segments[$lastSegmentKey] ?? null;

        return $firstSegment && $lastSegment &&
            $firstSegment['departure_code'] === $lastSegment['arrival_code'];
    }

    private function findBookingInfo($solution, $segmentKey)
    {
        static $bookingInfoCache = [];

        if (!isset($bookingInfoCache[$segmentKey])) {
            foreach ($solution->xpath('.//air:AirPricingInfo/air:BookingInfo') as $bookingInfo) {
                if ((string)$bookingInfo['SegmentRef'] === $segmentKey) {
                    $bookingInfoCache[$segmentKey] = [
                        'BookingCode' => (string)$bookingInfo['BookingCode'],
                        'BookingCount' => (string)$bookingInfo['BookingCount'],
                        'CabinClass' => (string)$bookingInfo['CabinClass']
                    ];
                    break;
                }
            }
        }

        return $bookingInfoCache[$segmentKey] ?? null;
    }

    private function getFareRuleKeys($solution)
    {
        $fareRuleKeys = [];

        // Get all FareInfo elements associated with this pricing solution
        $fareInfoRefs = $solution->xpath('.//air:AirPricingInfo/air:FareInfoRef');
        foreach ($fareInfoRefs as $fareInfoRef) {
            $fareInfoKey = (string)$fareInfoRef['Key'];

            // Find the associated FareInfo element using the Key
            $fareInfo = $this->xml->xpath("//air:FareInfo[@Key='$fareInfoKey']")[0] ?? null;
            if ($fareInfo) {
                $origin = (string)$fareInfo['Origin'];
                $destination = (string)$fareInfo['Destination'];
                $routeKey = $origin . '-' . $destination;

                // Get FareRuleKey element
                $fareRuleKey = $fareInfo->xpath('.//air:FareRuleKey')[0] ?? null;
                if ($fareRuleKey) {
                    $fareRuleKeys[$routeKey] = [
                        'FareInfoRef' => (string)$fareRuleKey['FareInfoRef'],
                        'ProviderCode' => (string)$fareRuleKey['ProviderCode'],
                        'content' => (string)$fareRuleKey
                    ];
                }
            }
        }

        return $fareRuleKeys;
    }

    public function parse()
    {
        $results = [];
        foreach ($this->xml->xpath('//air:AirPricingSolution') as $solution) {
            $results[] = $this->processPricingSolution($solution);
        }
        return ['flights' => $results];
    }

    private function getAllSegmentsRaw($solution)
    {
        $allSegments = [];
        $segmentRefs = $solution->xpath('.//air:AirSegmentRef');

        foreach ($segmentRefs as $segmentRef) {
            $segmentKey = (string)$segmentRef['Key'];
            $segment = $this->xml->xpath("//air:AirSegment[@Key='$segmentKey']")[0] ?? null;

            if ($segment) {
                $attributes = $segment->attributes();

                // Get all attributes directly
                $segmentData = [];
                foreach ($attributes as $key => $value) {
                    $segmentData[$key] = (string)$value;
                }

                // Add codeshare info if exists
                $codeshareInfo = $segment->xpath('./air:CodeshareInfo')[0] ?? null;
                if ($codeshareInfo) {
                    $codeshareAttributes = $codeshareInfo->attributes();
                    foreach ($codeshareAttributes as $key => $value) {
                        $segmentData[$key] = (string)$value;
                    }
                }

                $allSegments[] = $segmentData;
            }
        }

        return $allSegments;
    }

}
