<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Models\Showtime;

class Seat extends Model
{
    protected $table = 'seats';

    protected $fillable = [
        'showtime_id',
        'seat_row',
        'row_label',
        'seat_number',
        'seat_label',
        'seat_type',
        'status',
        'price_multiplier',
        'user_id',
        'reserved_at',
        'paid_at',
        'reservation_expires_at',
    ];

    protected $casts = [
        'price_multiplier' => 'decimal:2',
        'reserved_at' => 'datetime',
        'paid_at' => 'datetime',
        'reservation_expires_at' => 'datetime',
    ];

    public function showtime(): BelongsTo
    {
        return $this->belongsTo(Showtime::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
