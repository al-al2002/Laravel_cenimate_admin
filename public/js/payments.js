// Payments Management JavaScript

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

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
let paymentsCache = [];
let currentPaymentId = null;
let timerInterval = null;

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

async function fetchPayments() {
    const refreshBtn = document.getElementById('refreshBtn');
    const originalText = refreshBtn?.innerHTML;

    if (refreshBtn) {
        refreshBtn.disabled = true;
        refreshBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Refreshing...';
    }

    try {
        const searchTerm = document.getElementById('searchInput').value;
        const params = new URLSearchParams();
        if (searchTerm) params.append('search', searchTerm);

        const response = await fetch(`/admin/payments/list?${params}`);

        if (!response.ok) {
            throw new Error('Failed to fetch payments');
        }

        const payload = await response.json();
        paymentsCache = payload.data || [];

        updateStats(paymentsCache);
        renderPayments(paymentsCache);
    } catch (error) {
        console.error('Error fetching payments:', error);
        showToast('Failed to load pending payments', 'error');
    } finally {
        if (refreshBtn) {
            refreshBtn.disabled = false;
            refreshBtn.innerHTML = originalText;
        }
    }
}

function updateStats(payments) {
    const pending = payments.length;
    const totalAmount = payments.reduce((sum, p) => sum + parseFloat(p.total_amount || 0), 0);

    document.getElementById('statPending').textContent = pending;
    document.getElementById('statTotalAmount').textContent = `₱${totalAmount.toFixed(2)}`;
}

function startTimers() {
    if (timerInterval) {
        clearInterval(timerInterval);
    }

    timerInterval = setInterval(() => {
        paymentsCache.forEach((payment, index) => {
            const timerElement = document.getElementById(`timer-${payment.id}`);
            if (timerElement) {
                const expiresAt = new Date(payment.expires_at);
                const now = new Date();
                const timeRemaining = expiresAt - now;

                if (timeRemaining <= 0) {
                    timerElement.closest('.timer-badge').textContent = 'EXPIRED';
                    return;
                }

                const minutes = Math.floor(timeRemaining / 60000);
                const seconds = Math.floor((timeRemaining % 60000) / 1000);

                timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

                // Update badge class
                const badge = timerElement.closest('.timer-badge');
                if (minutes < 5) {
                    badge.classList.remove('warning');
                    badge.classList.add('danger');
                } else {
                    badge.classList.remove('danger');
                    badge.classList.add('warning');
                }
            }
        });
    }, 1000);
}

function renderPayments(payments) {
    const container = document.getElementById('paymentsList');

    if (payments.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="bi bi-check-circle-fill" style="color: #22c55e;"></i>
                <p>No pending payments</p>
            </div>
        `;
        return;
    }

    container.innerHTML = payments.map(payment => {
        const showtime = new Date(payment.showtime);

        return `
            <div class="payment-card">
                <div class="payment-card-body">
                    <!-- Header -->
                    <div class="payment-header">
                        <div>
                            <div class="payment-reference">Ref: ${payment.payment_reference}</div>
                            <div class="payment-method">${payment.payment_method || 'Unknown'}</div>
                        </div>
                    </div>

                    <!-- Payment Proof -->
                    ${payment.payment_proof_url ? `
                        <div class="payment-proof">
                            <div class="section-title">Payment Proof</div>
                            <div class="proof-container" onclick="showPaymentProof('${payment.payment_proof_url}', '${payment.payment_reference}')">
                                <img src="${payment.payment_proof_url}" alt="Payment Proof"
                                     onerror="this.parentElement.innerHTML='<div class=&quot;no-proof-notice&quot;><i class=&quot;bi bi-broken-image&quot;></i>Failed to load image</div>'">
                                <div class="proof-overlay">
                                    <i class="bi bi-zoom-in"></i>
                                    Tap to expand
                                </div>
                            </div>
                        </div>
                    ` : `
                        <div class="no-proof-notice">
                            <i class="bi bi-exclamation-triangle"></i>
                            No payment screenshot uploaded
                        </div>
                    `}

                    <!-- Details -->
                    <div class="payment-details">
                        <div class="movie-info">
                            <div class="section-title">Movie</div>
                            <div class="movie-title">${payment.movie_title || 'Unknown'}</div>
                            <div class="info-row">
                                <i class="bi bi-calendar-event"></i>
                                <span>${showtime.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} - ${showtime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</span>
                            </div>
                            <div class="info-row">
                                <i class="bi bi-geo-alt"></i>
                                <span>${payment.cinema_hall || 'Unknown'}</span>
                            </div>
                            <div class="info-row">
                                <i class="bi bi-ticket-perforated"></i>
                                <span>${payment.seat_labels || 'Unknown'}</span>
                            </div>
                        </div>

                        <div class="customer-info">
                            <div class="section-title">Customer</div>
                            <div class="customer-name">${payment.user_name || 'Unknown'}</div>
                            <div class="customer-email">${payment.user_email || ''}</div>
                            ${payment.phone_number ? `<div class="customer-phone">${payment.phone_number}</div>` : ''}
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="payment-footer">
                        <div class="total-amount">
                            <span class="amount-label">Total Amount</span>
                            <span class="amount-value">₱${parseFloat(payment.total_amount || 0).toFixed(2)}</span>
                        </div>
                        <div class="payment-actions">
                            <button class="btn btn-sm btn-outline-primary btn-copy" data-reference="${escapeHtml(payment.payment_reference)}" title="Copy Reference">
                                <i class="bi bi-clipboard"></i>
                            </button>
                            <button class="btn btn-danger btn-reject" data-id="${payment.id}" data-reference="${escapeHtml(payment.payment_reference)}" data-amount="${payment.total_amount}">
                                <i class="bi bi-x-circle me-1"></i>Reject
                            </button>
                            <button class="btn btn-success btn-confirm" data-id="${payment.id}" data-reference="${escapeHtml(payment.payment_reference)}" data-amount="${payment.total_amount}">
                                <i class="bi bi-check-circle me-1"></i>Confirm
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    // Add event listeners using event delegation
    container.querySelectorAll('.btn-copy').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            copyReference(btn.dataset.reference);
        });
    });

    container.querySelectorAll('.btn-reject').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            console.log('Reject button clicked', btn.dataset);
            showRejectModal(btn.dataset.id, btn.dataset.reference, parseFloat(btn.dataset.amount));
        });
    });

    container.querySelectorAll('.btn-confirm').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            console.log('Confirm button clicked', btn.dataset);
            showConfirmModal(btn.dataset.id, btn.dataset.reference, parseFloat(btn.dataset.amount));
        });
    });
}

function showPaymentProof(imageUrl, reference) {
    document.getElementById('proofImage').src = imageUrl;
    document.getElementById('proofReference').textContent = `Ref: ${reference}`;
    const modal = new bootstrap.Modal(document.getElementById('paymentProofModal'));
    modal.show();
}

function copyReference(reference) {
    navigator.clipboard.writeText(reference).then(() => {
        showToast('Reference copied!', 'success');
    }).catch(() => {
        showToast('Failed to copy reference', 'error');
    });
}

function showConfirmModal(paymentId, reference, amount) {
    currentPaymentId = paymentId;

    const modalBody = document.getElementById('confirmModalBody');
    modalBody.innerHTML = `
        <p class="text-white-50">Are you sure you want to confirm this payment?</p>
        <div class="mt-3">
            <p class="mb-2"><strong>Reference:</strong> <span class="text-danger">${reference}</span></p>
            <p class="mb-0"><strong>Amount:</strong> <span class="text-white">₱${parseFloat(amount).toFixed(2)}</span></p>
        </div>
    `;

    const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    modal.show();
}

function showRejectModal(paymentId, reference, amount) {
    currentPaymentId = paymentId;

    const modalInfo = document.getElementById('rejectModalInfo');
    modalInfo.innerHTML = `
        <p class="mb-2"><strong>Reference:</strong> <span class="text-danger">${reference}</span></p>
        <p class="mb-0"><strong>Amount:</strong> <span class="text-white">₱${parseFloat(amount).toFixed(2)}</span></p>
    `;

    document.getElementById('rejectionReason').value = '';

    const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
    modal.show();
}

async function confirmPayment() {
    const btn = document.getElementById('confirmPaymentBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

    try {
        const response = await fetch(`/admin/payments/${currentPaymentId}/confirm`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
            },
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || 'Failed to confirm payment');
        }

        const modal = bootstrap.Modal.getInstance(document.getElementById('confirmModal'));
        modal.hide();

        showToast(result.message || 'Payment confirmed successfully!', 'success');
        fetchPayments();
    } catch (error) {
        console.error('Error:', error);
        showToast(error.message || 'Failed to confirm payment', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

async function rejectPayment() {
    const reason = document.getElementById('rejectionReason').value.trim();

    if (!reason) {
        showToast('Please provide a rejection reason', 'error');
        return;
    }

    const btn = document.getElementById('rejectPaymentBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

    try {
        const response = await fetch(`/admin/payments/${currentPaymentId}/reject`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ reason }),
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || 'Failed to reject payment');
        }

        const modal = bootstrap.Modal.getInstance(document.getElementById('rejectModal'));
        modal.hide();

        showToast(result.message || 'Payment rejected successfully', 'success');
        fetchPayments();
    } catch (error) {
        console.error('Error:', error);
        showToast(error.message || 'Failed to reject payment', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

async function releaseExpiredSeats() {
    const btn = document.getElementById('releaseExpiredBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Releasing...';

    try {
        const response = await fetch('/admin/payments/release-expired', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
            },
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || 'Failed to release expired seats');
        }

        showToast(result.message || 'Expired seats released', 'success');
        fetchPayments();
    } catch (error) {
        console.error('Error:', error);
        showToast(error.message || 'Failed to release expired seats', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

// Event listeners
const debouncedFetch = debounce(fetchPayments, 300);
document.getElementById('searchInput').addEventListener('input', debouncedFetch);
document.getElementById('refreshBtn').addEventListener('click', fetchPayments);
document.getElementById('confirmPaymentBtn').addEventListener('click', confirmPayment);
document.getElementById('rejectPaymentBtn').addEventListener('click', rejectPayment);
document.getElementById('releaseExpiredBtn').addEventListener('click', releaseExpiredSeats);

// Auto-refresh every 30 seconds
setInterval(fetchPayments, 30000);

// Initial load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        if ('requestIdleCallback' in window) {
            requestIdleCallback(() => fetchPayments(), { timeout: 1000 });
        } else {
            setTimeout(fetchPayments, 100);
        }
    });
} else {
    if ('requestIdleCallback' in window) {
        requestIdleCallback(() => fetchPayments(), { timeout: 1000 });
    } else {
        setTimeout(fetchPayments, 100);
    }
}
