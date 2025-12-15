<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    public function index()
    {
        return view('admin.reservations');
    }

    /**
     * Get stats in a single optimized query
     */
    public function stats()
    {
        // Single query with conditional counts
        $reservationStats = DB::table('reservations')
            ->selectRaw("COUNT(CASE WHEN status = 'pending' AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 END) as pending")
            ->selectRaw("COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled")
            ->first();

        $ticketCount = DB::table('tickets')
            ->where('status', 'active')
            ->where('payment_status', 'paid')
            ->count();

        $pending = $reservationStats->pending ?? 0;
        $cancelled = $reservationStats->cancelled ?? 0;

        return response()->json([
            'total' => $pending + $cancelled,
            'pending' => $pending,
            'tickets' => $ticketCount,
            'cancelled' => $cancelled,
        ]);
    }

    public function list(Request $request)
    {
        $status = $request->query('status');

        // If filtering for tickets (active), query tickets table instead
        if ($status === 'active') {
            return $this->listTickets($request);
        }

        $query = DB::table('reservations')
            ->join('showtimes', 'reservations.showtime_id', '=', 'showtimes.id')
            ->join('movies', 'showtimes.movie_id', '=', 'movies.id')
            ->select(
                'reservations.*',
                DB::raw('showtimes.showtime::timestamp as showtime'),
                'showtimes.cinema_hall',
                'movies.title as movie_title',
                'movies.poster_url as movie_poster'
            );

        // Apply search filter
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reservations.reservation_code', 'ILIKE', "%{$search}%")
                    ->orWhere('reservations.payment_reference', 'ILIKE', "%{$search}%");
            });
        }

        // Apply status filter
        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('reservations.status', $status);
            }
        }

        $reservations = $query->orderByDesc('reservations.created_at')->limit(50)->get();

        // Batch fetch all seat IDs at once
        $allSeatIds = [];
        foreach ($reservations as $reservation) {
            $seatIds = json_decode($reservation->seat_ids, true) ?? [];
            $allSeatIds = array_merge($allSeatIds, $seatIds);
        }

        // Single query for all seats
        $seatsMap = [];
        if (!empty($allSeatIds)) {
            $seats = DB::table('seats')
                ->whereIn('id', array_unique($allSeatIds))
                ->select('id', 'row_label', 'seat_number')
                ->get();
            foreach ($seats as $seat) {
                $seatsMap[$seat->id] = $seat->row_label . $seat->seat_number;
            }
        }

        // Map seat labels to reservations
        $reservations = $reservations->map(function ($reservation) use ($seatsMap) {
            $seatIds = json_decode($reservation->seat_ids, true) ?? [];
            $labels = [];
            foreach ($seatIds as $seatId) {
                if (isset($seatsMap[$seatId])) {
                    $labels[] = $seatsMap[$seatId];
                }
            }
            $reservation->seat_labels = implode(', ', $labels);

            // Format showtime in Manila timezone for display
            $showtimeManila = \Carbon\Carbon::parse($reservation->showtime)->setTimezone('Asia/Manila');
            $reservation->showtime_formatted = $showtimeManila->format('Y-m-d\TH:i:s');

            return $reservation;
        });

        return response()->json(['data' => $reservations]);
    }

    public function show($id)
    {
        $reservation = DB::table('reservations')
            ->join('showtimes', 'reservations.showtime_id', '=', 'showtimes.id')
            ->join('movies', 'showtimes.movie_id', '=', 'movies.id')
            ->where('reservations.id', $id)
            ->select(
                'reservations.*',
                DB::raw('showtimes.showtime::timestamp as showtime'),
                'showtimes.cinema_hall',
                'movies.title as movie_title',
                'movies.poster_url as movie_poster'
            )
            ->first();

        if (!$reservation) {
            return response()->json(['error' => 'Reservation not found'], 404);
        }

        // Format showtime in Manila timezone for display
        $showtimeManila = \Carbon\Carbon::parse($reservation->showtime)->setTimezone('Asia/Manila');
        $reservation->showtime_formatted = $showtimeManila->format('Y-m-d\TH:i:s');

        $seatIds = json_decode($reservation->seat_ids, true) ?? [];

        if (!empty($seatIds)) {
            $seats = DB::table('seats')
                ->whereIn('id', $seatIds)
                ->select('row_label', 'seat_number')
                ->get();

            $reservation->seat_labels = $seats->map(function ($seat) {
                return $seat->row_label . $seat->seat_number;
            })->join(', ');
        } else {
            $reservation->seat_labels = '';
        }

        return response()->json(['data' => $reservation]);
    }

    public function approve(Request $request, $id)
    {
        $request->validate([
            'payment_reference' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            // Get reservation details
            $reservation = DB::table('reservations')->where('id', $id)->first();

            if (!$reservation) {
                return response()->json(['error' => 'Reservation not found'], 404);
            }

            if ($reservation->status !== 'pending') {
                return response()->json(['error' => 'Reservation is not pending'], 400);
            }

            // Generate ticket number
            $ticketNumber = $this->generateTicketNumber();

            // Create ticket
            $ticketId = DB::table('tickets')->insertGetId([
                'showtime_id' => $reservation->showtime_id,
                'user_id' => $reservation->user_id,
                'seat_ids' => $reservation->seat_ids,
                'ticket_number' => $ticketNumber,
                'payment_method' => $reservation->payment_method,
                'payment_reference' => $request->payment_reference,
                'total_amount' => $reservation->total_amount,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update seats status to paid
            $seatIds = json_decode($reservation->seat_ids, true) ?? [];
            if (!empty($seatIds)) {
                DB::table('seats')
                    ->whereIn('id', $seatIds)
                    ->update([
                        'status' => 'sold',
                        'paid_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            // Update reservation status
            DB::table('reservations')
                ->where('id', $id)
                ->update([
                    'status' => 'confirmed',
                    'payment_reference' => $request->payment_reference,
                    'updated_at' => now(),
                ]);

            // Create payment history
            DB::table('payment_history')->insert([
                'ticket_id' => $ticketId,
                'user_id' => $reservation->user_id,
                'amount' => $reservation->total_amount,
                'payment_method' => $reservation->payment_method,
                'payment_reference' => $request->payment_reference,
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Reservation approved and ticket generated successfully',
                'ticket_number' => $ticketNumber,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to approve reservation: ' . $e->getMessage()], 500);
        }
    }

    public function cancel($id)
    {
        try {
            DB::beginTransaction();

            $reservation = DB::table('reservations')->where('id', $id)->first();

            if (!$reservation) {
                return response()->json(['error' => 'Reservation not found'], 404);
            }

            // Release seats
            $seatIds = json_decode($reservation->seat_ids, true) ?? [];
            if (!empty($seatIds)) {
                DB::table('seats')
                    ->whereIn('id', $seatIds)
                    ->update([
                        'status' => 'available',
                        'user_id' => null,
                        'reserved_at' => null,
                        'reservation_expires_at' => null,
                        'updated_at' => now(),
                    ]);
            }

            // Update reservation status
            DB::table('reservations')
                ->where('id', $id)
                ->update([
                    'status' => 'cancelled',
                    'updated_at' => now(),
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Reservation cancelled successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to cancel reservation: ' . $e->getMessage()], 500);
        }
    }

    private function listTickets(Request $request)
    {
        $query = DB::table('tickets')
            ->join('showtimes', 'tickets.showtime_id', '=', 'showtimes.id')
            ->join('movies', 'showtimes.movie_id', '=', 'movies.id')
            ->select(
                'tickets.*',
                DB::raw('showtimes.showtime::timestamp as showtime'),
                'showtimes.cinema_hall',
                'movies.title as movie_title',
                'movies.poster_url as movie_poster',
                'movies.duration_minutes'
            );

        // Apply search filter
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tickets.ticket_number', 'ILIKE', "%{$search}%")
                    ->orWhere('tickets.payment_reference', 'ILIKE', "%{$search}%");
            });
        }

        // Only show tickets with paid status (active or expired)
        $query->where('tickets.payment_status', 'paid');

        $tickets = $query->orderByDesc('tickets.created_at')->limit(50)->get();

        // Batch fetch all seat IDs at once
        $allSeatIds = [];
        foreach ($tickets as $ticket) {
            $seatIds = json_decode($ticket->seat_ids, true) ?? [];
            $allSeatIds = array_merge($allSeatIds, $seatIds);
        }

        // Single query for all seats
        $seatsMap = [];
        if (!empty($allSeatIds)) {
            $seats = DB::table('seats')
                ->whereIn('id', array_unique($allSeatIds))
                ->select('id', 'row_label', 'seat_number')
                ->get();
            foreach ($seats as $seat) {
                $seatsMap[$seat->id] = $seat->row_label . $seat->seat_number;
            }
        }

        // Map seat labels to tickets and check if expired
        $now = now()->setTimezone('Asia/Manila');
        $tickets = $tickets->map(function ($ticket) use ($seatsMap, $now) {
            $seatIds = json_decode($ticket->seat_ids, true) ?? [];
            $labels = [];
            foreach ($seatIds as $seatId) {
                if (isset($seatsMap[$seatId])) {
                    $labels[] = $seatsMap[$seatId];
                }
            }
            $ticket->seat_labels = implode(', ', $labels);
            $ticket->reservation_code = $ticket->ticket_number;

            // Check if movie has ended (showtime + movie duration)
            // Parse showtime and convert to Manila timezone for comparison
            $showtimeManila = \Carbon\Carbon::parse($ticket->showtime)->setTimezone('Asia/Manila');
            $duration = $ticket->duration_minutes ?? 120;
            $movieEndTime = $showtimeManila->copy()->addMinutes($duration);
            $ticket->is_expired = $now->greaterThan($movieEndTime);

            // Format showtime in Manila timezone for display
            $ticket->showtime_formatted = $showtimeManila->format('Y-m-d\TH:i:s');
            $ticket->movie_end_time = $movieEndTime->format('Y-m-d\TH:i:s');
            $ticket->duration = $duration;

            return $ticket;
        });

        return response()->json(['data' => $tickets]);
    }

    private function generateTicketNumber(): string
    {
        $prefix = config('payment.ticket_number_prefix', 'TKT');
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(substr(md5(uniqid()), 0, 4));

        return "{$prefix}{$timestamp}{$random}";
    }
}
