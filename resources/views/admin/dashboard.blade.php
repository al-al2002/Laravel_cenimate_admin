    @extends('layouts.admin')

    @section('styles')
        <link rel="preconnect" href="https://cdn.jsdelivr.net">
        <link href="{{ asset('css/dashboard.css') }}" rel="stylesheet">
    @endsection

    @section('content')
        <div class="container-fluid dashboard-container">
            <h1 class="dashboard-title">Dashboard</h1>

            <div class="row g-3 mt-2">
                <div class="col-md-3">
                    <div class="card stat-card text-white mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Total Users</h5>
                            <p class="card-text">{{ $totalUsers ?? 0 }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card text-white mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Total Movies</h5>
                            <p class="card-text">{{ $totalMovies ?? 0 }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card text-white mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Total Reservations</h5>
                            <p class="card-text">{{ $totalReservations ?? 0 }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card text-white mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Pending Payments</h5>
                            <p class="card-text">{{ $pendingPayments ?? 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Revenue Section -->
            <div class="row g-3 mt-3">
                <div class="col-12">
                    <div class="card revenue-card text-white">
                        <div class="card-body">
                            <div class="revenue-header">
                                <div>
                                    <h5 class="revenue-label">Total Revenue</h5>
                                    <p class="revenue-amount" id="totalRevenue">₱{{ number_format($totalRevenue ?? 0, 2) }}
                                    </p>
                                </div>
                                <div class="revenue-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48"
                                        fill="currentColor" viewBox="0 0 24 24">
                                        <text x="50%" y="50%" dominant-baseline="central" text-anchor="middle"
                                            font-size="20" font-weight="bold" font-family="Arial, sans-serif">₱</text>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Revenue Chart Section -->
            <div class="row g-3 mt-3">
                <div class="col-12">
                    <div class="card chart-card text-white">
                        <div class="card-body">
                            <div class="chart-header">
                                <h5 class="chart-title">Revenue Per Day</h5>
                                <span class="chart-subtitle">Last 14 days</span>
                            </div>
                            <div class="chart-container">
                                <canvas id="revenueChart"></canvas>
                            </div>
                            <div class="chart-loading" id="chartLoading">
                                <span class="spinner-border spinner-border-sm text-danger me-2"></span>
                                Loading chart data...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
        <script src="{{ asset('js/dashboard.js') }}" defer></script>
    @endpush
