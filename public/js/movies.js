// Movies Page JavaScript

// Debounce utility for search input
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

const SUPABASE_URL = 'https://isqzlkxwpotvjkymirvn.supabase.co';
const SUPABASE_ANON_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImlzcXpsa3h3cG90dmpreW1pcnZuIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjMyODI4NjcsImV4cCI6MjA3ODg1ODg2N30.X94ifDA-wrWh74QutmAx8nYXU_irwsna8rmAjeH1l6Y';
const { createClient } = supabase;
const supabaseClient = createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const movieModal = new bootstrap.Modal(document.getElementById('movieModal'));
const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
const endedShowtimesModal = new bootstrap.Modal(document.getElementById('endedShowtimesModal'));
const movieForm = document.getElementById('movieForm');
const movieModalLabel = document.getElementById('movieModalLabel');
let currentMovieId = null;
let moviesCache = [];
let allMoviesCache = []; // Master cache for local filtering
let endedMoviesCache = [];
let pendingDeleteId = null;
let pendingDeleteTitle = null;
let lastFetchTime = 0;
const CACHE_DURATION = 60000; // 1 minute cache

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

function showConfirm(title, message) {
    return new Promise((resolve) => {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content bg-black border border-danger border-opacity-50 text-white">
                    <div class="modal-header border-bottom border-danger">
                        <h5 class="modal-title">${title}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>${message}</p>
                    </div>
                    <div class="modal-footer border-top border-danger">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmBtn">Delete</button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);

        modal.querySelector('#confirmBtn').addEventListener('click', () => {
            resolve({ confirmed: true, modal: bsModal });
        });

        modal.addEventListener('hidden.bs.modal', () => {
            setTimeout(() => modal.remove(), 150);
            if (!modal._confirmed) {
                resolve({ confirmed: false, modal: null });
            }
        });

        modal.querySelector('#confirmBtn').addEventListener('click', () => {
            modal._confirmed = true;
        });

        bsModal.show();
    });
}

async function fetchMovies(forceRefresh = false) {
    const searchBtn = document.getElementById('searchBtn');
    const originalText = searchBtn?.innerHTML;
    const now = Date.now();

    // Use local filtering if cache is valid and not forcing refresh
    if (!forceRefresh && allMoviesCache.length > 0 && (now - lastFetchTime) < CACHE_DURATION) {
        filterAndRenderMovies();
        return;
    }

    if (searchBtn) {
        searchBtn.disabled = true;
        searchBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Refreshing...';
    }

    try {
        // Fetch ALL movies (no filters) and stats in parallel
        const [moviesResponse, statsResponse] = await Promise.all([
            fetch('/admin/movies/list'),
            fetch('/admin/movies/stats')
        ]);

        if (!moviesResponse.ok) {
            throw new Error('Failed to fetch movies');
        }

        const payload = await moviesResponse.json();
        allMoviesCache = payload.data; // Store all movies
        lastFetchTime = now;

        // Apply local filtering and render
        filterAndRenderMovies();

        // Update ended showtimes count
        if (statsResponse.ok) {
            const statsData = await statsResponse.json();
            document.getElementById('statEnded').textContent = statsData.ended_showtimes_count || 0;
        }
    } catch (error) {
        console.error('Error fetching movies:', error);
        showToast('Failed to load movies', 'error');
    } finally {
        if (searchBtn) {
            searchBtn.disabled = false;
            searchBtn.innerHTML = originalText;
        }
    }
}

// Local filtering function - no API calls
function filterAndRenderMovies() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const genre = document.getElementById('filterGenre').value;
    const status = document.getElementById('filterStatus').value;

    let filtered = [...allMoviesCache];

    // Apply search filter
    if (search) {
        filtered = filtered.filter(movie =>
            movie.title && movie.title.toLowerCase().includes(search)
        );
    }

    // Apply genre filter
    if (genre && genre !== 'All') {
        filtered = filtered.filter(movie => {
            if (Array.isArray(movie.genre)) {
                return movie.genre.includes(genre);
            }
            return movie.genre === genre;
        });
    }

    // Apply status filter
    if (status) {
        if (status === 'Active') {
            filtered = filtered.filter(movie => movie.is_active);
        } else if (status === 'Inactive') {
            filtered = filtered.filter(movie => !movie.is_active);
        }
    }

    moviesCache = filtered;
    updateStats(allMoviesCache); // Always use ALL movies for stats
    renderMovies(filtered);
}

function updateStats(movies) {
    const active = movies.filter((movie) => movie.is_active).length;
    const inactive = movies.length - active;
    const rating = movies.length ? (movies.reduce((sum, movie) => sum + Number(movie.rating || 0), 0) / movies.length).toFixed(1) : '0.0';

    document.getElementById('statActive').textContent = active;
    document.getElementById('statInactive').textContent = inactive;
    document.getElementById('statRating').textContent = rating;
}

function renderMovies(movies) {
    const container = document.getElementById('moviesList');
    if (!movies.length) {
        container.innerHTML = `
            <div class="col-12">
                <div class="card movie-card text-center text-white">
                    <div class="card-body">
                        <p class="mb-0">No movies found. Use the button above to add one.</p>
                    </div>
                </div>
            </div>
        `;
        return;
    }

    // Optimized: Build HTML string once, no console.logs, simplified URL logic
    container.innerHTML = movies.map((movie) => {
        // Simplified poster URL logic - no logging, faster checks
        let poster = 'https://via.placeholder.com/130x180/2a2a2a/ffffff?text=No+Image';
        if (movie.poster_url) {
            const url = movie.poster_url;
            if (url.startsWith('http://') || url.startsWith('https://')) {
                poster = url;
            } else {
                poster = url.startsWith('/') ? window.location.origin + url : window.location.origin + '/' + url;
            }
        }
        const genreLabel = Array.isArray(movie.genre) ? movie.genre.join(', ') : movie.genre;
        return `
            <div class="col-xl-6">
                <div class="card movie-card text-white h-100">
                    <div class="card-body d-flex gap-3 flex-column flex-lg-row">
                        <div style="width: 130px; flex-shrink: 0; background: #2a2a2a; border-radius: 8px; overflow: hidden;">
                            <img src="${poster}" class="w-100" alt="${movie.title}" style="height: 180px; object-fit: cover; display: block;" onerror="this.src='https://via.placeholder.com/130x180/2a2a2a/ffffff?text=No+Image'">
                        </div>
                        <div class="grow">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h5 class="card-title mb-1">${movie.title}</h5>
                                    <p class="mb-1 card-text-transparent small">${genreLabel} • ${movie.language} • ${movie.duration_minutes} min</p>
                                </div>
                                <span class="badge badge-pill ${movie.is_active ? 'bg-success' : 'bg-secondary'}">
                                    ${movie.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </div>
                            <p class="card-text card-text-transparent mb-2" style="max-height: 4rem; overflow: hidden;">
                                ${movie.description ?? ''}
                            </p>
                            <p class="text-muted-light small mb-3">
                                <i class="bi bi-star-fill text-warning"></i>
                                ${movie.rating}/10 • Released ${new Date(movie.release_date).toLocaleDateString()}
                            </p>
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn btn-sm btn-outline-light movie-edit-btn" data-movie-id="${movie.id}">Edit</button>
                                <button class="btn btn-sm ${movie.is_active ? 'btn-warning' : 'btn-success'} movie-toggle-btn" data-movie-id="${movie.id}">
                                    ${movie.is_active ? 'Deactivate' : 'Activate'}
                                </button>
                                <button class="btn btn-sm btn-danger movie-delete-btn" data-movie-id="${movie.id}" data-movie-title="${movie.title.replace(/"/g, '&quot;')}">Delete</button>
                                <button class="btn btn-sm btn-info movie-review-btn" data-movie-id="${movie.id}">Review</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function showAlert(message, type = 'success') {
    const container = document.getElementById('alertContainer');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
    container.appendChild(alert);
    setTimeout(() => alert.classList.remove('show'), 4000);
}

// Debounce search for local filtering (instant, no API)
const debouncedLocalFilter = debounce(filterAndRenderMovies, 150);
document.getElementById('searchInput').addEventListener('input', debouncedLocalFilter);
document.getElementById('filterGenre').addEventListener('change', filterAndRenderMovies);
document.getElementById('filterStatus').addEventListener('change', () => {
    // Clear active state on stat cards when manually changing filter
    document.querySelectorAll('.stat-pill-clickable').forEach(card => card.classList.remove('active'));
    const status = document.getElementById('filterStatus').value;
    if (status === 'Active') {
        document.getElementById('activeMoviesCard').classList.add('active');
    } else if (status === 'Inactive') {
        document.getElementById('disabledMoviesCard').classList.add('active');
    }
    filterAndRenderMovies();
});
document.getElementById('searchBtn').addEventListener('click', () => {
    // Clear active state on refresh
    document.querySelectorAll('.stat-pill-clickable').forEach(card => card.classList.remove('active'));
    document.getElementById('filterStatus').value = 'All';
    fetchMovies(true);
});
document.getElementById('addMovieBtn').addEventListener('click', () => openMovieForm());

// Event delegation for movie action buttons
document.getElementById('moviesList').addEventListener('click', (e) => {
    const target = e.target;

    if (target.classList.contains('movie-edit-btn')) {
        const movieId = target.dataset.movieId;
        openMovieFormById(movieId);
    } else if (target.classList.contains('movie-toggle-btn')) {
        const movieId = target.dataset.movieId;
        toggleStatus(movieId, target);
    } else if (target.classList.contains('movie-delete-btn')) {
        const movieId = target.dataset.movieId;
        const movieTitle = target.dataset.movieTitle;
        deleteMovie(movieId, movieTitle, target);
    } else if (target.classList.contains('movie-review-btn')) {
        const movieId = target.dataset.movieId;
        openReviewModal(movieId);
    }
});

// Review Modal HTML (append to body if not present)
function ensureReviewModal() {
    if (!document.getElementById('reviewModal')) {
        const modal = document.createElement('div');
        modal.innerHTML = `
        <div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content bg-dark text-white">
                    <div class="modal-header">
                        <h5 class="modal-title" id="reviewModalTitle">Movie Reviews</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="reviewModalBody">
                        <div class="text-center py-4"><span class="spinner-border text-info"></span></div>
                    </div>
                </div>
            </div>
        </div>`;
        document.body.appendChild(modal.firstElementChild);
    }
}

// Open Review Modal and fetch reviews
async function openReviewModal(movieId) {
    ensureReviewModal();
    const modal = new bootstrap.Modal(document.getElementById('reviewModal'));
    const modalBody = document.getElementById('reviewModalBody');
    modalBody.innerHTML = '<div class="text-center py-4 text-white"><span class="spinner-border text-info"></span> Loading reviews...</div>';
    modal.show();
    try {
        // set modal title from cached movies if available
        const movie = allMoviesCache.find(m => m.id === movieId);
        const titleEl = document.getElementById('reviewModalTitle');
        if (titleEl) titleEl.textContent = movie ? `${movie.title} — Reviews` : 'Movie Reviews';

        const response = await fetch(`/api/v1/movies/${movieId}/reviews`);
        if (!response.ok) throw new Error('Failed to fetch reviews');
        const result = await response.json();
        const reviews = result.data || [];
        if (!Array.isArray(reviews) || reviews.length === 0) {
            modalBody.innerHTML = `
                <div class="text-center py-5 text-white-50">
                    <div style="font-size:18px; font-weight:600; color:#fff;">No reviews yet</div>
                    <div class="small mt-2 text-white-50">This movie has no reviews.</div>
                </div>`;
            return;
        }
        modalBody.innerHTML = reviews.map(r => `
            <div class="border-bottom border-secondary py-2">
                <div class="d-flex align-items-center mb-1">
                    <strong class="me-2">${r.user?.full_name || 'Unknown User'}</strong>
                    <span class="badge bg-warning text-dark me-2">${r.rating}/10</span>
                    <span class="text-muted small">${(r.created_at && !isNaN(new Date(r.created_at).getTime())) ? new Date(r.created_at).toLocaleString() : ''}</span>
                </div>
                <div class="small text-white mt-1">${r.comment ? (r.comment.replace(/</g, '&lt;').replace(/\n/g, '<br/>')) : '<span class="text-white-50">No comment</span>'}</div>
            </div>
        `).join('');
    } catch (error) {
        console.error('Review load error:', error);
        modalBody.innerHTML = '<div class="text-center py-4" style="color:#f8d7da">Failed to load reviews.</div>';
    }
}

function openMovieForm(movie = null) {
    currentMovieId = movie?.id ?? null;
    movieModalLabel.textContent = movie ? 'Edit Movie' : 'Add Movie';
    movieForm.reset();

    // Handle poster preview
    const posterPreview = document.getElementById('currentPosterPreview');
    const posterPreviewImg = document.getElementById('posterPreviewImg');

    if (movie) {
        movieForm.title.value = movie.title || '';
        // Genre can be an array or string - handle both
        const genreValue = Array.isArray(movie.genre) ? movie.genre[0] : movie.genre;
        movieForm.genre.value = genreValue || '';
        movieForm.language.value = movie.language || '';
        movieForm.duration_minutes.value = movie.duration_minutes || '';
        movieForm.rating.value = movie.rating || '';
        movieForm.description.value = movie.description || '';
        movieForm.trailer_url.value = movie.trailer_url || '';
        movieForm.cast.value = movie.cast || '';
        // Handle different date formats - ensure YYYY-MM-DD format for input
        if (movie.release_date) {
            const dateStr = movie.release_date.split('T')[0]; // Handle ISO format
            movieForm.release_date.value = dateStr;
        }
        movieForm.is_active.checked = movie.is_active;

        // Show poster preview if movie has a poster
        if (movie.poster_url && posterPreview && posterPreviewImg) {
            posterPreviewImg.src = movie.poster_url;
            posterPreview.style.display = 'block';
        } else if (posterPreview) {
            posterPreview.style.display = 'none';
        }
    } else {
        movieForm.is_active.checked = true;
        movieForm.release_date.value = new Date().toISOString().split('T')[0];
        // Hide poster preview for new movies
        if (posterPreview) {
            posterPreview.style.display = 'none';
        }
    }

    movieModal.show();
}

movieForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const submitBtn = movieForm.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

    const formData = new FormData(movieForm);
    const posterFile = formData.get('poster');

    if (posterFile && posterFile.size > 0) {
        try {
            const fileExt = posterFile.name.split('.').pop();
            const fileName = `movies/poster_${Date.now()}.${fileExt}`;

            const { data, error } = await supabaseClient.storage
                .from('movie-posters')
                .upload(fileName, posterFile, {
                    cacheControl: '3600',
                    upsert: false
                });

            if (error) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                showToast('Failed to upload poster: ' + error.message, 'error');
                return;
            }

            const { data: { publicUrl } } = supabaseClient.storage
                .from('movie-posters')
                .getPublicUrl(fileName);

            formData.delete('poster');
            formData.set('poster_url', publicUrl);
        } catch (error) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            showToast('Failed to upload poster: ' + error.message, 'error');
            return;
        }
    } else {
        formData.delete('poster');
    }

    formData.set('is_active', movieForm.is_active.checked ? '1' : '0');

    const endpoint = currentMovieId ? `/admin/movies/${currentMovieId}` : '/admin/movies';

    if (currentMovieId) {
        formData.append('_method', 'PUT');
    }

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
            body: formData,
        });

        if (!response.ok) {
            hideLoading();
            const error = await response.json();
            showToast(error.message || 'Unable to save movie. Please check the fields.', 'error');
            return;
        }

        const result = await response.json();

        if (currentMovieId) {
            // Update in both caches
            const index = allMoviesCache.findIndex(m => m.id === currentMovieId);
            if (index !== -1) allMoviesCache[index] = result.data;
            const filteredIndex = moviesCache.findIndex(m => m.id === currentMovieId);
            if (filteredIndex !== -1) moviesCache[filteredIndex] = result.data;
        } else {
            // Add to master cache and re-filter
            allMoviesCache.unshift(result.data);
        }

        filterAndRenderMovies();

        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        showToast(`Movie ${currentMovieId ? 'updated' : 'created'} successfully!`, 'success');
        movieModal.hide();
        movieForm.reset();
    } catch (error) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        showToast('An error occurred. Please try again.', 'error');
    }
});

async function deleteMovie(id, title, deleteBtn) {
    const modalResult = await showConfirm('Delete Movie', `Are you sure you want to delete "${title}"? This cannot be undone.`);

    if (!modalResult.confirmed) return;

    // Show loading in modal
    const confirmBtn = document.getElementById('confirmBtn');
    const modalBody = modalResult.modal._element.querySelector('.modal-body');

    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Deleting...';
    }

    if (modalBody) {
        modalBody.innerHTML = '<div class="text-center"><span class="spinner-border text-danger me-2"></span>Deleting movie...</div>';
    }

    const originalText = deleteBtn.innerHTML;
    deleteBtn.disabled = true;
    deleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    try {
        const response = await fetch(`/admin/movies/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            // Optimized: Remove from both caches
            allMoviesCache = allMoviesCache.filter(m => m.id !== id);
            filterAndRenderMovies();

            // Show success toast and hide modal
            showToast('Movie deleted successfully', 'success');
            if (modalResult.modal) {
                modalResult.modal.hide();
            }
        } else {
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = originalText;
            const error = await response.json().catch(() => ({}));
            showToast(error.message || 'Failed to delete movie', 'error');
        }
    } catch (error) {
        deleteBtn.disabled = false;
        deleteBtn.innerHTML = originalText;
        showToast('An error occurred. Please try again.', 'error');
    }
}

async function toggleStatus(id, statusBtn) {
    const originalText = statusBtn.innerHTML;
    statusBtn.disabled = true;
    statusBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    try {
        const response = await fetch(`/admin/movies/${id}/status`, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            const result = await response.json();

            // Update in master cache
            const index = allMoviesCache.findIndex(m => m.id === id);
            if (index !== -1) {
                allMoviesCache[index] = result.data;
            }

            filterAndRenderMovies();

            statusBtn.disabled = false;
            statusBtn.innerHTML = originalText;
            showToast('Status updated successfully', 'success');
        } else {
            statusBtn.disabled = false;
            statusBtn.innerHTML = originalText;
            showToast('Unable to update status', 'error');
        }
    } catch (error) {
        statusBtn.disabled = false;
        statusBtn.innerHTML = originalText;
        showToast('An error occurred. Please try again.', 'error');
    }
}

function openMovieFormById(id) {
    const movie = allMoviesCache.find((m) => m.id === id) || moviesCache.find((m) => m.id === id);
    if (movie) {
        openMovieForm(movie);
    }
}

// Initial load - show cached content immediately if available
if (allMoviesCache.length > 0) {
    filterAndRenderMovies();
} else {
    fetchMovies();
}

// Reload movies when tab becomes visible (only if cache is empty)
document.addEventListener('visibilitychange', () => {
    if (!document.hidden && allMoviesCache.length === 0) {
        fetchMovies();
    }
});

// Handle browser back/forward navigation
window.addEventListener('pageshow', (event) => {
    if (event.persisted || performance.navigation.type === 2) {
        // Show cached data first for instant display
        if (allMoviesCache.length > 0) {
            filterAndRenderMovies();
        }
        // Then fetch fresh data in background
        fetchMovies(true);
    }
});

// Stat Card Click Handlers
document.getElementById('endedShowtimesCard').addEventListener('click', showEndedShowtimesModal);
document.getElementById('endedShowtimesCard').addEventListener('keypress', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        showEndedShowtimesModal();
    }
});

// Active Movies Card - Filter to show only active movies
document.getElementById('activeMoviesCard').addEventListener('click', () => filterByStatus('Active'));
document.getElementById('activeMoviesCard').addEventListener('keypress', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        filterByStatus('Active');
    }
});

// Disabled Movies Card - Filter to show only inactive movies
document.getElementById('disabledMoviesCard').addEventListener('click', () => filterByStatus('Inactive'));
document.getElementById('disabledMoviesCard').addEventListener('keypress', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        filterByStatus('Inactive');
    }
});

// Filter by status helper function
function filterByStatus(status) {
    const filterSelect = document.getElementById('filterStatus');
    filterSelect.value = status;
    filterAndRenderMovies();

    // Visual feedback - highlight the selected card
    document.querySelectorAll('.stat-pill-clickable').forEach(card => card.classList.remove('active'));
    if (status === 'Active') {
        document.getElementById('activeMoviesCard').classList.add('active');
    } else if (status === 'Inactive') {
        document.getElementById('disabledMoviesCard').classList.add('active');
    }
}


// Revenue Report Card Click Handler
const revenueReportModal = new bootstrap.Modal(document.getElementById('revenueReportModal'));
document.getElementById('revenueReportCard').addEventListener('click', showRevenueReportModal);
document.getElementById('revenueReportCard').addEventListener('keypress', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        showRevenueReportModal();
    }
});

// Automatically fetch and display total revenue on page load
document.addEventListener('DOMContentLoaded', async () => {
    try {
        const response = await fetch('/admin/movies/revenue-report');
        if (!response.ok) throw new Error('Failed to fetch revenue report');
        const result = await response.json();
        const totals = result.totals || { revenue: 0 };
        document.getElementById('statTotalRevenue').textContent = formatCurrency(totals.revenue);
    } catch (error) {
        document.getElementById('statTotalRevenue').textContent = '₱0.00';
    }
});

async function showRevenueReportModal() {
    const modalBody = document.getElementById('revenueReportBody');
    const grandTotalRevenue = document.getElementById('grandTotalRevenue');
    const grandTotalTickets = document.getElementById('grandTotalTickets');

    // Show loading state
    modalBody.innerHTML = `
        <div class="text-center py-5">
            <span class="spinner-border text-success"></span>
            <p class="mt-3 text-muted">Loading revenue data...</p>
        </div>
    `;
    grandTotalRevenue.textContent = '₱0.00';
    grandTotalTickets.textContent = '0';

    revenueReportModal.show();

    try {
        const response = await fetch('/admin/movies/revenue-report');
        if (!response.ok) throw new Error('Failed to fetch revenue report');

        const result = await response.json();
        const movies = result.data || [];
        const totals = result.totals || { revenue: 0, tickets: 0 };

        // Update grand totals
        grandTotalRevenue.textContent = formatCurrency(totals.revenue);
        grandTotalTickets.textContent = totals.tickets.toLocaleString();

        // Update the stat card total revenue
        document.getElementById('statTotalRevenue').textContent = formatCurrency(totals.revenue);

        if (movies.length === 0) {
            modalBody.innerHTML = `
                <div class="text-center py-5">
                    <i class="bi bi-film text-muted" style="font-size: 3rem;"></i>
                    <p class="mt-3 text-muted">No movies found</p>
                </div>
            `;
            return;
        }

        // Render movie revenue cards
        modalBody.innerHTML = movies.map(movie => {
            const poster = movie.poster_url || 'https://via.placeholder.com/60x80/2a2a2a/ffffff?text=No+Image';
            const releaseDate = movie.release_date ? new Date(movie.release_date).toLocaleDateString('en-US', {
                month: 'long', day: 'numeric', year: 'numeric'
            }) : 'N/A';
            const statusBadge = movie.is_active
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-secondary">Inactive</span>';

            return `
                <div class="revenue-movie-card mb-3 p-3" style="background: #111; border-radius: 12px; border: 1px solid #333;">
                    <div class="d-flex align-items-center gap-3">
                        <img src="${poster}" alt="${movie.title}"
                            style="width: 60px; height: 80px; object-fit: cover; border-radius: 8px;"
                            onerror="this.src='https://via.placeholder.com/60x80/2a2a2a/ffffff?text=No+Image'">
                        <div style="flex-grow: 1;">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">${movie.title} ${statusBadge}</h6>
                                    <div class="mt-1">
                                        <span style="color: #a0a0a0; font-size: 0.85rem;">
                                            <i class="bi bi-calendar-event me-1"></i>Released: ${releaseDate}
                                        </span>
                                        <span class="text-warning ms-2" style="font-size: 0.85rem;">${movie.tickets_sold} tickets sold</span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="text-success fw-bold" style="font-size: 1.1rem;">${formatCurrency(movie.total_revenue)}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

    } catch (error) {
        console.error('Error fetching revenue report:', error);
        modalBody.innerHTML = `
            <div class="text-center py-5">
                <i class="bi bi-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                <p class="mt-3 text-muted">Failed to load revenue data</p>
            </div>
        `;
    }
}

async function showEndedShowtimesModal() {
    const modalBody = document.getElementById('endedShowtimesBody');
    const totalRevenueDisplay = document.getElementById('totalRevenueDisplay');

    // Show loading state
    modalBody.innerHTML = `
        <div class="text-center py-5">
            <span class="spinner-border text-danger"></span>
            <p class="mt-3 text-muted">Loading movies with ended showtimes...</p>
        </div>
    `;
    totalRevenueDisplay.textContent = '₱0.00';

    endedShowtimesModal.show();

    try {
        const response = await fetch('/admin/movies/ended-showtimes');
        if (!response.ok) throw new Error('Failed to fetch ended showtimes');

        const data = await response.json();
        endedMoviesCache = data.data || [];

        if (endedMoviesCache.length === 0) {
            modalBody.innerHTML = `
                <div class="text-center py-5">
                    <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                    <p class="mt-3 text-muted">All active movies have upcoming showtimes!</p>
                </div>
            `;
            return;
        }

        // Calculate total revenue
        const totalRevenue = endedMoviesCache.reduce((sum, m) => sum + (m.total_revenue || 0), 0);
        totalRevenueDisplay.textContent = formatCurrency(totalRevenue);

        // Render movies
        modalBody.innerHTML = endedMoviesCache.map(movie => renderEndedMovieCard(movie)).join('');

    } catch (error) {
        console.error('Error fetching ended showtimes:', error);
        modalBody.innerHTML = `
            <div class="text-center py-5 text-danger">
                <i class="bi bi-exclamation-triangle" style="font-size: 3rem;"></i>
                <p class="mt-3">Failed to load data. Please try again.</p>
            </div>
        `;
    }
}

function renderEndedMovieCard(movie) {
    let poster = 'https://via.placeholder.com/80x120/2a2a2a/ffffff?text=No+Image';
    if (movie.poster_url) {
        const url = movie.poster_url;
        if (url.startsWith('http://') || url.startsWith('https://')) {
            poster = url;
        } else {
            poster = url.startsWith('/') ? window.location.origin + url : window.location.origin + '/' + url;
        }
    }

    const genreLabel = Array.isArray(movie.genre) ? movie.genre.join(', ') : (movie.genre || 'N/A');
    const lastShowtime = movie.last_showtime ? new Date(movie.last_showtime).toLocaleString() : 'N/A';

    return `
        <div class="ended-movie-card d-flex gap-3" data-movie-id="${movie.id}">
            <img src="${poster}" class="ended-movie-poster" alt="${movie.title}"
                onerror="this.src='https://via.placeholder.com/80x120/2a2a2a/ffffff?text=No+Image'">
            <div class="ended-movie-info">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="ended-movie-title">${movie.title}</div>
                        <div class="ended-movie-meta">${genreLabel}</div>
                        <div class="ended-movie-meta"><i class="bi bi-calendar-check me-1"></i>Last showtime: ${lastShowtime}</div>
                    </div>
                    <button class="btn btn-warning btn-sm deactivate-btn" data-movie-id="${movie.id}" onclick="deactivateMovieFromModal('${movie.id}', this)">
                        <i class="bi bi-pause-circle me-1"></i>Deactivate
                    </button>
                </div>
                <div class="ended-movie-stats">
                    <div class="ended-movie-stat">
                        <span class="ended-movie-stat-value revenue">${formatCurrency(movie.total_revenue || 0)}</span>
                        <span class="ended-movie-stat-label">Total Revenue</span>
                    </div>
                    <div class="ended-movie-stat">
                        <span class="ended-movie-stat-value tickets">${movie.tickets_sold || 0}</span>
                        <span class="ended-movie-stat-label">Tickets Sold</span>
                    </div>
                    <div class="ended-movie-stat">
                        <span class="ended-movie-stat-value showtimes">${movie.total_showtimes || 0}</span>
                        <span class="ended-movie-stat-label">Showtimes</span>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function formatCurrency(amount) {
    return '₱' + Number(amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function deactivateMovieFromModal(movieId, btn) {
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    try {
        const response = await fetch(`/admin/movies/${movieId}/status`, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            // Remove card from modal with animation
            const card = btn.closest('.ended-movie-card');
            card.style.transition = 'all 0.3s ease';
            card.style.opacity = '0';
            card.style.transform = 'translateX(20px)';

            setTimeout(() => {
                card.remove();

                // Update ended count
                endedMoviesCache = endedMoviesCache.filter(m => m.id !== movieId);
                document.getElementById('statEnded').textContent = endedMoviesCache.length;

                // Recalculate total revenue
                const totalRevenue = endedMoviesCache.reduce((sum, m) => sum + (m.total_revenue || 0), 0);
                document.getElementById('totalRevenueDisplay').textContent = formatCurrency(totalRevenue);

                // Check if no more movies
                if (endedMoviesCache.length === 0) {
                    document.getElementById('endedShowtimesBody').innerHTML = `
                        <div class="text-center py-5">
                            <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                            <p class="mt-3 text-muted">All movies have been deactivated!</p>
                        </div>
                    `;
                }
            }, 300);

            // Update main movies list in master cache
            const movieIndex = allMoviesCache.findIndex(m => m.id === movieId);
            if (movieIndex !== -1) {
                allMoviesCache[movieIndex].is_active = false;
                filterAndRenderMovies();
            }

            showToast('Movie deactivated successfully', 'success');
        } else {
            btn.disabled = false;
            btn.innerHTML = originalText;
            showToast('Failed to deactivate movie', 'error');
        }
    } catch (error) {
        btn.disabled = false;
        btn.innerHTML = originalText;
        showToast('An error occurred. Please try again.', 'error');
    }
}
