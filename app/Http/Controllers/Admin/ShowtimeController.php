<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\Seat;
use App\Models\Showtime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShowtimeController extends Controller
{
    public function index(Request $request)
    {
        $dateFilter = $request->input('date', 'all');
        $movieFilter = $request->input('movie', 'all');
        $cinemaFilter = $request->input('cinema', 'all');

        // Build optimized query
        $query = Showtime::select('id', 'movie_id', 'cinema_hall', 'showtime', 'base_price')
            ->with(['movie:id,title,poster_url,duration_minutes,language,description,is_active'])
            ->orderBy('cinema_hall')
            ->orderBy('showtime');

        // Only filter by date if a specific date is selected
        if ($dateFilter !== 'all') {
            try {
                $date = Carbon::parse($dateFilter);
                $query->whereDate('showtime', $date);
            } catch (\Exception $e) {
                // Invalid date, show all
            }
        }

        if ($movieFilter !== 'all') {
            $query->where('movie_id', $movieFilter);
        }

        if ($cinemaFilter !== 'all') {
            $query->where('cinema_hall', $cinemaFilter);
        }

        // Get showtimes first
        $showtimesList = $query->get();

        // Batch fetch available seat counts in a single query
        $showtimeIds = $showtimesList->pluck('id')->toArray();
        $seatCounts = [];

        if (!empty($showtimeIds)) {
            $counts = Seat::selectRaw('showtime_id, COUNT(*) as count')
                ->whereIn('showtime_id', $showtimeIds)
                ->where('status', 'available')
                ->groupBy('showtime_id')
                ->pluck('count', 'showtime_id')
                ->toArray();
            $seatCounts = $counts;
        }

        // Add seat counts to showtimes
        $showtimesList->each(function ($showtime) use ($seatCounts) {
            $showtime->available_seats_count = $seatCounts[$showtime->id] ?? 0;
        });

        // Group by movie
        $showtimes = $showtimesList->groupBy('movie_id');

        // Sort movie groups by latest showtime (descending - newest first)
        $showtimes = $showtimes->sortByDesc(function ($movieShowtimes) {
            return $movieShowtimes->max('showtime');
        });

        // Only fetch active movies with needed columns
        $movies = Movie::select('id', 'title')
            ->where('is_active', true)
            ->orderBy('title')
            ->get();

        // Cache cinema options for 10 minutes
        $cinemas = cache()->remember('cinema_halls', 600, function () {
            return Showtime::select('cinema_hall')
                ->distinct()
                ->orderBy('cinema_hall')
                ->pluck('cinema_hall');
        });

        $defaultHalls = ['Cinema 1', 'Cinema 2', 'Cinema 3', 'Cinema 4'];
        $cinemaOptions = collect($defaultHalls)->merge($cinemas)->unique()->values();

        // Set date variable for the view
        $date = $dateFilter !== 'all' ? Carbon::parse($dateFilter) : null;

        return view('admin.showtimes', compact('showtimes', 'movies', 'date', 'dateFilter', 'movieFilter', 'cinemaFilter', 'cinemas', 'cinemaOptions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'movie_id' => 'required|exists:movies,id',
            'cinema_hall' => 'required|string|max:255',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'base_price' => 'required|numeric|min:1',
        ]);

        $showtimeDate = Carbon::createFromFormat('Y-m-d H:i', "{$validated['date']} {$validated['time']}");
        if ($showtimeDate->isPast()) {
            return redirect()->route('admin.showtimes')->withErrors(['date' => 'Showtime must be scheduled in the future.'])->withInput();
        }

        $showtime = Showtime::create([
            'id' => (string) Str::uuid(),
            'movie_id' => $validated['movie_id'],
            'cinema_hall' => $validated['cinema_hall'],
            'showtime' => $showtimeDate,
            'base_price' => $validated['base_price'],
        ]);

        $this->generateSeats($showtime);

        // Clear cinema halls cache since we added a new hall potentially
        cache()->forget('cinema_halls');

        if ($request->expectsJson()) {
            // Load fresh showtime with movie relationship for DOM insertion
            $showtime->load('movie:id,title,poster_url,duration_minutes,language,description,is_active');
            $showtime->available_seats_count = 120; // New showtime has all 120 seats available

            return response()->json([
                'success' => true,
                'message' => 'Showtime saved successfully.',
                'showtime' => $showtime
            ]);
        }

        // Redirect with the date filter set to the showtime's date
       return redirect()->route('admin.showtimes')
    ->with('success', 'Showtime saved successfully.');

    }

    protected function generateSeats(Showtime $showtime): void
    {
        $rows = range('A', 'J');
        $seatsPerRow = 12;
        $seats = [];
        $now = now();

        foreach ($rows as $rowIndex => $row) {
            for ($seatNumber = 1; $seatNumber <= $seatsPerRow; $seatNumber++) {
                $seatType = 'regular';
                if ($rowIndex >= 7) {
                    $seatType = 'premium';
                } elseif ($rowIndex >= 4 && $seatNumber >= 4 && $seatNumber <= 9) {
                    $seatType = 'vip';
                }

                $seats[] = [
                    'id' => (string) Str::uuid(),
                    'showtime_id' => $showtime->id,
                    'seat_row' => $row,
                    'row_label' => $row,
                    'seat_number' => $seatNumber,
                    'seat_type' => $seatType,
                    'status' => 'available',
                    'created_at' => $now,
                ];
            }
        }

        // Single bulk insert for best performance
        Seat::insert($seats);
    }

    public function edit($id)
    {
        $showtime = Showtime::with('movie')->findOrFail($id);
        return response()->json($showtime);
    }

    public function update(Request $request, $id)
    {
        $showtime = Showtime::findOrFail($id);

        $validated = $request->validate([
            'movie_id' => 'required|exists:movies,id',
            'cinema_hall' => 'required|string|max:255',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
            'base_price' => 'required|numeric|min:1',
        ]);

        $showtimeDate = Carbon::createFromFormat('Y-m-d H:i', "{$validated['date']} {$validated['time']}");

        $showtime->update([
            'movie_id' => $validated['movie_id'],
            'cinema_hall' => $validated['cinema_hall'],
            'showtime' => $showtimeDate,
            'base_price' => $validated['base_price'],
        ]);

        // Clear cinema halls cache
        cache()->forget('cinema_halls');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Showtime updated successfully.'
            ]);
        }

        return redirect()->route('admin.showtimes', ['date' => $showtimeDate->toDateString()])
            ->with('success', 'Showtime updated successfully.');
    }

    public function destroy($id)
    {
        $showtime = Showtime::findOrFail($id);

        try {
            DB::beginTransaction();

            // Delete associated tickets first
            DB::table('tickets')->where('showtime_id', $id)->delete();

            // Delete associated reservations
            DB::table('reservations')->where('showtime_id', $id)->delete();

            // Delete associated seats
            Seat::where('showtime_id', $id)->delete();

            // Delete the showtime
            $showtime->delete();

            // Clear cinema halls cache
            cache()->forget('cinema_halls');

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Showtime deleted successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to delete showtime: ' . $e->getMessage()], 500);
        }
    }
}
