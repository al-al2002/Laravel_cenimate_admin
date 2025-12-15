@extends('layouts.admin')

@section('styles')
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://isqzlkxwpotvjkymirvn.supabase.co">
    <link href="{{ asset('css/payments.css') }}" rel="stylesheet">
@endsection

@section('content')
    <div class="container-fluid payments-page">
        <div class="hero-panel mb-4 text-white">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1>Pending Payments</h1>
                    <p>Review and approve customer payment submissions</p>
                </div>
                <div class="col-lg-4 text-end">
                    <button class="btn btn-outline-light" id="releaseExpiredBtn">
                        <i class="bi bi-arrow-clockwise me-2"></i>Release Expired Reservations
                    </button>
                </div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="card mb-4 filters text-white">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label" for="searchInput">Search by reference number</label>
                        <input type="text" id="searchInput" class="form-control bg-slate-800 text-white"
                            placeholder="Search by reference number..." autocomplete="off">
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-outline-light w-100" id="refreshBtn">
                            <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="stat-pill">
                    <i class="bi bi-clock-history"></i>
                    <div>
                        <strong id="statPending">0</strong>
                        <span>Pending Payments</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat-pill">
                    <i class="bi bi-cash-stack"></i>
                    <div>
                        <strong id="statTotalAmount">₱0.00</strong>
                        <span>Total Pending Amount</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payments List -->
        <div id="paymentsList"></div>
    </div>

    <!-- Payment Proof Modal -->
    <div class="modal fade" id="paymentProofModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-slate-900 text-white">
                <div class="modal-header border-0" style="background: #ef4444;">
                    <div>
                        <h5 class="modal-title">Payment Proof</h5>
                        <small id="proofReference" class="text-white-50"></small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center" style="min-height: 400px;">
                    <img id="proofImage" src="" alt="Payment Proof"
                        style="max-width: 100%; max-height: 600px; border-radius: 8px;">
                </div>
                <div class="modal-footer border-0">
                    <small class="text-muted">Pinch to zoom • Verify reference matches the screenshot</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirm Payment Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-slate-900 text-white">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Confirm Payment?</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="confirmModalBody">
                    <!-- Loaded dynamically -->
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmPaymentBtn">Confirm Payment</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Payment Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-slate-900 text-white">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Reject Payment?</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="rejectModalInfo" class="mb-3"></div>
                    <label for="rejectionReason" class="form-label">Please provide a reason for rejection:</label>
                    <textarea id="rejectionReason" class="form-control bg-slate-800 text-white" rows="3"
                        placeholder="e.g., Invalid reference number, Amount mismatch..."></textarea>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="rejectPaymentBtn">Reject Payment</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>
@endsection

@push('scripts')
    <script src="{{ asset('js/payments.js') }}"></script>
@endpush
