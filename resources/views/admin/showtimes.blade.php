@extends('layouts.admin')

@section('styles')
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://isqzlkxwpotvjkymirvn.supabase.co">
    <link href="{{ asset('css/showtimes.css') }}" rel="stylesheet">
@endsection

@section('content')
    <div class="showtimes-page">
        <div class="showtimes-content">
            <div class="hero-panel text-white mb-4">
                <div>
                    <h1 class="h3 mb-2">Showtime Management</h1>
                    <p class="text-muted-light mb-0">Organize every showtime, cinema hall, and pricing detail in one place.
                    </p>
                </div>
                <div class="text-md-end">
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal"
                        data-bs-target="#addShowtimeModal">
                        <i class="bi bi-plus-lg"></i> Add Showtime
                    </button>
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success text-white bg-success border-0 mb-4">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger text-white bg-danger border-0 mb-4">
                    {!! implode('<br>', $errors->all()) !!}
                </div>
            @endif

            <!-- Search -->
            <div class="card mb-4 text-white" style="background: #050505; border: 1px solid rgba(239, 68, 68, 0.15);">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-10">
                            <label class="form-label" for="searchMovie">Search Movie</label>
                            <input type="text" id="searchMovie" class="form-control text-white border-0"
                                style="background: #1f1f1f; color: #fff !important;" placeholder="Search by movie name..."
                                autocomplete="off">
                            <style>
                                #searchMovie::placeholder {
                                    color: #b0b0b0 !important;
                                    opacity: 1;
                                }
                            </style>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button class="btn btn-outline-danger w-100" id="clearSearch">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            @php
                $allShowtimes = $showtimes->flatten();
                $totalSeats = $allShowtimes->sum('available_seats_count');
                $showtimeCount = $allShowtimes->count();
                $movieCount = $movies->count();
            @endphp
            <div class="mb-2 d-flex justify-content-between flex-wrap gap-2 text-muted-light">
                <div id="showtimeStats">{{ $showtimeCount }} showtimes shown ({{ $totalSeats }} seats available)</div>
                <div>Total movies tracked: {{ $movieCount }}</div>
            </div>

            @if ($allShowtimes->isEmpty())
                <div class="text-center text-muted-light mb-4">
                    <p>No showtimes found.</p>
                </div>
            @else
                <div id="showtimesContainer">
                    @foreach ($showtimes as $movieId => $movieShowtimes)
                        @php
                            $firstShowtime = $movieShowtimes->first();
                            $movie = $firstShowtime->movie;
                            $movieTotalSeats = $movieShowtimes->sum('available_seats_count');
                            $movieShowtimeCount = $movieShowtimes->count();
                        @endphp

                        <div class="mb-4 movie-group" data-movie-name="{{ strtolower($movie->title) }}"
                            data-movie-seats="{{ $movieTotalSeats }}" data-movie-showtime-count="{{ $movieShowtimeCount }}">
                            <!-- Movie Header -->
                            <div class="d-flex gap-3 align-items-start mb-3">
                                <div style="flex-shrink: 0;">
                                    <img src="{{ $movie->poster_url ?? 'https://via.placeholder.com/100x140?text=Poster' }}"
                                        alt="{{ $movie->title }}"
                                        style="width: 100px; height: 140px; object-fit: cover; border-radius: 8px;">
                                </div>
                                <div class="grow">
                                    <h4 class="text-white mb-2">
                                        {{ $movie->title }}
                                        @if (!$movie->is_active)
                                            <span class="badge bg-secondary ms-2"
                                                style="font-size: 0.6rem; vertical-align: middle;">Deactivated</span>
                                        @endif
                                    </h4>
                                    <p class="mb-1 text-muted-light">
                                        {{ $movie->duration_minutes }} min • {{ $movie->language ?? 'N/A' }}
                                    </p>
                                    <p class="mb-1">
                                        <span class="badge bg-danger me-2 movie-showtime-count">{{ $movieShowtimeCount }}
                                            showtime{{ $movieShowtimeCount > 1 ? 's' : '' }}</span>
                                        <span class="badge bg-success movie-seats-count">{{ $movieTotalSeats }} seats
                                            available</span>
                                    </p>
                                    <p class="text-muted-light mb-0" style="font-size: 0.85rem;">
                                        {{ $movie->description ? \Illuminate\Support\Str::limit($movie->description, 150) : 'No description yet.' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Showtimes List for this Movie -->
                            <div class="row g-3">
                                @foreach ($movieShowtimes as $showtime)
                                    @php
                                        $showtimeDate =
                                            $showtime->showtime instanceof \Carbon\Carbon
                                                ? $showtime->showtime
                                                : \Carbon\Carbon::parse($showtime->showtime);
                                    @endphp
                                    <div class="col-lg-6 showtime-item" data-showtime-id="{{ $showtime->id }}"
                                        data-cinema="{{ $showtime->cinema_hall }}">
                                        <div class="showtime-card p-3">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="text-white mb-1">
                                                        <i
                                                            class="bi bi-building text-danger me-2"></i>{{ $showtime->cinema_hall }}
                                                    </h6>
                                                </div>
                                                <span
                                                    class="badge badge-pill {{ $showtime->available_seats_count ? 'bg-success' : 'bg-danger' }}">
                                                    {{ $showtime->available_seats_count }} seats
                                                </span>
                                            </div>
                                            <div class="d-flex flex-wrap gap-3 text-muted-light mb-3">
                                                <div>
                                                    <i class="bi bi-calendar-event me-1"></i>
                                                    {{ $showtimeDate->format('M d, Y') }}
                                                </div>
                                                <div>
                                                    <i class="bi bi-clock me-1"></i>
                                                    {{ $showtimeDate->format('h:i A') }}
                                                </div>
                                                <div>
                                                    <i class="bi bi-currency-exchange me-1"></i>
                                                    ₱{{ number_format($showtime->base_price, 2) }}
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-outline-danger btn-sm btn-edit"
                                                    onclick="editShowtime('{{ $showtime->id }}')">Edit</button>
                                                <button type="button" class="btn btn-outline-danger btn-sm btn-delete"
                                                    onclick="deleteShowtime('{{ $showtime->id }}', '{{ addslashes($movie->title) }}', '{{ $showtimeDate->format('M d, Y h:i A') }}')">
                                                    Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- Add/Edit Showtime Modal -->
    <div class="modal fade" id="addShowtimeModal" tabindex="-1" aria-labelledby="addShowtimeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-black border border-danger border-opacity-50 text-white">
                <form method="POST" action="{{ route('admin.showtimes.store') }}" id="showtimeForm">
                    @csrf
                    <input type="hidden" name="_method" id="formMethod" value="POST">
                    <input type="hidden" name="showtime_id" id="showtimeId">
                    <div class="modal-header border-bottom border-danger">
                        <h5 class="modal-title" id="addShowtimeModalLabel">Add New Showtime</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label text-muted-light" for="movieSelect">Movie</label>
                            <select id="movieSelect" class="form-select bg-dark text-white border border-danger"
                                name="movie_id" required>
                                <option value="" disabled selected>Select a movie</option>
                                @foreach ($movies as $movie)
                                    <option value="{{ $movie->id }}">{{ $movie->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted-light" for="cinemaHall">Cinema Hall</label>
                            <select id="cinemaHall" class="form-select bg-dark text-white border border-danger"
                                name="cinema_hall" required>
                                <option value="" disabled selected>Select a hall</option>
                                @foreach ($cinemaOptions as $hall)
                                    <option value="{{ $hall }}">{{ $hall }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted-light" for="showtimeDate">Date</label>
                                <input id="showtimeDate" type="date"
                                    class="form-control bg-dark text-white border border-danger" name="date"
                                    value="{{ old('date', now()->toDateString()) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted-light" for="showtimeTime">Time</label>
                                <input id="showtimeTime" type="time"
                                    class="form-control bg-dark text-white border border-danger" name="time"
                                    value="{{ old('time', now()->format('H:i')) }}" required>
                            </div>
                        </div>
                        <div class="mb-3 mt-3">
                            <label class="form-label text-muted-light" for="basePrice">Base Price (₱)</label>
                            <input id="basePrice" name="base_price" type="number" step="0.01" min="1"
                                value="{{ old('base_price', '250') }}"
                                class="form-control bg-dark text-white border border-danger" required>
                        </div>
                    </div>
                    <div class="modal-footer border-top border-danger">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" id="submitBtn">Save Showtime</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>

    <style>
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
        }

        .custom-toast {
            background: #050505;
            border: 1px solid;
            border-radius: 8px;
            padding: 1rem 1.5rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 300px;
            animation: slideIn 0.3s ease-out;
        }

        .custom-toast.success {
            border-color: #22c55e;
        }

        .custom-toast.error {
            border-color: #ef4444;
        }

        .custom-toast-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .custom-toast.success .custom-toast-icon {
            background: #22c55e;
            color: white;
        }

        .custom-toast.error .custom-toast-icon {
            background: #ef4444;
            color: white;
        }

        .custom-toast-message {
            color: #f8fafc;
            flex: 1;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <script src="{{ asset('js/showtimes.js') }}"></script>
@endpush
