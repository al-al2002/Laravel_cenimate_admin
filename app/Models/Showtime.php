<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Models\Movie;
use App\Models\Seat;

class Showtime extends Model
{
    protected $table = 'showtimes';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'movie_id',
        'cinema_hall',
        'showtime',
        'base_price',
        'total_seats',
        'status',
    ];

    protected $casts = [
        'showtime' => 'datetime',
        'base_price' => 'decimal:2',
        'total_seats' => 'integer',
    ];

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    public function availableSeats(): HasMany
    {
        return $this->hasMany(Seat::class)->where('status', 'available');
    }
}
