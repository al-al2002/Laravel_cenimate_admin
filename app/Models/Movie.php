<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Movie extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'title',
        'description',
        'genre',
        'language',
        'duration_minutes',
        'rating',
        'poster_url',
        'trailer_url',
        'cast',
        'cast_members',
        'release_date',
        'is_active',
        'country',
        'duration',
        'status',
    ];

    protected $casts = [
        'id' => 'string',
        'duration_minutes' => 'integer',
        'duration' => 'integer',
        'is_active' => 'boolean',
        'release_date' => 'date',
        'rating' => 'string',
        'genre' => 'array',
        'cast_members' => 'array',
    ];

    protected $appends = ['poster_url'];

    public static function booted()
    {
        static::creating(function ($movie) {
            if (empty($movie->id)) {
                $movie->id = (string) Str::uuid();
            }
        });
    }

    protected function posterUrl(): Attribute
    {
        return Attribute::get(function () {
            return $this->attributes['poster_url'] ?? null;
        });
    }

    public function showtimes()
    {
        return $this->hasMany(Showtime::class);
    }
}
