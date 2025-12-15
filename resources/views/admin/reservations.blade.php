@extends('layouts.admin')

@section('styles')
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://isqzlkxwpotvjkymirvn.supabase.co">
    <link href="{{ asset('css/reservations.css') }}" rel="stylesheet">
@endsection

@section('content')
    <div class="container-fluid reservations-page">
        <div class="hero-panel mb-4 text-white">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1>Reservations Management</h1>
                    <p>View and manage ticket reservations and payment approvals</p>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="card mb-4 filters text-white">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label" for="searchInput">Search by reservation code or reference</label>
                        <input type="text" id="searchInput" class="form-control bg-slate-800 text-white"
                            placeholder="Search reservations..." autocomplete="off">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="filterStatus">Filter by Status</label>
                        <select id="filterStatus" class="form-select bg-slate-800 text-white">
                            <option value="all">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="active">Tickets</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-light w-100" id="refreshBtn">
                            <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-pill" style="cursor: pointer;" onclick="filterByStatus('all')">
                    <i class="bi bi-list-check"></i>
                    <div>
                        <strong id="statTotal">0</strong>
                        <span>Total Reservations</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-pill" style="cursor: pointer;" onclick="filterByStatus('pending')">
                    <i class="bi bi-clock-history"></i>
                    <div>
                        <strong id="statPending">0</strong>
                        <span>Pending</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-pill" style="cursor: pointer;" onclick="filterByStatus('active')">
                    <i class="bi bi-ticket-perforated"></i>
                    <div>
                        <strong id="statTickets">0</strong>
                        <span>Tickets</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-pill" style="cursor: pointer;" onclick="filterByStatus('cancelled')">
                    <i class="bi bi-x-circle"></i>
                    <div>
                        <strong id="statCancelled">0</strong>
                        <span>Cancelled</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reservations List -->
        <div id="reservationsList"></div>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-slate-900 text-white">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Reservation Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Loaded dynamically -->
                </div>
                <div class="modal-footer border-0" id="modalFooter">
                    <!-- Loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-slate-900 text-white">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Approve Payment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Enter the payment reference number to approve this reservation and generate the ticket.</p>
                    <div class="mb-3">
                        <label for="paymentReference" class="form-label">Payment Reference Number</label>
                        <input type="text" class="form-control bg-slate-800 text-white" id="paymentReference"
                            placeholder="e.g., GCash123456789" required>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmApproveBtn">Approve & Generate Ticket</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <script src="{{ asset('js/reservations.js') }}"></script>
@endpush
