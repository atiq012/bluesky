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
            ->whereIn('status', ['confirmed', 'committed', 'ticketing', 'ticketed', 'cancelled', 'voided'])
            ->count();
        $confirmedBookings = (clone $query)
            ->whereIn('status', ['confirmed', 'committed', 'ticketing', 'ticketed', 'voided'])
            ->count();
        $cancelledBookings = (clone $query)
            ->where('status', 'cancelled')
            ->count();
        $todayBookings = (clone $query)
            ->whereIn('status', ['confirmed', 'committed', 'ticketing', 'ticketed', 'cancelled', 'voided'])
            ->whereDate('created_at', Carbon::today())
            ->count();

        $yesterdayBookings = (clone $query)
            ->whereIn('status', ['confirmed', 'committed', 'ticketing', 'ticketed', 'cancelled', 'voided'])
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
            ->whereIn('booking_attempts.status', ['confirmed', 'committed', 'ticketing', 'ticketed', 'voided'])
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
            ->whereIn('status', ['confirmed', 'committed', 'ticketing', 'ticketed', 'cancelled', 'voided'])
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

        // Query Top 10 Most Selling Airlines
        // $topAirlinesRaw = BookingAttempt::join('booking_price_logs', 'booking_attempts.id', '=', 'booking_price_logs.booking_attempt_id')
        //     ->whereIn('booking_attempts.status', ['ticketed', 'voided'])
        //     ->whereNotNull('booking_attempts.airline_name')
        //     ->where('booking_attempts.airline_name', '!=', '')
        //     ->when(!empty($agencyUserIds), function ($q) use ($agencyUserIds) {
        //         $q->whereIn('booking_attempts.user_id', $agencyUserIds);
        //     })
        //     ->selectRaw("
        //         booking_attempts.airline_name as name,
        //         COUNT(DISTINCT booking_attempts.id) as ticketing,
        //         SUM(booking_price_logs.total_price) as sales")
        //     ->groupBy('booking_attempts.airline_name')
        //     ->orderByDesc('ticketing')
        //     ->limit(10)
        //     ->get();
        // $topAirlines = $topAirlinesRaw->map(function ($item) {
        //     return [
        //         'name'      => $item->name,
        //         'ticketing' => (int) $item->ticketing,
        //         'sales'     => number_format($item->sales, 2),
        //         'trend'     => 'up', // Options: 'up', 'down', 'same'
        //     ];
        // })->toArray();


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
            'monthlyBookings'   => $monthlyBookings,
            'monthlyTicketing'  => $monthlyTicketing,
            'depositData'       => $depositData,
            'creditData'        => $creditData,
            'topAirlines'       => $topAirlines,
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
}
