<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MovieController;
use App\Http\Controllers\Admin\ShowtimeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Auth\AdminAuthController; // <-- updated namespace

// Default route goes to login
Route::get('/', [AdminAuthController::class, 'showLoginForm'])->name('login');

// CSRF token refresh endpoint
Route::get('/csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
});

// Admin login routes
Route::prefix('admin')->group(function () {
    Route::get('login', [AdminAuthController::class, 'showLoginForm'])->name('admin.login');
    Route::post('login', [AdminAuthController::class, 'login'])->name('admin.login.post');
    Route::post('logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

    Route::middleware('auth:admin')->group(function() {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('dashboard/revenue-chart', [DashboardController::class, 'revenueChart']);

        Route::get('movies', [MovieController::class, 'index'])->name('admin.movies');
        Route::get('movies/list', [MovieController::class, 'list']);
        Route::get('movies/stats', [MovieController::class, 'stats']);
        Route::get('movies/ended-showtimes', [MovieController::class, 'endedShowtimes']);
        Route::get('movies/revenue-report', [MovieController::class, 'revenueReport']);
        Route::post('movies', [MovieController::class, 'store']);
        Route::put('movies/{movie}', [MovieController::class, 'update']);
        Route::delete('movies/{movie}', [MovieController::class, 'destroy']);
        Route::patch('movies/{movie}/status', [MovieController::class, 'toggleStatus']);
        Route::get('showtimes', [ShowtimeController::class, 'index'])->name('admin.showtimes');
        Route::post('showtimes', [ShowtimeController::class, 'store'])->name('admin.showtimes.store');
        Route::get('showtimes/{showtime}/edit', [ShowtimeController::class, 'edit'])->name('admin.showtimes.edit');
        Route::put('showtimes/{showtime}', [ShowtimeController::class, 'update'])->name('admin.showtimes.update');
        Route::delete('showtimes/{showtime}', [ShowtimeController::class, 'destroy'])->name('admin.showtimes.destroy');

        Route::get('users', [UserController::class, 'index'])->name('admin.users');
        Route::get('users/list', [UserController::class, 'list']);
        Route::patch('users/{user}/role', [UserController::class, 'updateRole']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);

        Route::get('reservations', [ReservationController::class, 'index'])->name('admin.reservations');
        Route::get('reservations/list', [ReservationController::class, 'list']);
        Route::get('reservations/stats', [ReservationController::class, 'stats']);
        Route::get('reservations/{id}', [ReservationController::class, 'show']);
        Route::post('reservations/{id}/approve', [ReservationController::class, 'approve']);
        Route::post('reservations/{id}/cancel', [ReservationController::class, 'cancel']);

        Route::get('payments', [PaymentController::class, 'index'])->name('admin.payments');
        Route::get('payments/list', [PaymentController::class, 'listPending']);
        Route::post('payments/{id}/confirm', [PaymentController::class, 'confirmPayment']);
        Route::post('payments/{id}/reject', [PaymentController::class, 'rejectPayment']);
        Route::post('payments/release-expired', [PaymentController::class, 'releaseExpired']);
    });
});
