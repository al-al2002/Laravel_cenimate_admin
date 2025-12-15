<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Movie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewApiController extends Controller
{
    // Fetch all reviews for a movie with user info
    public function index($movieId)
    {
        // Eager-load the user relation; do not explicitly select columns to remain compatible
        // with existing legacy review table schemas (some use review_text/user_name columns).
        $reviews = Review::with(['user' => function ($q) {
                $q->select('id', 'full_name');
            }])
            ->where('movie_id', $movieId)
            ->orderByDesc('created_at')
            ->get();

        // Handle legacy schema variants where the comment column may be named differently
        $formatted = $reviews->map(function ($r) {
            // If comment field is missing but review_text exists in DB, use it
            if (empty($r->comment) && isset($r->review_text)) {
                $r->comment = $r->review_text;
            }

            // If user relation is missing, fall back to user_name/user_email columns on the reviews table
            if (empty($r->user) && (isset($r->user_name) || isset($r->user_email))) {
                $r->user = (object) [
                    'id' => $r->user_id,
                    'full_name' => $r->user_name ?? null,
                    'email' => $r->user_email ?? null,
                ];
            }

            return $r;
        });

        return response()->json(['data' => $formatted]);
    }

    // Store a new review
    public function store(Request $request, $movieId)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:10',
            'comment' => 'nullable|string',
            'user_id' => 'required|exists:users,id',
        ]);

        $review = Review::create([
            'user_id' => $request->user_id,
            'movie_id' => $movieId,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return response()->json(['data' => $review->load('user:id,name,email')], 201);
    }
}
