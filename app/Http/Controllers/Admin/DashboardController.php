<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Movie;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Get all stats in a single optimized query batch
        $stats = cache()->remember('dashboard.stats', 60, function () {
            // Run all count queries in parallel using a single connection
            $results = DB::select("
                SELECT
                    (SELECT COUNT(*) FROM movies) as total_movies,
                    (SELECT COUNT(*) FROM users) as total_users,
                    (SELECT COUNT(*) FROM reservations WHERE status = 'pending') as total_reservations,
                    (SELECT COUNT(*) FROM tickets WHERE payment_status = 'pending') as pending_payments,
                    (SELECT COALESCE(SUM(total_amount), 0) FROM tickets WHERE payment_status = 'paid') as total_revenue
            ");

            return $results[0] ?? (object)[
                'total_movies' => 0,
                'total_users' => 0,
                'total_reservations' => 0,
                'pending_payments' => 0,
                'total_revenue' => 0,
            ];
        });

        return view('admin.dashboard', [
            'totalUsers' => $stats->total_users,
            'totalMovies' => $stats->total_movies,
            'totalReservations' => $stats->total_reservations,
            'pendingPayments' => $stats->pending_payments,
            'totalRevenue' => $stats->total_revenue,
        ]);
    }

    /**
     * Get revenue data per day for chart (cached)
     */
    public function revenueChart()
    {
        $data = cache()->remember('dashboard.revenue_chart', 120, function () {
            $startDate = Carbon::now()->subDays(13)->startOfDay();
            $endDate = Carbon::now()->endOfDay();

            $revenueData = DB::table('tickets')
                ->select(
                    DB::raw('DATE(confirmed_at) as date'),
                    DB::raw('SUM(total_amount) as revenue'),
                    DB::raw('COUNT(*) as tickets_count')
                )
                ->where('payment_status', 'paid')
                ->whereNotNull('confirmed_at')
                ->whereBetween('confirmed_at', [$startDate, $endDate])
                ->groupBy(DB::raw('DATE(confirmed_at)'))
                ->orderBy('date')
                ->get()
                ->keyBy('date');

            // Build complete date range with zero-filled missing days
            $labels = [];
            $revenues = [];
            $ticketCounts = [];

            for ($i = 0; $i < 14; $i++) {
                $date = Carbon::now()->subDays(13 - $i)->format('Y-m-d');
                $displayDate = Carbon::now()->subDays(13 - $i)->format('M d');

                $labels[] = $displayDate;
                $revenues[] = isset($revenueData[$date]) ? (float) $revenueData[$date]->revenue : 0;
                $ticketCounts[] = isset($revenueData[$date]) ? (int) $revenueData[$date]->tickets_count : 0;
            }

            return [
                'labels' => $labels,
                'revenues' => $revenues,
                'ticketCounts' => $ticketCounts,
            ];
        });

        return response()->json($data);
    }
}
