<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\BookingAttempt;
use App\Http\Controllers\BaseController;
use App\Models\BookingPriceLog;
use App\Models\Deposit\Deposit;
use Carbon\Carbon;
use App\Models\BookingSearchLog;
use App\Models\Helpdesk\Request as HelpdeskRequest;
use App\Models\Agent\Agent;
use App\Models\User;
use App\Models\BookingPax;
use App\Models\AirlineLogo\AirlineLogo;

class DashboardController extends Controller
{
    public function getDashboardStats(Request $request)
    {
        $user = $request->user();
        $agencyId = $user->agent_id ?? Agent::where('user_id', $user->id)->value('id');
        $query = BookingAttempt::query();
        $agencyUserIds = [];


        // if ($agencyId) {
        //     // Get all user IDs under this agency (staff users + agency owner)
        //     $agencyUserIds = User::where('agent_id', $agencyId)->pluck('id')->toArray();

        //     $ownerId = Agent::where('id', $agencyId)->value('user_id');
        //     if ($ownerId) {
        //         $agencyUserIds[] = (int) $ownerId;
        //     }
        //     $query->whereIn('user_id', array_unique(array_filter($agencyUserIds)));
        // }

        if ($agencyId) {
            // Get all user IDs under this agency (staff users + agency owner)
            $agencyUserIds = User::where('agent_id', $agencyId)->pluck('id')->toArray();
            $ownerId = Agent::where('id', $agencyId)->value('user_id');
            if ($ownerId) {
                $agencyUserIds[] = (int) $ownerId;
            }
            $agencyUserIds = array_unique(array_filter($agencyUserIds));

            $query->whereIn('user_id', $agencyUserIds);
        }

        //booking stats
        $totalBookings = (clone $query)
            ->where(function ($q) {
                $q->where('status', 'confirmed')
                    ->where('last_api_status', 'success')
                    ->orWhereIn('status', [
                        'committed',
                        'ticketing',
                        'ticketed',
                        'cancelled',
                        'voided',
                    ]);
            })
            ->count();

        $confirmedBookings = (clone $query)
            ->where(function ($q) {
                $q->where('status', 'confirmed')
                    ->where('last_api_status', 'success')
                    ->orWhereIn('status', [
                        'committed',
                        'ticketing',
                        'ticketed',
                        'voided',
                    ]);
            })
            ->count();

        $cancelledBookings = (clone $query)
            ->where('status', 'cancelled')
            ->count();

        $todayBookings = (clone $query)
            ->where(function ($q) {
                $q->where('status', 'confirmed')
                    ->where('last_api_status', 'success')
                    ->orWhereIn('status', [
                        'committed',
                        'ticketing',
                        'ticketed',
                        'voided',
                        'cancelled'
                    ]);
            })
            ->whereDate('created_at', Carbon::today())
            ->count();

        $yesterdayBookings = (clone $query)
            ->where(function ($q) {
                $q->where('status', 'confirmed')
                    ->where('last_api_status', 'success')
                    ->orWhereIn('status', [
                        'committed',
                        'ticketing',
                        'ticketed',
                        'voided',
                        'cancelled'
                    ]);
            })
            ->whereDate('created_at', Carbon::yesterday())
            ->count();

        $bookingRatio = $this->calculateRatio($todayBookings, $yesterdayBookings);

        //ticketing stats
        $totalTicketing = (clone $query)
            ->whereIn('status', ['ticketed', 'voided'])
            ->count();

        $ticketed = (clone $query)
            ->whereIn('status', ['ticketed'])
            ->count();

        $voided = (clone $query)
            ->whereIn('status', ['voided'])
            ->count();

        $todayTicketing = (clone $query)
            ->whereIn('status', ['ticketed', 'voided'])
            ->whereDate('created_at', Carbon::today())
            ->count();

        $yesterdayTicketing = (clone $query)
            ->whereIn('status', ['ticketed', 'voided'])
            ->whereDate('created_at', Carbon::yesterday())
            ->count();

        $ticketingRatio = $this->calculateRatio($todayTicketing, $yesterdayTicketing);


        $monthlySalesQuery = BookingPriceLog::join('booking_attempts', 'booking_price_logs.booking_attempt_id', '=', 'booking_attempts.id')
            ->whereIn('booking_attempts.status', ['ticketed', 'voided'])
            ->whereYear('booking_attempts.ticketed_at', Carbon::now()->year);

        if (!empty($agencyUserIds)) {
            $monthlySalesQuery->whereIn('booking_attempts.user_id', $agencyUserIds);
        }
        $monthlySales = $monthlySalesQuery
            ->selectRaw('MONTH(booking_attempts.ticketed_at) as month, SUM(booking_price_logs.total_price) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->toArray();
        $salesByMonth = [];
        for ($m = 1; $m <= 12; $m++) {
            $salesByMonth[] = $monthlySales[$m] ?? 0;
        }

        // Build the base query for trending routes
        $trendingRoutesQuery = BookingSearchLog::whereYear('created_at', Carbon::now()->year)
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereNotNull('from_airport')
            ->whereNotNull('to_airport')
            ->where('from_airport', '!=', '')
            ->where('to_airport', '!=', '');

        // Filter by the logged-in agency's user IDs (reusing $agencyUserIds)
        if (!empty($agencyUserIds)) {
            $trendingRoutesQuery->whereIn('user_id', $agencyUserIds);
        }

        // Execute the grouped count query
        $trendingRoutesRaw = $trendingRoutesQuery
            ->selectRaw("CONCAT(from_airport, '-', to_airport) as code, COUNT(*) as count")
            ->groupBy('code')
            ->orderByDesc('count')
            ->limit(7)
            ->get();

        // Find highest count for scaling progress bars
        $maxCount = $trendingRoutesRaw->max('count') ?: 1;

        // Color palettes
        $palette = ['#93c5fd', '#6ee7b7', '#c4b5fd', '#f9a8d4', '#fcd34d', '#67e8f9', '#86efac'];
        $paletteText = ['#0b66ce', '#047045', '#4528ba', '#c41977', '#917108', '#048495', '#045e25'];

        // Map results
        $trendingRoutes = $trendingRoutesRaw->map(function ($route, $index) use ($maxCount, $palette, $paletteText) {
            return [
                'code'      => $route->code,
                'count'     => (int) $route->count,
                'color'     => $palette[$index % count($palette)],
                'textColor' => $paletteText[$index % count($paletteText)],
                'max'       => (int) $maxCount,
            ];
        })->values()->toArray();


        // Calculate traveler counts filtered by agency user IDs
        $travelerStats = BookingPax::join('booking_attempts', 'booking_paxes.booking_attempt_id', '=', 'booking_attempts.id')
            ->where(function ($q) {
                $q->where('booking_attempts.status', 'confirmed')
                    ->where('booking_attempts.last_api_status', 'success')
                    ->orWhereIn('booking_attempts.status', [
                        'committed',
                        'ticketing',
                        'ticketed',
                        'voided',
                    ]);
            })
            ->when(!empty($agencyUserIds), function ($q) use ($agencyUserIds) {
                $q->whereIn('booking_attempts.user_id', $agencyUserIds);
            })
            ->selectRaw("
                SUM(CASE WHEN booking_paxes.pax_type = 'ADT' THEN 1 ELSE 0 END) as adult,
                SUM(CASE WHEN booking_paxes.pax_type IN ('CNN', 'CHD') THEN 1 ELSE 0 END) as children,
                SUM(CASE WHEN booking_paxes.pax_type = 'INF' THEN 1 ELSE 0 END) as infant")
            ->first();


        $travelerData = [
            (int) ($travelerStats->adult ?? 0),
            (int) ($travelerStats->children ?? 0),
            (int) ($travelerStats->infant ?? 0),
        ];


        // trending booking class
        $cabinClassStats = BookingAttempt::whereNotNull('cabin_class')
            ->where('cabin_class', '!=', '')
            ->when(!empty($agencyUserIds), function ($q) use ($agencyUserIds) {
                $q->whereIn('user_id', $agencyUserIds);
            })
            ->selectRaw("
                SUM(CASE WHEN LOWER(cabin_class) LIKE '%economy%' AND LOWER(cabin_class) NOT LIKE '%premium%' THEN 1 ELSE 0 END) as economy,
                SUM(CASE WHEN LOWER(cabin_class) LIKE '%premium%' THEN 1 ELSE 0 END) as premium_economy,
                SUM(CASE WHEN LOWER(cabin_class) LIKE '%business%' THEN 1 ELSE 0 END) as business,
                SUM(CASE WHEN LOWER(cabin_class) LIKE '%first%' THEN 1 ELSE 0 END) as first_class")
            ->first();
        $totalClass = ($cabinClassStats->economy + $cabinClassStats->premium_economy + $cabinClassStats->business + $cabinClassStats->first_class) ?: 1;

        // Calculate percentages for the pie chart
        $bookingClassData = [
            round((($cabinClassStats->economy ?? 0) / $totalClass) * 100, 1),
            round((($cabinClassStats->premium_economy ?? 0) / $totalClass) * 100, 1),
            round((($cabinClassStats->business ?? 0) / $totalClass) * 100, 1),
            round((($cabinClassStats->first_class ?? 0) / $totalClass) * 100, 1),
        ];

        // Monthly Bookings Count
        $monthlyBookingsRaw = BookingAttempt::whereYear('created_at', Carbon::now()->year)
            ->where(function ($q) {
                $q->where('status', 'confirmed')
                    ->where('last_api_status', 'success')
                    ->orWhereIn('booking_attempts.status', [
                        'committed',
                        'ticketing',
                        'ticketed',
                        'cancelled',
                        'voided'
                    ]);
            })
            // ->whereIn('status', ['confirmed', 'committed', 'ticketing', 'ticketed', 'cancelled', 'voided'])
            // ->where('last_api_status', 'success')
            ->when(!empty($agencyUserIds), function ($q) use ($agencyUserIds) {
                $q->whereIn('user_id', $agencyUserIds);
            })
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->groupBy('month')
            ->pluck('count', 'month')
            ->toArray();
        $monthlyBookings = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyBookings[] = (int) ($monthlyBookingsRaw[$m] ?? 0);
        }

        // Monthly Ticketing Count
        $monthlyTicketingRaw = BookingAttempt::whereYear('ticketed_at', Carbon::now()->year)
            ->whereIn('status', ['ticketed', 'voided'])
            ->when(!empty($agencyUserIds), function ($q) use ($agencyUserIds) {
                $q->whereIn('user_id', $agencyUserIds);
            })
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->groupBy('month')
            ->pluck('count', 'month')
            ->toArray();

        $monthlyTicketing = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyTicketing[] = (int) ($monthlyTicketingRaw[$m] ?? 0);
        }

        // Calculate Monthly Deposits
        $monthlyDepositRaw = Deposit::whereYear('updated_at', Carbon::now()->year)
            ->where('status', 'Approved')
            ->where('type', '!=', 'Credit Request')
            ->when($agencyId, function ($q) use ($agencyId) {
                $q->where('agent_id', $agencyId);
            })
            ->selectRaw('MONTH(created_at) as month, SUM(total) as total')
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();

        $depositData = [];
        for ($m = 1; $m <= 12; $m++) {
            $depositData[] = $monthlyDepositRaw[$m] ?? 0;
        }

        // Calculate Monthly Credit Requests
        $monthlyCreditRaw = Deposit::whereYear('updated_at', Carbon::now()->year)
            ->where('status', 'Approved')
            ->where('type', 'Credit Request')
            ->when($agencyId, function ($q) use ($agencyId) {
                $q->where('agent_id', $agencyId);
            })
            ->selectRaw('MONTH(created_at) as month, SUM(total) as total')
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();

        $creditData = [];
        for ($m = 1; $m <= 12; $m++) {
            $creditData[] = $monthlyCreditRaw[$m] ?? 0;
        }



        $currentYear  = Carbon::now()->year;
        $currentMonth = Carbon::now()->month;

        // Monthly Top 10 Airlines
        $monthlyAirlinesQuery = BookingAttempt::whereIn('booking_attempts.status', ['ticketed', 'voided'])
            ->whereYear('booking_attempts.created_at', $currentYear)
            ->whereMonth('booking_attempts.created_at', $currentMonth);
        if (!empty($agencyUserIds)) {
            $monthlyAirlinesQuery->whereIn('booking_attempts.user_id', $agencyUserIds);
        }
        $monthlyAirlines = $monthlyAirlinesQuery
            ->selectRaw('booking_attempts.airline_name, COUNT(*) as ticketing, COALESCE(SUM(bpl.total_price), 0) as sales')
            ->leftJoin('booking_price_logs as bpl', 'bpl.booking_attempt_id', '=', 'booking_attempts.id')
            ->groupBy('booking_attempts.airline_name')
            ->orderByDesc('ticketing')
            ->limit(10)
            ->get();

        // Today's Airline Rankings
        $todayRankingQuery = BookingAttempt::whereIn('booking_attempts.status', ['ticketed', 'voided'])
            ->whereDate('booking_attempts.created_at', Carbon::today());
        if (!empty($agencyUserIds)) {
            $todayRankingQuery->whereIn('booking_attempts.user_id', $agencyUserIds);
        }

        $todayRanking = $todayRankingQuery
            ->selectRaw('booking_attempts.airline_name, COUNT(*) as ticketing')
            ->groupBy('booking_attempts.airline_name')
            ->orderByDesc('ticketing')
            ->pluck('airline_name')
            ->toArray();

        // Yesterday's Airline Rankings
        $yesterdayRankingQuery = BookingAttempt::whereIn('booking_attempts.status', ['ticketed', 'voided'])
            ->whereDate('booking_attempts.created_at', Carbon::yesterday());
        if (!empty($agencyUserIds)) {
            $yesterdayRankingQuery->whereIn('booking_attempts.user_id', $agencyUserIds);
        }
        $yesterdayRanking = $yesterdayRankingQuery
            ->selectRaw('booking_attempts.airline_name, COUNT(*) as ticketing')
            ->groupBy('booking_attempts.airline_name')
            ->orderByDesc('ticketing')
            ->pluck('airline_name')
            ->toArray();

        // Combine Monthly Data with Today vs Yesterday Trend Comparison
        $topAirlines = $monthlyAirlines->map(function ($airline) use ($todayRanking, $yesterdayRanking) {
            $todayRank     = array_search($airline->airline_name, $todayRanking);
            $yesterdayRank = array_search($airline->airline_name, $yesterdayRanking);
            if ($todayRank === false) {
                $trend = 'same'; // No activity today
            } elseif ($yesterdayRank === false) {
                $trend = 'up';   // Active today but wasn't active yesterday
            } elseif ($todayRank < $yesterdayRank) {
                $trend = 'up';   // Rank improved today (e.g. rank 0 today vs rank 2 yesterday)
            } elseif ($todayRank > $yesterdayRank) {
                $trend = 'down'; // Rank dropped today (e.g. rank 3 today vs rank 1 yesterday)
            } else {
                $trend = 'same'; // Maintained exact same rank
            }
            return [
                'name'      => $airline->airline_name,
                'ticketing' => (int) $airline->ticketing,
                'sales'     => number_format($airline->sales, 0, '.', ','),
                'trend'     => $trend,
            ];
        })->values()->toArray();

        // Monthly Search
        $monthlySearchQuery = BookingSearchLog::whereYear('created_at', Carbon::now()->year);

        if (!empty($agencyUserIds)) {
            $monthlySearchQuery->whereIn('user_id', $agencyUserIds);
        }

        $monthlySearchRaw = $monthlySearchQuery
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as total')
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();

        // Fill all 12 months, missing months = 0
        $monthlySearch = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlySearch[] = (int) ($monthlySearchRaw[$m] ?? 0);
        }


        // Fetch pending/active bookings requiring ticketing
        $ticketingBookingsRaw = BookingAttempt::with(['paxes', 'searchLog', 'commitSession'])
            ->where(function ($q) {
                $q->where('status', 'confirmed')
                    ->where('last_api_status', 'success')
                    ->orWhereIn('booking_attempts.status', [
                        'committed',
                        'ticketing',
                    ]);
            })
            // ->whereIn('status', ['confirmed', 'committed', 'ticketing'])
            ->when(!empty($agencyUserIds), function ($q) use ($agencyUserIds) {
                $q->whereIn('user_id', $agencyUserIds);
            })
            ->where('last_api_status', 'success')
            ->orderByDesc('id')
            ->get();

        $ticketingBookings = $ticketingBookingsRaw->map(function ($booking) {
            // Primary Passenger details
            $pax = $booking->paxes->first();
            $primaryPassenger = $pax ? trim("{$pax->first_name} {$pax->last_name}") : 'N/A';
            $contact = $pax?->phone ?? '';

            // Extract Last Ticketing Time from commitSession payload or snapshot
            $payload = $booking->commitSession?->response_payload;
            $terms = data_get($payload, 'ReservationResponse.Reservation.Offer.0.TermsAndConditionsFull', []);

            $lastTicketingTime = null;
            if (is_array($terms)) {
                foreach ($terms as $term) {
                    if (!empty($term['PaymentTimeLimit'])) {
                        $lastTicketingTime = (string) $term['PaymentTimeLimit'];
                        break;
                    }
                    if (!empty($term['ExpiryDate'])) {
                        $lastTicketingTime = (string) $term['ExpiryDate'];
                        break;
                    }
                }
            }

            // Fallback if payload limit is absent
            if (!$lastTicketingTime) {
                $lastTicketingTime = $booking->post_commit_snapshot_json['ticket_time_limit']
                    ?? $booking->snapshot_json['ticket_time_limit']
                    ?? optional($booking->created_at)->addHours(24)->toIso8601String();
            }

            // Route formatting
            $from = $booking->searchLog->from_airport ?? '';
            $to   = $booking->searchLog->to_airport ?? '';
            $route = ($from && $to) ? "{$from} → {$to}" : 'N/A';

            return [
                'flightPnr'         => $booking->airline_pnr ?? $booking->pnr ?? 'N/A',
                'gdsPnr'            => $booking->gds_pnr ?? 'N/A',
                'airline'           => $booking->airline_name ?? 'N/A',
                'airlineCode'       => $booking->airline_code ?? '',
                'route'             => $route,
                'cabinClass'        => $booking->cabin_class ?? 'Economy',
                'totalPax'          => $booking->paxes->count(),
                'primaryPassenger'  => $primaryPassenger,
                'contact'           => $contact,
                'lastTicketingTime' => $lastTicketingTime,
            ];
        })->toArray();

        /*upcoming departure*/

        // Define 15-day departure date range
        $today    = Carbon::today();
        $in15Days = Carbon::today()->addDays(15);

        $airlineLogos = AirlineLogo::whereNotNull('logo_path')
            ->pluck('logo_path', 'code')
            ->toArray();

        // Eager load commitSession alongside paxes & searchLog
        $upcomingBookings = BookingAttempt::with(['paxes', 'searchLog', 'commitSession'])
            ->whereIn('status', ['ticketed'])
            ->when(!empty($agencyUserIds), function ($q) use ($agencyUserIds) {
                $q->whereIn('user_id', $agencyUserIds);
            })
            ->whereHas('searchLog', function ($q) use ($today, $in15Days) {
                $q->whereBetween('dep_date', [$today, $in15Days]);
            })
            ->get();

        // Group bookings by departure date (Y-m-d)
        $groupedByDate = $upcomingBookings->groupBy(function ($booking) {
            return optional($booking->searchLog->dep_date)->format('Y-m-d');
        })->filter(fn($group, $key) => !empty($key));

        $upcomingTravelDates = [];
        $flightDetailsByDate = [];
        foreach ($groupedByDate as $dateStr => $bookingsForDate) {
            $formattedDisplayDate = Carbon::parse($dateStr)->format('d M Y'); // e.g. "14 Aug 2026"

            // Group bookings on this date by actual Flight Number
            $flightsGrouped = $bookingsForDate->groupBy(function ($booking) {
                return $this->extractFlightNumber($booking);
            });

            $totalPassengersOnDate = 0;
            $flightsDetailList = [];
            foreach ($flightsGrouped as $flightNo => $flightBookings) {
                $firstBooking = $flightBookings->first();
                $paxCount = $flightBookings->sum(fn($b) => $b->paxes->count());
                $totalPassengersOnDate += $paxCount;
                $from = $firstBooking->searchLog->from_airport ?? '';
                $to   = $firstBooking->searchLog->to_airport ?? '';

                $airlineCode = $firstBooking->airline_code;
                $logoPath = $airlineLogos[$airlineCode] ?? null;
                $logoUrl = $logoPath ? asset($logoPath) : null;


                // Passenger list for this flight number
                $bookingPassengerList = $flightBookings->map(function ($b) {
                    $pax = $b->paxes->first();
                    return [
                        'primaryPassenger' => $pax ? trim("{$pax->first_name} {$pax->last_name}") : 'N/A',
                        'contact'          => $pax?->phone ?? '',
                    ];
                })->values()->toArray();
                $flightsDetailList[] = [
                    'flightNumber'   => $flightNo, // Actual flight number (e.g. "BG 147")
                    'airlineName'    => $firstBooking->airline_name ?? 'Airline',
                    'airlineLogo'    => $logoUrl,
                    'route'          => ($from && $to) ? "{$from} → {$to}" : 'N/A',
                    'passengerCount' => $paxCount,
                    'bookingCount'   => $flightBookings->count(),
                    'bookings'       => $bookingPassengerList,
                ];
            }
            // Date Card Summary
            $upcomingTravelDates[] = [
                'date'           => $dateStr,
                'displayDate'    => $formattedDisplayDate,
                'flightCount'    => count($flightsDetailList),
                'passengerCount' => $totalPassengersOnDate,
                'bookingCount'   => $bookingsForDate->count(),
            ];
            // Flight Details Modal Data
            $flightDetailsByDate[$dateStr] = [
                'totalPassengers' => $totalPassengersOnDate,
                'totalBookings'   => $bookingsForDate->count(),
                'flights'         => $flightsDetailList,
            ];
        }
        // Sort chronologically by date
        usort($upcomingTravelDates, fn($a, $b) => strcmp($a['date'], $b['date']));

        return response()->json([
            'totalBookings'     => $totalBookings,
            'confirmedBookings' => $confirmedBookings,
            'cancelledBookings' => $cancelledBookings,
            'todayBookings'     => $todayBookings,
            'bookingRatio'      => $bookingRatio,
            'totalTicketing'    => $totalTicketing,
            'ticketed'          => $ticketed,
            'voided'            => $voided,
            'todayTicketing'    => $todayTicketing,
            'ticketingRatio'    => $ticketingRatio,
            'monthlySales'      => $salesByMonth,
            'trendingRoutes'    => $trendingRoutes,
            'travelerData'      => $travelerData,
            'bookingClassData'  => $bookingClassData,
            'cabinClassData'    => $cabinClassStats,
            'monthlyBookings'   => $monthlyBookings,
            'monthlyTicketing'  => $monthlyTicketing,
            'depositData'       => $depositData,
            'creditData'        => $creditData,
            'topAirlines'       => $topAirlines,
            'monthlySearch'     => $monthlySearch,
            'lastTicketingInfo' => $ticketingBookings,
            'upcomingTravelDates' => $upcomingTravelDates,
            'flightDetailsByDate' => $flightDetailsByDate,
        ]);
    }

    private function calculateRatio(int $today, int $yesterday)
    {

        if ($yesterday === 0) {
            if ($today > 0) {
                return 100;
            }

            return 0;
        }

        return round((($today - $yesterday) / $yesterday) * 100, 2);
    }


    private function extractFlightNumber($booking): string
    {
        // Try snapshot JSON
        // $segment = data_get($booking->snapshot_json, 'segments.0');
        // if ($segment && !empty($segment['flight'])) {
        //     return $segment['flight'];
        // }
        // if ($segment && !empty($segment['carrier_code']) && !empty($segment['flightNumber'])) {
        //     return "{$segment['carrier_code']} {$segment['flightNumber']}";
        // }

        // Try commit response payload
        $flight = data_get($booking->commitSession?->response_payload, 'ReservationResponse.Reservation.Offer.0.Product.0.FlightSegment.0.Flight');
        if ($flight) {
            $carrier = $flight['carrier'] ?? $flight['@carrier'] ?? '';
            $number  = $flight['number'] ?? $flight['@number'] ?? '';
            if ($carrier || $number) {
                return trim("{$carrier} {$number}");
            }
        }

        return $booking->airline_code ?? $booking->airline_name ?? 'N/A';
    }
}
