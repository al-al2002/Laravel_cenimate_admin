
<?php

use App\Http\Controllers\Api\ReviewApiController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\PaymentApiController;
use App\Http\Controllers\Api\UserSessionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public API endpoints for Flutter app
Route::prefix('v1')->group(function () {
    // Reviews API
    Route::get('movies/{movie}/reviews', [ReviewApiController::class, 'index']); // Fetch all reviews for a movie (with user)
    Route::post('movies/{movie}/reviews', [ReviewApiController::class, 'store']); // Add a review for a movie
    /**
     * GET /api/v1/users
     * Fetches all regular users (non-admin) for Flutter app
     *
     * Response:
     * {
     *   "success": true,
     *   "data": [...],
     *   "count": 10
     * }
     */
    Route::get('users', [UserApiController::class, 'getRegularUsers']);

    /**
     * DELETE /api/v1/users/{id}
     * Deletes a user from Supabase Auth
     */
    Route::delete('users/{id}', [UserApiController::class, 'deleteUser']);

    /**
     * GET /api/v1/payment/methods
     * Get available payment methods with QR codes
     */
    Route::get('payment/methods', [PaymentApiController::class, 'getPaymentMethods']);

    /**
     * POST /api/v1/payment/submit-reference
     * Submit payment reference number after payment
     */
    Route::post('payment/submit-reference', [PaymentApiController::class, 'submitPaymentReference']);

    /**
     * GET /api/v1/payment/reservation/{id}/status
     * Check reservation status and get ticket if approved
     */
    Route::get('payment/reservation/{id}/status', [PaymentApiController::class, 'checkReservationStatus']);

    /**
     * GET /api/v1/payment/ticket/{id}
     * Get ticket details with QR code data
     */
    Route::get('payment/ticket/{id}', [PaymentApiController::class, 'getTicket']);

    /**
     * GET /api/v1/tickets
     * Get all tickets for a user
     */
    Route::get('tickets', [PaymentApiController::class, 'getUserTickets']);

    /**
     * GET /api/v1/reservations
     * Get all reservations for a user
     */
    Route::get('reservations', [PaymentApiController::class, 'getUserReservations']);

    /**
     * POST /api/v1/user/session
     * Update user online status (login/logout from Flutter app)
     */
    Route::post('user/session', [UserSessionController::class, 'updateSession']);

    /**
     * POST /api/v1/user/heartbeat
     * Keep user session alive (called periodically from Flutter)
     */
    Route::post('user/heartbeat', [UserSessionController::class, 'heartbeat']);

    /**
     * GET /api/v1/user/online
     * Get list of online users
     */
    Route::get('user/online', [UserSessionController::class, 'getOnlineUsers']);
});
