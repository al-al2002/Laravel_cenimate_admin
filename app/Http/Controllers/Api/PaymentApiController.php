<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentApiController extends Controller
{
    /**
     * Get payment method details (QR codes and numbers)
     */
    public function getPaymentMethods()
    {
        $methods = config('payment.methods');

        $enabledMethods = collect($methods)->filter(function ($method) {
            return $method['enabled'] ?? false;
        })->map(function ($method, $key) {
            return [
                'id' => $key,
                'name' => $method['name'],
                'qr_code_url' => $method['qr_code_url'] ?? null,
                'account_number' => $method['account_number'] ?? null,
                'account_name' => $method['account_name'] ?? null,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $enabledMethods,
        ]);
    }

    /**
     * Submit payment reference number
     * This marks the reservation as awaiting approval
     */
    public function submitPaymentReference(Request $request)
    {
        $request->validate([
            'reservation_id' => 'required|integer',
            'payment_reference' => 'required|string|max:255',
        ]);

        try {
            $reservation = DB::table('reservations')
                ->where('id', $request->reservation_id)
                ->first();

            if (!$reservation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reservation not found',
                ], 404);
            }

            if ($reservation->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Reservation is not pending',
                ], 400);
            }

            // Update reservation with payment reference
            DB::table('reservations')
                ->where('id', $request->reservation_id)
                ->update([
                    'payment_reference' => $request->payment_reference,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment reference submitted successfully. Please wait for admin approval.',
                'data' => [
                    'reservation_id' => $request->reservation_id,
                    'payment_reference' => $request->payment_reference,
                    'status' => 'pending_approval',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit payment reference: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check reservation status and ticket
     * Returns ticket details if approved
     */
    public function checkReservationStatus($reservationId)
    {
        try {
            $reservation = DB::table('reservations')
                ->where('id', $reservationId)
                ->first();

            if (!$reservation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reservation not found',
                ], 404);
            }

            $response = [
                'success' => true,
                'data' => [
                    'reservation_id' => $reservation->id,
                    'reservation_code' => $reservation->reservation_code,
                    'status' => $reservation->status,
                    'payment_reference' => $reservation->payment_reference,
                ],
            ];

            // If confirmed, get ticket details
            if ($reservation->status === 'confirmed') {
                $ticket = DB::table('tickets')
                    ->where('user_id', $reservation->user_id)
                    ->where('showtime_id', $reservation->showtime_id)
                    ->where('payment_reference', $reservation->payment_reference)
                    ->first();

                if ($ticket) {
                    $response['data']['ticket'] = [
                        'id' => $ticket->id,
                        'ticket_number' => $ticket->ticket_number,
                        'status' => $ticket->status,
                        'qr_code_data' => $ticket->ticket_number, // Can be used to generate QR on Flutter side
                    ];
                }
            }

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check reservation status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get ticket details with QR code data
     */
    public function getTicket($ticketId)
    {
        try {
            $ticket = DB::table('tickets')
                ->join('showtimes', 'tickets.showtime_id', '=', 'showtimes.id')
                ->join('movies', 'showtimes.movie_id', '=', 'movies.id')
                ->where('tickets.id', $ticketId)
                ->select(
                    'tickets.*',
                    'showtimes.showtime',
                    'showtimes.cinema_hall',
                    'movies.title as movie_title',
                    'movies.poster_url as movie_poster'
                )
                ->first();

            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found',
                ], 404);
            }

            $seatIds = json_decode($ticket->seat_ids, true) ?? [];

            if (!empty($seatIds)) {
                $seats = DB::table('seats')
                    ->whereIn('id', $seatIds)
                    ->select('row_label', 'seat_number')
                    ->get();

                $ticket->seat_labels = $seats->map(function ($seat) {
                    return $seat->row_label . $seat->seat_number;
                })->toArray();
            } else {
                $ticket->seat_labels = [];
            }

            return response()->json([
                'success' => true,
                'data' => $ticket,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get ticket: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all tickets for a user
     */
    public function getUserTickets(Request $request)
    {
        $request->validate([
            'user_id' => 'required|string',
        ]);

        try {
            $tickets = DB::table('tickets')
                ->join('showtimes', 'tickets.showtime_id', '=', 'showtimes.id')
                ->join('movies', 'showtimes.movie_id', '=', 'movies.id')
                ->where('tickets.user_id', $request->user_id)
                ->select(
                    'tickets.*',
                    'showtimes.showtime',
                    'showtimes.cinema_hall',
                    'movies.title as movie_title',
                    'movies.poster_url as movie_poster'
                )
                ->orderByDesc('tickets.created_at')
                ->get();

            // Get seat details for each ticket
            $tickets = $tickets->map(function ($ticket) {
                $seatIds = json_decode($ticket->seat_ids, true) ?? [];

                if (!empty($seatIds)) {
                    $seats = DB::table('seats')
                        ->whereIn('id', $seatIds)
                        ->select('row_label', 'seat_number')
                        ->get();

                    $ticket->seat_labels = $seats->map(function ($seat) {
                        return $seat->row_label . $seat->seat_number;
                    })->join(', ');
                } else {
                    $ticket->seat_labels = '';
                }

                return $ticket;
            });

            return response()->json([
                'success' => true,
                'data' => $tickets,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get tickets: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all reservations for a user
     */
    public function getUserReservations(Request $request)
    {
        $request->validate([
            'user_id' => 'required|string',
        ]);

        try {
            $reservations = DB::table('reservations')
                ->join('showtimes', 'reservations.showtime_id', '=', 'showtimes.id')
                ->join('movies', 'showtimes.movie_id', '=', 'movies.id')
                ->where('reservations.user_id', $request->user_id)
                ->select(
                    'reservations.*',
                    'showtimes.showtime',
                    'showtimes.cinema_hall',
                    'movies.title as movie_title',
                    'movies.poster_url as movie_poster'
                )
                ->orderByDesc('reservations.created_at')
                ->get();

            // Get seat details for each reservation
            $reservations = $reservations->map(function ($reservation) {
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

                return $reservation;
            });

            return response()->json([
                'success' => true,
                'data' => $reservations,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get reservations: ' . $e->getMessage(),
            ], 500);
        }
    }
}
