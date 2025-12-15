// Reservations Management JavaScript

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
let reservationsCache = [];
let currentReservationId = null;

function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `custom-toast ${type}`;

    const icon = type === 'success' ? '✓' : '✕';
    toast.innerHTML = `
        <span class="custom-toast-icon">${icon}</span>
        <span class="custom-toast-message">${message}</span>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease-out reverse';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

async function fetchReservations() {
    const refreshBtn = document.getElementById('refreshBtn');
    const originalText = refreshBtn?.innerHTML;

    if (refreshBtn) {
        refreshBtn.disabled = true;
        refreshBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Refreshing...';
    }

    try {
        const searchTerm = document.getElementById('searchInput').value;
        const statusFilter = document.getElementById('filterStatus').value;

        const params = new URLSearchParams();
        if (searchTerm) params.append('search', searchTerm);
        // Send status filter to backend to handle tickets separately
        if (statusFilter !== 'all') params.append('status', statusFilter);

        // Fetch data and stats in parallel for faster loading
        const [dataResponse, statsResponse] = await Promise.all([
            fetch(`/admin/reservations/list?${params}`),
            fetch('/admin/reservations/stats')
        ]);

        if (!dataResponse.ok) {
            throw new Error('Failed to fetch reservations');
        }

        const payload = await dataResponse.json();
        const allReservations = payload.data || [];

        // Update stats from parallel fetch
        if (statsResponse.ok) {
            const stats = await statsResponse.json();
            document.getElementById('statTotal').textContent = stats.total || 0;
            document.getElementById('statPending').textContent = stats.pending || 0;
            document.getElementById('statTickets').textContent = stats.tickets || 0;
            document.getElementById('statCancelled').textContent = stats.cancelled || 0;
        }

        // For tickets view, data already filtered by backend
        let filteredReservations = allReservations;

        // Only apply client-side filtering for reservations (not tickets)
        if (statusFilter !== 'all' && statusFilter !== 'active') {
            filteredReservations = allReservations.filter(r => {
                // Check if expired
                const expiresAt = r.expires_at ? new Date(r.expires_at) : null;
                const isExpired = expiresAt && new Date() > expiresAt && r.status === 'pending';

                // If filtering for pending, exclude expired ones
                if (statusFilter === 'pending') {
                    return r.status === statusFilter && !isExpired;
                }

                return r.status === statusFilter;
            });
        }

        reservationsCache = filteredReservations;
        renderReservations(filteredReservations);
    } catch (error) {
        console.error('Error fetching reservations:', error);
        showToast('Failed to load reservations', 'error');
    } finally {
        if (refreshBtn) {
            refreshBtn.disabled = false;
            refreshBtn.innerHTML = originalText;
        }
    }
}

async function fetchStats() {
    try {
        // Single optimized API call for all stats
        const response = await fetch('/admin/reservations/stats');
        const stats = await response.json();

        document.getElementById('statTotal').textContent = stats.total || 0;
        document.getElementById('statPending').textContent = stats.pending || 0;
        document.getElementById('statTickets').textContent = stats.tickets || 0;
        document.getElementById('statCancelled').textContent = stats.cancelled || 0;
    } catch (error) {
        console.error('Error fetching stats:', error);
    }
}

function renderReservations(reservations) {
    const container = document.getElementById('reservationsList');

    if (reservations.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>No reservations found</p>
            </div>
        `;
        return;
    }

    container.innerHTML = reservations.map(reservation => {
        // Use showtime_formatted if available (Manila timezone), fallback to showtime
        const showtimeStr = reservation.showtime_formatted || reservation.showtime;
        const showtime = new Date(showtimeStr);
        const expiresAt = reservation.expires_at ? new Date(reservation.expires_at) : null;
        const isExpired = expiresAt && new Date() > expiresAt && reservation.status === 'pending';

        let statusClass = `status-${reservation.status}`;
        let statusText = reservation.status.toUpperCase();

        if (isExpired) {
            statusClass = 'status-expired';
            statusText = 'EXPIRED';
        }

        // Handle ticket status display
        if (reservation.status === 'active') {
            // Check if ticket's showtime has ended
            if (reservation.is_expired) {
                statusClass = 'status-expired';
                statusText = 'EXPIRED';
            } else {
                statusClass = 'status-confirmed';
                statusText = 'ACTIVE';
            }
        }

        return `
            <div class="reservation-card" onclick="showDetails(${reservation.id})">
                <div class="reservation-card-header">
                    <img src="${reservation.movie_poster || '/images/placeholder-movie.png'}"
                         alt="${reservation.movie_title}"
                         class="movie-poster"
                         onerror="this.src='/images/placeholder-movie.png'">
                    <div class="reservation-info">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="reservation-title">${reservation.movie_title}</div>
                            <span class="status-badge ${statusClass}">${statusText}</span>
                        </div>
                        <div class="reservation-code">${reservation.reservation_code}</div>
                        <div class="reservation-details">
                            <div class="detail-item">
                                <i class="bi bi-calendar"></i>
                                <span>${showtime.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                            </div>
                            <div class="detail-item">
                                <i class="bi bi-clock"></i>
                                <span>${showtime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })}</span>
                            </div>
                            <div class="detail-item">
                                <i class="bi bi-tv"></i>
                                <span>${reservation.cinema_hall}</span>
                            </div>
                            <div class="detail-item">
                                <i class="bi bi-ticket-perforated"></i>
                                <span>${reservation.seat_labels}</span>
                            </div>
                            <div class="detail-item">
                                <i class="bi bi-cash"></i>
                                <span>₱${parseFloat(reservation.total_amount).toFixed(2)}</span>
                            </div>
                            ${expiresAt && reservation.status === 'pending' && !isExpired ? `
                                <div class="detail-item" style="color: #fb923c;">
                                    <i class="bi bi-hourglass-split"></i>
                                    <span>Expires: ${expiresAt.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })}</span>
                                </div>
                            ` : ''}
                            ${reservation.status === 'active' && reservation.movie_end_time && !reservation.is_expired ? `
                                <div class="detail-item" style="color: #6b7280;">
                                    <i class="bi bi-film"></i>
                                    <span>Ends: ${new Date(reservation.movie_end_time).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })}</span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                    <i class="bi bi-chevron-right" style="color: #94a3b8;"></i>
                </div>
            </div>
        `;
    }).join('');
}

async function showDetails(reservationId) {
    try {
        const response = await fetch(`/admin/reservations/${reservationId}`);

        if (!response.ok) {
            throw new Error('Failed to fetch reservation details');
        }

        const payload = await response.json();
        const reservation = payload.data;

        // Use showtime_formatted if available (Manila timezone), fallback to showtime
        const showtimeStr = reservation.showtime_formatted || reservation.showtime;
        const showtime = new Date(showtimeStr);
        const expiresAt = reservation.expires_at ? new Date(reservation.expires_at) : null;
        const createdAt = new Date(reservation.created_at);
        const isExpired = expiresAt && new Date() > expiresAt && reservation.status === 'pending';

        const modalBody = document.getElementById('modalBody');
        modalBody.innerHTML = `
            <div class="text-center mb-4">
                <img src="${reservation.movie_poster || '/images/placeholder-movie.png'}"
                     alt="${reservation.movie_title}"
                     style="max-height: 300px; border-radius: 8px;"
                     onerror="this.src='/images/placeholder-movie.png'">
            </div>
            <div class="detail-row">
                <div class="detail-label">Movie</div>
                <div class="detail-value">${reservation.movie_title}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Reservation Code</div>
                <div class="detail-value">${reservation.reservation_code}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Showtime</div>
                <div class="detail-value">${showtime.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })} - ${showtime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Cinema</div>
                <div class="detail-value">${reservation.cinema_hall}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Seats</div>
                <div class="detail-value">${reservation.seat_labels}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Total Amount</div>
                <div class="detail-value">₱${parseFloat(reservation.total_amount).toFixed(2)}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Payment Method</div>
                <div class="detail-value">${reservation.payment_method || 'N/A'}</div>
            </div>
            ${reservation.payment_reference ? `
                <div class="detail-row">
                    <div class="detail-label">Payment Reference</div>
                    <div class="detail-value">${reservation.payment_reference}</div>
                </div>
            ` : ''}
            ${expiresAt ? `
                <div class="detail-row">
                    <div class="detail-label">Expires At</div>
                    <div class="detail-value">${expiresAt.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })} - ${expiresAt.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })}</div>
                </div>
            ` : ''}
            <div class="detail-row">
                <div class="detail-label">Status</div>
                <div class="detail-value">${reservation.is_expired ? 'EXPIRED' : (isExpired ? 'EXPIRED' : reservation.status.toUpperCase())}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Created</div>
                <div class="detail-value">${createdAt.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })} - ${createdAt.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</div>
            </div>
        `;

        const modalFooter = document.getElementById('modalFooter');
        modalFooter.innerHTML = `
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            ${reservation.status === 'pending' && !isExpired ? `
                <button type="button" class="btn btn-danger" onclick="cancelReservation(${reservation.id})">Cancel</button>
                <button type="button" class="btn btn-success" onclick="showApproveModal(${reservation.id})">Approve Payment</button>
            ` : ''}
        `;

        const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
        modal.show();
    } catch (error) {
        console.error('Error fetching details:', error);
        showToast('Failed to load reservation details', 'error');
    }
}

function showApproveModal(reservationId) {
    currentReservationId = reservationId;
    document.getElementById('paymentReference').value = '';

    // Close details modal
    const detailsModal = bootstrap.Modal.getInstance(document.getElementById('detailsModal'));
    if (detailsModal) detailsModal.hide();

    // Show approve modal
    const approveModal = new bootstrap.Modal(document.getElementById('approveModal'));
    approveModal.show();
}

async function approveReservation() {
    const paymentReference = document.getElementById('paymentReference').value.trim();

    if (!paymentReference) {
        showToast('Please enter a payment reference number', 'error');
        return;
    }

    const btn = document.getElementById('confirmApproveBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

    try {
        const response = await fetch(`/admin/reservations/${currentReservationId}/approve`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ payment_reference: paymentReference }),
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || 'Failed to approve reservation');
        }

        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('approveModal'));
        modal.hide();

        showToast(result.message || 'Reservation approved successfully!', 'success');
        fetchReservations();
    } catch (error) {
        console.error('Error:', error);
        showToast(error.message || 'Failed to approve reservation', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

async function cancelReservation(reservationId) {
    if (!confirm('Are you sure you want to cancel this reservation? The seats will be released.')) {
        return;
    }

    try {
        const response = await fetch(`/admin/reservations/${reservationId}/cancel`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
            },
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || 'Failed to cancel reservation');
        }

        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('detailsModal'));
        if (modal) modal.hide();

        showToast(result.message || 'Reservation cancelled successfully', 'success');
        fetchReservations();
    } catch (error) {
        console.error('Error:', error);
        showToast(error.message || 'Failed to cancel reservation', 'error');
    }
}

function filterByStatus(status) {
    const filterSelect = document.getElementById('filterStatus');
    filterSelect.value = status;
    fetchReservations();
}

// Event listeners
const debouncedFetch = debounce(fetchReservations, 300);
document.getElementById('searchInput').addEventListener('input', debouncedFetch);
document.getElementById('filterStatus').addEventListener('change', fetchReservations);
document.getElementById('refreshBtn').addEventListener('click', fetchReservations);
document.getElementById('confirmApproveBtn').addEventListener('click', approveReservation);

// Initial load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        if ('requestIdleCallback' in window) {
            requestIdleCallback(() => fetchReservations(), { timeout: 1000 });
        } else {
            setTimeout(fetchReservations, 100);
        }
    });
} else {
    if ('requestIdleCallback' in window) {
        requestIdleCallback(() => fetchReservations(), { timeout: 1000 });
    } else {
        setTimeout(fetchReservations, 100);
    }
}
