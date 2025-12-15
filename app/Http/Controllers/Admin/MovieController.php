<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MovieRequest;
use App\Models\Movie;
use App\Models\Showtime;
use App\Models\Seat;
use App\Services\SupabaseStorageService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MovieController extends Controller
{
    private SupabaseStorageService $supabaseStorage;

    public function __construct(SupabaseStorageService $supabaseStorage)
    {
        $this->supabaseStorage = $supabaseStorage;
    }
    public function index()
    {
        return view('admin.movies');
    }

    public function list(Request $request)
    {
        // Optimized: Select only needed fields for better performance
        $query = Movie::select([
            'id', 'title', 'genre', 'language', 'duration_minutes',
            'rating', 'description', 'release_date', 'is_active',
            'poster_url', 'trailer_url', 'cast', 'created_at'
        ]);

        if ($search = $request->query('search')) {
            $query->whereRaw('title ILIKE ?', ["%{$search}%"]);
        }

        if ($genre = $request->query('genre')) {
            if ($genre !== 'All') {
                // Query PostgreSQL array column for genre
                $query->whereRaw('? = ANY(genre)', [$genre]);
            }
        }

        if ($status = $request->query('status')) {
            if ($status === 'Active') {
                $query->where('is_active', true);
            } elseif ($status === 'Inactive') {
                $query->where('is_active', false);
            }
        }

        $baseUrl = $request->getSchemeAndHttpHost();

        // Optimized: Use limit if needed, chunk processing for large datasets
        $movies = $query->orderByDesc('created_at')
            ->limit(100) // Limit results to avoid loading too many movies
            ->get()
            ->map(function (Movie $movie) use ($baseUrl) {
                $data = $movie->toArray();
                $data['poster_url'] = $this->toAbsolutePosterUrl($movie->poster_url, $baseUrl);
                // Format release_date for HTML date input (YYYY-MM-DD)
                if ($movie->release_date) {
                    $data['release_date'] = Carbon::parse($movie->release_date)->format('Y-m-d');
                }
                return $data;
            });

        return response()->json(['data' => $movies]);
    }

    public function store(MovieRequest $request)
    {
        $payload = $request->validated();
        $payload['is_active'] = $request->boolean('is_active', true);
        $payload['cast_members'] = DB::raw($this->formatArrayForPostgres($this->normalizeCastMembers($payload['cast'] ?? $request->input('cast'))));
        $payload['duration'] = $payload['duration'] ?? $payload['duration_minutes'];
        $payload['country'] = $payload['country'] ?? 'Philippines';
        $payload['status'] = $payload['status'] ?? 'upcoming';

        // Format genre as PostgreSQL array
        if (isset($payload['genre'])) {
            $genre = is_array($payload['genre']) ? $payload['genre'] : [$payload['genre']];
            $payload['genre'] = DB::raw($this->formatArrayForPostgres($genre));
        }

        if ($posterUrl = $this->storeUploadedPoster($request->file('poster'))) {
            $payload['poster_url'] = $posterUrl;
        } elseif ($request->filled('poster_url')) {
            $payload['poster_url'] = $request->input('poster_url');
        }

        $movie = Movie::create($payload);

        // Clear movie stats cache
        Cache::forget('movies_stats');
        Cache::forget('movies_ended_showtimes');

        return response()->json(['data' => $movie]);
    }

    public function update(MovieRequest $request, Movie $movie)
    {
        $payload = $request->validated();
        $payload['is_active'] = $request->boolean('is_active', true);
        $payload['cast_members'] = DB::raw($this->formatArrayForPostgres($this->normalizeCastMembers($payload['cast'] ?? $request->input('cast'))));
        $payload['duration'] = $payload['duration'] ?? $payload['duration_minutes'] ?? $movie->duration;
        $payload['country'] = $payload['country'] ?? $movie->country ?? 'Philippines';
        $payload['status'] = $payload['status'] ?? $movie->status ?? 'upcoming';

        // Format genre as PostgreSQL array
        if (isset($payload['genre'])) {
            $genre = is_array($payload['genre']) ? $payload['genre'] : [$payload['genre']];
            $payload['genre'] = DB::raw($this->formatArrayForPostgres($genre));
        }

        if ($posterUrl = $this->storeUploadedPoster($request->file('poster'))) {
            $this->deletePosterFile($movie->poster_url);
            $payload['poster_url'] = $posterUrl;
        } elseif ($request->filled('poster_url')) {
            $payload['poster_url'] = $request->input('poster_url');
        }

        $movie->update($payload);

        // Clear movie stats cache
        Cache::forget('movies_stats');
        Cache::forget('movies_ended_showtimes');

        return response()->json(['data' => $movie]);
    }

    public function destroy(Movie $movie)
    {
        // Get all showtime IDs for this movie
        $showtimeIds = $movie->showtimes()->pluck('id');

        // Delete all reviews for this movie
        DB::table('reviews')->where('movie_id', $movie->id)->delete();

        if ($showtimeIds->isNotEmpty()) {
            // Delete tickets related to these showtimes
            DB::table('tickets')->whereIn('showtime_id', $showtimeIds)->delete();

            // Delete payment history related to these tickets (if any)
            DB::table('payment_history')
                ->whereIn('ticket_id', function($query) use ($showtimeIds) {
                    $query->select('id')
                        ->from('tickets')
                        ->whereIn('showtime_id', $showtimeIds);
                })
                ->delete();

            // Delete reservations related to these showtimes
            DB::table('reservations')->whereIn('showtime_id', $showtimeIds)->delete();

            // Delete all seats for showtimes associated with this movie
            Seat::whereIn('showtime_id', $showtimeIds)->delete();
        }

        // Delete all showtimes associated with this movie
        $movie->showtimes()->delete();

        // Delete the poster file
        $this->deletePosterFile($movie->poster_url);

        // Delete the movie
        $movie->delete();

        // Clear movie stats cache
        Cache::forget('movies_stats');
        Cache::forget('movies_ended_showtimes');

        return response()->json(['message' => 'Movie deleted']);
    }    public function toggleStatus(Movie $movie)
    {
        $movie->update(['is_active' => ! $movie->is_active]);

        // Clear movie stats cache when status changes
        Cache::forget('movies_stats');
        Cache::forget('movies_ended_showtimes');

        return response()->json(['data' => $movie]);
    }

    /**
     * Get movies stats including movies with all showtimes ended
     * Optimized: Cache stats for 2 minutes
     */
    public function stats(Request $request)
    {
        $stats = Cache::remember('movies_stats', 120, function () {
            // Use Manila timezone for comparison
            $nowManila = Carbon::now('Asia/Manila')->toDateTimeString();

            // Get movies with all showtimes ended (has showtimes but all are in the past)
            // Cast showtime to timestamp (without timezone) to treat stored time as local time
            // Use pluck->count() instead of count() to avoid GROUP BY issues
            $endedCount = DB::table('movies')
                ->select('movies.id')
                ->join('showtimes', 'movies.id', '=', 'showtimes.movie_id')
                ->where('movies.is_active', true)
                ->groupBy('movies.id')
                ->havingRaw("MAX(showtimes.showtime::timestamp) < ?", [$nowManila])
                ->pluck('id')
                ->count();

            return ['ended_showtimes_count' => $endedCount];
        });

        return response()->json($stats);
    }

    /**
     * Get list of movies with all showtimes ended with revenue data
     * Optimized: Cache for 2 minutes, combined queries
     */
    public function endedShowtimes(Request $request)
    {
        $baseUrl = $request->getSchemeAndHttpHost();

        $result = Cache::remember('movies_ended_showtimes', 120, function () {
            // Use Manila timezone for comparison
            $nowManila = Carbon::now('Asia/Manila')->toDateTimeString();

            // Combined query: Get movies with ended showtimes, showtime data, and revenue in fewer queries
            // Cast showtime to timestamp (without timezone) to treat stored time as local time
            $movieIds = DB::table('movies')
                ->select('movies.id')
                ->join('showtimes', 'movies.id', '=', 'showtimes.movie_id')
                ->where('movies.is_active', true)
                ->groupBy('movies.id')
                ->havingRaw("MAX(showtimes.showtime::timestamp) < ?", [$nowManila])
                ->pluck('id');

            if ($movieIds->isEmpty()) {
                return [];
            }

            // Single optimized query for movie details, showtimes, and revenue
            $movies = DB::table('movies')
                ->leftJoin('showtimes', 'movies.id', '=', 'showtimes.movie_id')
                ->leftJoin('tickets', function ($join) {
                    $join->on('showtimes.id', '=', 'tickets.showtime_id')
                         ->where('tickets.payment_status', '=', 'paid');
                })
                ->whereIn('movies.id', $movieIds)
                ->select(
                    'movies.id',
                    'movies.title',
                    'movies.genre',
                    'movies.poster_url',
                    'movies.is_active',
                    'movies.release_date',
                    DB::raw('COUNT(DISTINCT showtimes.id) as total_showtimes'),
                    DB::raw('MAX(showtimes.showtime::timestamp) as last_showtime'),
                    DB::raw('COALESCE(SUM(tickets.total_amount), 0) as total_revenue'),
                    DB::raw('COUNT(tickets.id) as tickets_sold')
                )
                ->groupBy('movies.id', 'movies.title', 'movies.genre', 'movies.poster_url', 'movies.is_active', 'movies.release_date')
                ->get();

            return $movies->toArray();
        });

        // Format the results (poster URLs and genre parsing)
        $formattedResult = collect($result)->map(function ($movie) use ($baseUrl) {
            $movie = (object) $movie;
            $movie->poster_url = $this->toAbsolutePosterUrl($movie->poster_url, $baseUrl);
            $movie->genre = $this->parsePostgresArray($movie->genre);
            $movie->total_revenue = (float) $movie->total_revenue;
            $movie->tickets_sold = (int) $movie->tickets_sold;
            return $movie;
        });

        return response()->json(['data' => $formattedResult]);
    }

    /**
     * Get revenue report for all movies
     */
    public function revenueReport(Request $request)
    {
        $baseUrl = $request->getSchemeAndHttpHost();

        // Get all movies with their revenue data
        $movies = DB::table('movies')
            ->leftJoin('showtimes', 'movies.id', '=', 'showtimes.movie_id')
            ->leftJoin('tickets', function ($join) {
                $join->on('showtimes.id', '=', 'tickets.showtime_id')
                     ->where('tickets.payment_status', '=', 'paid');
            })
            ->select(
                'movies.id',
                'movies.title',
                'movies.poster_url',
                'movies.is_active',
                'movies.release_date',
                'movies.created_at',
                DB::raw('COALESCE(SUM(tickets.total_amount), 0) as total_revenue'),
                DB::raw('COUNT(tickets.id) as tickets_sold')
            )
            ->groupBy('movies.id', 'movies.title', 'movies.poster_url', 'movies.is_active', 'movies.release_date', 'movies.created_at')
            ->orderByDesc(DB::raw('COALESCE(SUM(tickets.total_amount), 0)'))
            ->get();

        // Format results
        $result = $movies->map(function ($movie) use ($baseUrl) {
            $movie->poster_url = $this->toAbsolutePosterUrl($movie->poster_url, $baseUrl);
            $movie->total_revenue = (float) $movie->total_revenue;
            $movie->tickets_sold = (int) $movie->tickets_sold;
            return $movie;
        });

        // Calculate totals
        $grandTotalRevenue = $result->sum('total_revenue');
        $grandTotalTickets = $result->sum('tickets_sold');

        return response()->json([
            'data' => $result,
            'totals' => [
                'revenue' => $grandTotalRevenue,
                'tickets' => $grandTotalTickets
            ]
        ]);
    }

    /**
     * Parse PostgreSQL array string to PHP array
     */
    private function parsePostgresArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (empty($value) || $value === '{}') {
            return [];
        }

        // Remove the curly braces
        $value = trim($value, '{}');

        if (empty($value)) {
            return [];
        }

        // Split by comma, handling quoted strings
        $items = [];
        $current = '';
        $inQuotes = false;

        for ($i = 0; $i < strlen($value); $i++) {
            $char = $value[$i];

            if ($char === '"' && ($i === 0 || $value[$i - 1] !== '\\')) {
                $inQuotes = !$inQuotes;
            } elseif ($char === ',' && !$inQuotes) {
                $items[] = trim($current, '"');
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if ($current !== '') {
            $items[] = trim($current, '"');
        }

        return $items;
    }

    private function normalizeCastMembers(?string $cast): array
    {
        if (empty($cast)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $cast))));
    }

    private function toAbsolutePosterUrl(?string $posterUrl, string $baseUrl): ?string
    {
        if (empty($posterUrl)) {
            return null;
        }

        if (Str::startsWith($posterUrl, ['http://', 'https://'])) {
            return $posterUrl;
        }

        $baseUrl = rtrim($baseUrl, '/');

        return $baseUrl . '/' . ltrim($posterUrl, '/');
    }

    private function storeUploadedPoster(?UploadedFile $file): ?string
    {
        if (! $file) {
            return null;
        }

        if ($this->supabaseStorage->isEnabled()) {
            try {
                return $this->supabaseStorage->uploadPoster($file);
            } catch (\Throwable $exception) {
                Log::warning('Supabase poster upload failed, falling back to local disk.', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        $path = $disk->putFile('movies', $file);

        // Return relative URL path instead of full URL
        return '/storage/' . $path;
    }

    private function deletePosterFile(?string $posterUrl): void
    {
        if (empty($posterUrl)) {
            return;
        }

        if ($this->supabaseStorage->isEnabled() && $this->supabaseStorage->ownsUrl($posterUrl)) {
            try {
                $this->supabaseStorage->deletePoster($posterUrl);

                return;
            } catch (\Throwable $exception) {
                Log::warning('Supabase poster delete failed, falling back to local disk.', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        $baseUrl = rtrim($disk->url(''), '/');

        if (! Str::startsWith($posterUrl, $baseUrl)) {
            return;
        }

        $relative = Str::after($posterUrl, $baseUrl);

        if (Str::startsWith($relative, '/')) {
            $relative = ltrim($relative, '/');
        }

        if ($relative !== '') {
            $disk->delete($relative);
        }
    }

    /**
     * Format an array for PostgreSQL array column.
     * Converts PHP array to PostgreSQL array literal format.
     *
     * @param array|string $value
     * @return string
     */
    private function formatArrayForPostgres($value): string
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (empty($value)) {
            return '{}';
        }

        // Escape special characters and wrap in quotes
        $escaped = array_map(function ($item) {
            $item = str_replace('\\', '\\\\', $item);
            $item = str_replace('"', '\\"', $item);
            return '"' . $item . '"';
        }, $value);

        return '{' . implode(',', $escaped) . '}';
    }
}
