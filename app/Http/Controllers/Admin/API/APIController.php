<?php

namespace App\Http\Controllers\Admin\API;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Admin\API\XmlToJson;
use App\Http\Controllers\Admin\API\farerulsreq;

class APIController extends BaseController
{

    public function Lowfaresearch( Request $request ) {

        // dd( $request->all() );
        $request->validate( [
            'from' => 'required',
            'to' => 'required',
        ] );

        $requestXML = new RequestXML();
        $xmlpayload = $requestXML->generateLowFareSearchXML( $request );

        // dd( $xmlpayload );

        $array = $this->performCurlRequest( $xmlpayload );

        header( 'Content-Type: application/json' );
        echo json_encode( $array, JSON_PRETTY_PRINT );

    }

    public function performCurlRequest( $xmlpayload ) {

        $username = env( 'API_USERNAME' );
        $password = env( 'API_PASSWORD' );

        $start_time = microtime( true );
        $ch = curl_init();
        // Set cURL options
        curl_setopt_array( $ch, [
            CURLOPT_URL => 'https://apac.universal-api.pp.travelport.com/B2BGateway/connect/uAPI/AirService',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xmlpayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml;charset=UTF-8',
            ],
            CURLOPT_USERPWD => "$username:$password",
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_RETURNTRANSFER => true,
        ] );

        // Execute and fetch the response
        $response = curl_exec( $ch );

        // Check for errors
        if ( curl_errno( $ch ) ) {
            $error = curl_error( $ch );
            curl_close( $ch );
            return response()->json( [ 'error' => $error ] );
        }

        curl_close( $ch );

        $converter = new XmlToJson($response);
        $result = $converter->parse();
        return $result;

    }

    // public function Lowfaresearch(Request $request)
    // {

    //     try {
    //         // Read the local XML file

    //         $xmlContent = file_get_contents(__DIR__ . '/all.xml');

    //         // Create XML to JSON converter
    //         $startTime = microtime(true);
    //         $converter = new XmlToJson($xmlContent);
    //         $result = $converter->parse();
    //         $endTime = microtime(true);
    //         $executionTime = ($endTime - $startTime);
    //         Log::info("XML to Json: " . $executionTime . " seconds");

    //         // Return JSON response
    //         return response()->json($result);
    //     } catch (Exception $e) {
    //         Log::error('Error processing XML:', [
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         return response()->json([
    //             'error' => 'Error processing request',
    //             'details' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function getFareRules(Request $request)
    {
        try {
            // Get the fare rules data from request
            $fareRulesData = $request->all();

            // Create farerulsreq instance and get XML
            $farerulsreq = new farerulsreq();
            $xml = $farerulsreq->generateAirFareRulesXML($fareRulesData);

            // Make API call and get response
            $response = $this->FareRulsCurlRequest($xml);

            // Convert XML to SimpleXMLElement
            $xmlObj = new \SimpleXMLElement($response);

            // Register namespaces
            $xmlObj->registerXPathNamespace('SOAP', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xmlObj->registerXPathNamespace('air', 'http://www.travelport.com/schema/air_v52_0');

            // Get all FareRule elements with complete path
            $fareRules = $xmlObj->xpath('//SOAP:Envelope/SOAP:Body/air:AirFareRulesRsp/air:FareRule');

            $result = [];

            foreach ($fareRules as $fareRule) {
                $fareInfoRef = (string)$fareRule['FareInfoRef'];

                // Find corresponding segment
                $segmentKey = array_key_first(array_filter($fareRulesData, function($data) use ($fareInfoRef) {
                    return $data['FareInfoRef'] === $fareInfoRef;
                }));

                if ($segmentKey) {
                    $rules = [];
                    $tempRules = [];

                    // Get all FareRuleLong elements for this FareRule
                    $fareRuleLongs = $fareRule->xpath('.//air:FareRuleLong');

                    foreach ($fareRuleLongs as $ruleLong) {
                        $categoryNumber = (string)$ruleLong['Category'];

                        // Get the text content directly
                        $content = (string)$ruleLong;

                        // If direct approach doesn't work, try getting CDATA
                        if (empty($content)) {
                            $domNode = dom_import_simplexml($ruleLong);
                            foreach ($domNode->childNodes as $node) {
                                if ($node->nodeType === XML_CDATA_SECTION_NODE) {
                                    $content = $node->nodeValue;
                                    break;
                                }
                            }
                        }

                        // Clean up the content
                        $content = trim(preg_replace(['/\s+/', '/[\r\n]+/'], ' ', $content));

                        // Store rules with numeric keys for proper sorting
                        $tempRules[(int)$categoryNumber] = [
                            'category' => $categoryNumber,
                            'content' => $content
                        ];
                    }

                    // Sort by category number
                    ksort($tempRules);

                    // Convert to final format with category names
                    foreach ($tempRules as $categoryNumber => $ruleData) {
                        $categoryName = $this->getCategoryName((string)$categoryNumber);
                        $rules[$categoryName] = $ruleData;
                    }

                    $result[$segmentKey] = [
                        'rules' => $rules
                    ];
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => $result
            ]);

        } catch (Exception $e) {
            Log::error('Error processing fare rules:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error processing fare rules',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    private function getCategoryName($category) {
        $categories = [
            '1' => 'ELIGIBILITY',
            '2' => 'DAY_TIME',
            '3' => 'SEASONALITY',
            '4' => 'FLIGHT_APPLICATION',
            '5' => 'ADVANCE_RESERVATIONS_TICKETING',
            '6' => 'MINIMUM_STAY',
            '7' => 'MAXIMUM_STAY',
            '8' => 'STOPOVERS',
            '9' => 'TRANSFERS',
            '10' => 'COMBINATIONS',
            '11' => 'BLACKOUT_DATES',
            '12' => 'SURCHARGES',
            '13' => 'ACCOMPANIED_TRAVEL',
            '14' => 'TRAVEL_RESTRICTIONS',
            '15' => 'SALES_RESTRICTIONS',
            '16' => 'PENALTIES',
            '17' => 'HIP_MILEAGE_EXCEPTIONS',
            '18' => 'TICKET_ENDORSEMENTS',
            '19' => 'CHILDREN_DISCOUNTS',
            '20' => 'TOUR_CONDUCTOR_DISCOUNTS',
            '21' => 'AGENT_DISCOUNTS',
            '22' => 'ALL_OTHER_DISCOUNTS',
            '23' => 'MISCELLANEOUS_PROVISIONS',
            '24' => 'NOT_APPLICABLE',
            '25' => 'FARE_BY_RULE',
            '26' => 'GROUPS',
            '27' => 'TOURS',
            '28' => 'VISIT_ANOTHER_COUNTRY',
            '29' => 'DEPOSITS',
            '31' => 'VOLUNTARY_CHANGES',
            '33' => 'VOLUNTARY_REFUNDS',
            '35' => 'NEGOTIATED_FARE_RESTRICTIONS',
            '50' => 'APPLICATION_AND_OTHER_CONDITIONS'
        ];

        return $categories[$category] ?? 'CATEGORY_' . $category;
    }

    public function FareRulsCurlRequest($xmlpayload)
    {

        $username = env('API_USERNAME');
        $password = env('API_PASSWORD');

        $ch = curl_init();
        // Set cURL options
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://apac.universal-api.pp.travelport.com/B2BGateway/connect/uAPI/AirService',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xmlpayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml;charset=UTF-8',
            ],
            CURLOPT_USERPWD => "$username:$password",
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_RETURNTRANSFER => true,
        ]);

        // Execute and fetch the response
        $response = curl_exec($ch);

        // Check for errors
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return response()->json(['error' => $error]);
        }

        curl_close($ch);

        return $response;

    }

    public function PricingRequestBody(Request $request)
    {
        dd($request->all());
    }
}
