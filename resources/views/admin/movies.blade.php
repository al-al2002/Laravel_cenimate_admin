@extends('layouts.admin')

@section('styles')
    <!-- Preconnect to external resources for faster loading -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://isqzlkxwpotvjkymirvn.supabase.co">
    <link href="{{ asset('css/movies.css') }}" rel="stylesheet">
@endsection

@section('content')
    <div class="container-fluid">
        <div class="hero-panel mb-4 text-white">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1>Movie Catalog Control</h1>
                    <p>Keep the collection accurate, update the artwork, and ensure every screening detail stays current.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <button class="btn btn-danger" id="addMovieBtn">Add New Movie</button>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg">
                <div class="stat-pill stat-pill-clickable" id="activeMoviesCard" role="button" tabindex="0">
                    <span class="card-text-transparent">Active movies</span>
                    <strong id="statActive">0</strong>
                    <span class="stat-pill-hint"><i class="bi bi-funnel me-1"></i>Click to filter</span>
                </div>
            </div>
            <div class="col-6 col-lg">
                <div class="stat-pill stat-pill-clickable" id="disabledMoviesCard" role="button" tabindex="0">
                    <span class="card-text-transparent">Disabled movies</span>
                    <strong id="statInactive">0</strong>
                    <span class="stat-pill-hint"><i class="bi bi-funnel me-1"></i>Click to filter</span>
                </div>
            </div>
            <div class="col-6 col-lg">
                <div class="stat-pill">
                    <span class="card-text-transparent">Average rating</span>
                    <strong id="statRating">0</strong>
                </div>
            </div>
            <div class="col-6 col-lg">
                <div class="stat-pill stat-pill-clickable" id="endedShowtimesCard" role="button" tabindex="0">
                    <span class="card-text-transparent">Showtimes Ended</span>
                    <strong id="statEnded">0</strong>
                    <span class="stat-pill-hint"><i class="bi bi-eye me-1"></i>Click to view</span>
                </div>
            </div>
            <div class="col-12 col-lg">
                <div class="stat-pill stat-pill-clickable stat-pill-revenue" id="revenueReportCard" role="button"
                    tabindex="0">
                    <span class="card-text-transparent">Revenue Report</span>
                    <strong id="statTotalRevenue">₱0</strong>
                    <span class="stat-pill-hint"><i class="bi bi-bar-chart me-1"></i>Click to view</span>
                </div>
            </div>
        </div>

        <div class="card mb-4 filters text-white">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="searchInput">Search title or keyword</label>
                        <input type="text" id="searchInput" class="form-control bg-slate-800 text-white"
                            placeholder="Search by title" autocomplete="off">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="filterGenre">Genre</label>
                        <select id="filterGenre" class="form-select bg-slate-800 text-white">
                            <option value="All">All</option>
                            <option>Action</option>
                            <option>Comedy</option>
                            <option>Drama</option>
                            <option>Horror</option>
                            <option>Romance</option>
                            <option>Sci-Fi</option>
                            <option>Thriller</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="filterStatus">Status</label>
                        <select id="filterStatus" class="form-select bg-slate-800 text-white">
                            <option value="All">All</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-outline-light w-100" id="searchBtn">
                            <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="alertContainer"></div>
        <div id="moviesList" class="row g-3"></div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <div class="modal fade confirm-modal" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-white">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Confirm Action</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="confirmMessage">
                    Are you sure you want to proceed?
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">No</button>
                    <button type="button" class="btn btn-danger" id="confirmYes">Yes</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="movieModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 text-white movie-modal" style="background: #000;">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="movieModalLabel">Add Movie</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <form id="movieForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Title *</label>
                                <input type="text" name="title" class="form-control bg-slate-800 text-white"
                                    required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Genre *</label>
                                <select name="genre" class="form-select bg-slate-800 text-white" required>
                                    <option>Action</option>
                                    <option>Comedy</option>
                                    <option>Drama</option>
                                    <option>Horror</option>
                                    <option>Romance</option>
                                    <option>Sci-Fi</option>
                                    <option>Thriller</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Language *</label>
                                <select name="language" class="form-select bg-slate-800 text-white" required>
                                    <option>English</option>
                                    <option>Filipino</option>
                                    <option>Korean</option>
                                    <option>Japanese</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Duration (min) *</label>
                                <input type="number" name="duration_minutes"
                                    class="form-control bg-slate-800 text-white" min="1" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Rating (1-10) *</label>
                                <input type="number" step="0.1" name="rating"
                                    class="form-control bg-slate-800 text-white" min="1" max="10" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description *</label>
                                <textarea name="description" rows="3" class="form-control bg-slate-800 text-white" required></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Trailer URL</label>
                                <input type="url" name="trailer_url" class="form-control bg-slate-800 text-white">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cast</label>
                                <input type="text" name="cast" class="form-control bg-slate-800 text-white"
                                    placeholder="Comma separated">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Release Date *</label>
                                <input type="date" name="release_date" class="form-control bg-slate-800 text-white"
                                    required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Poster Image
                                    <span class="text-muted-light">(jpg/png)</span>
                                </label>
                                <div id="currentPosterPreview" class="mb-2" style="display: none;">
                                    <img id="posterPreviewImg" src="" alt="Current Poster"
                                        style="max-height: 80px; border-radius: 6px; border: 1px solid #ef4444;">
                                    <small class="text-muted-light d-block mt-1">Current poster (leave empty to
                                        keep)</small>
                                </div>
                                <input type="file" name="poster" class="form-control bg-slate-800 text-white">
                                <small class="text-muted-light">Uploading a new file replaces the existing artwork.</small>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="isActiveToggle"
                                        checked>
                                    <label class="form-check-label" for="isActiveToggle">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Save Movie</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Ended Showtimes Modal -->
    <div class="modal fade" id="endedShowtimesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 text-white"
                style="background: #050505; border: 1px solid #ef4444 !important;">
                <div class="modal-header border-bottom border-danger">
                    <h5 class="modal-title">
                        <i class="bi bi-calendar-x me-2 text-danger"></i>Movies with All Showtimes Ended
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body" id="endedShowtimesBody">
                    <div class="text-center py-5">
                        <span class="spinner-border text-danger"></span>
                        <p class="mt-3 text-muted">Loading movies...</p>
                    </div>
                </div>
                <div class="modal-footer border-top border-danger">
                    <div class="d-flex align-items-center me-auto">
                        <span class="text-muted small">Total Revenue: </span>
                        <strong class="ms-2 text-success" id="totalRevenueDisplay">₱0.00</strong>
                    </div>
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Revenue Report Modal -->
    <div class="modal fade" id="revenueReportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 text-white"
                style="background: #050505; border: 1px solid #22c55e !important;">
                <div class="modal-header border-bottom border-success">
                    <h5 class="modal-title">
                        <i class="bi bi-bar-chart me-2 text-success"></i>Movie Revenue Report
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body" id="revenueReportBody">
                    <div class="text-center py-5">
                        <span class="spinner-border text-success"></span>
                        <p class="mt-3 text-muted">Loading revenue data...</p>
                    </div>
                </div>
                <div class="modal-footer border-top border-success">
                    <div class="d-flex align-items-center me-auto">
                        <span class="text-muted small">Grand Total Revenue: </span>
                        <strong class="ms-2 text-success" id="grandTotalRevenue">₱0.00</strong>
                        <span class="text-muted small ms-4">Total Tickets Sold: </span>
                        <strong class="ms-2 text-warning" id="grandTotalTickets">0</strong>
                    </div>
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <script src="{{ asset('js/movies.js') }}"></script>
@endpush
