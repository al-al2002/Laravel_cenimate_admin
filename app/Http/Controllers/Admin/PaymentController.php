<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index()
    {
        return view('admin.payments');
    }

    public function listPending(Request $request)
    {
        $query = DB::table('tickets')
            ->join('showtimes', 'tickets.showtime_id', '=', 'showtimes.id')
            ->join('movies', 'showtimes.movie_id', '=', 'movies.id')
            ->leftJoin('users', 'tickets.user_id', '=', 'users.id')
            ->select(
                'tickets.*',
                'showtimes.showtime',
                'showtimes.cinema_hall',
                'movies.title as movie_title',
                'movies.poster_url as movie_poster',
                'users.full_name as user_name',
                'users.email as user_email',
                'users.phone_number'
            )
            ->where('tickets.payment_status', 'pending')
            ->whereNotNull('tickets.payment_reference');

        // Apply search filter
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tickets.payment_reference', 'ILIKE', "%{$search}%");
            });
        }

        $payments = $query->orderByDesc('tickets.created_at')->limit(50)->get();

        // Batch fetch all seat IDs at once
        $allSeatIds = [];
        foreach ($payments as $payment) {
            $seatIds = json_decode($payment->seat_ids, true) ?? [];
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

        // Map seat labels to payments
        $payments = $payments->map(function ($payment) use ($seatsMap) {
            $seatIds = json_decode($payment->seat_ids, true) ?? [];
            $labels = [];
            foreach ($seatIds as $seatId) {
                if (isset($seatsMap[$seatId])) {
                    $labels[] = $seatsMap[$seatId];
                }
            }
            $payment->seat_labels = implode(', ', $labels);
            return $payment;
        });

        return response()->json(['data' => $payments]);
    }

    public function confirmPayment(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $ticket = DB::table('tickets')->where('id', $id)->first();

            if (!$ticket) {
                return response()->json(['error' => 'Ticket not found'], 404);
            }

            if ($ticket->payment_status !== 'pending') {
                return response()->json(['error' => 'Payment is not pending'], 400);
            }

            // Update ticket payment status
            DB::table('tickets')
                ->where('id', $id)
                ->update([
                    'payment_status' => 'paid',
                    'status' => 'active',
                    'confirmed_at' => now(),
                    'confirmed_by' => auth('admin')->id(),
                ]);

            // Update seats status to sold
            $seatIds = json_decode($ticket->seat_ids, true) ?? [];
            if (!empty($seatIds)) {
                DB::table('seats')
                    ->whereIn('id', $seatIds)
                    ->update([
                        'status' => 'sold',
                        'paid_at' => now(),
                    ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment confirmed successfully',
                'ticket_number' => $ticket->ticket_number,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to confirm payment: ' . $e->getMessage()], 500);
        }
    }

    public function rejectPayment(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            $ticket = DB::table('tickets')->where('id', $id)->first();

            if (!$ticket) {
                return response()->json(['error' => 'Ticket not found'], 404);
            }

            // Release seats
            $seatIds = json_decode($ticket->seat_ids, true) ?? [];
            if (!empty($seatIds)) {
                DB::table('seats')
                    ->whereIn('id', $seatIds)
                    ->update([
                        'status' => 'available',
                        'user_id' => null,
                        'reserved_at' => null,
                        'reservation_expires_at' => null,
                    ]);
            }

            // Update ticket status with rejection reason
            DB::table('tickets')
                ->where('id', $id)
                ->update([
                    'payment_status' => 'failed',
                    'status' => 'cancelled',
                    'rejection_reason' => $request->reason,
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment rejected successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to reject payment: ' . $e->getMessage()], 500);
        }
    }

    public function releaseExpired()
    {
        try {
            DB::beginTransaction();

            // Only release expired RESERVATIONS (not pending payments)
            // Reservations are seats that are reserved but no payment reference submitted yet
            $expiredReservations = DB::table('reservations')
                ->where('status', 'pending')
                ->where('expires_at', '<', now())
                ->get();

            $releasedCount = 0;

            foreach ($expiredReservations as $reservation) {
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
                        ]);
                }

                // Mark reservation as expired
                DB::table('reservations')
                    ->where('id', $reservation->id)
                    ->update([
                        'status' => 'expired',
                    ]);

                $releasedCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Released {$releasedCount} expired reservations",
                'count' => $releasedCount,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to release expired reservations: ' . $e->getMessage()], 500);
        }
    }

    private function generateTicketNumber(): string
    {
        $prefix = config('payment.ticket_number_prefix', 'TKT');
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(substr(md5(uniqid()), 0, 4));

        return "{$prefix}{$timestamp}{$random}";
    }
}
