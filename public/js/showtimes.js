// Showtimes Page JavaScript

const showtimeForm = document.getElementById('showtimeForm');
const submitBtn = document.getElementById('submitBtn');
const modal = bootstrap.Modal.getInstance(document.getElementById('addShowtimeModal')) || new bootstrap.Modal(document.getElementById('addShowtimeModal'));
const modalTitle = document.getElementById('addShowtimeModalLabel');
const formMethod = document.getElementById('formMethod');
const showtimeId = document.getElementById('showtimeId');

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

async function editShowtime(id) {
    const editBtn = event.target;
    const originalText = editBtn.innerHTML;
    editBtn.disabled = true;
    editBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    try {
        const response = await fetch(`/admin/showtimes/${id}/edit`, {
            headers: {
                'Accept': 'application/json'
            }
        });

        if (response.ok) {
            const showtime = await response.json();

            // Populate form
            document.getElementById('movieSelect').value = showtime.movie_id;
            document.getElementById('cinemaHall').value = showtime.cinema_hall;

            const showtimeDate = new Date(showtime.showtime);
            document.getElementById('showtimeDate').value = showtimeDate.toISOString().split('T')[0];
            document.getElementById('showtimeTime').value = showtimeDate.toTimeString().slice(0, 5);
            document.getElementById('basePrice').value = showtime.base_price;

            // Set form to update mode
            modalTitle.textContent = 'Edit Showtime';
            formMethod.value = 'PUT';
            showtimeId.value = id;
            showtimeForm.action = `/admin/showtimes/${id}`;
            submitBtn.textContent = 'Update Showtime';

            editBtn.disabled = false;
            editBtn.innerHTML = originalText;
            modal.show();
        } else {
            editBtn.disabled = false;
            editBtn.innerHTML = originalText;
            showToast('Failed to load showtime', 'error');
        }
    } catch (error) {
        editBtn.disabled = false;
        editBtn.innerHTML = originalText;
        showToast('An error occurred. Please try again.', 'error');
    }
}

// Reset form when modal is closed
document.getElementById('addShowtimeModal').addEventListener('hidden.bs.modal', function() {
    showtimeForm.reset();
    modalTitle.textContent = 'Add New Showtime';
    formMethod.value = 'POST';
    showtimeId.value = '';
    showtimeForm.action = '/admin/showtimes';
    submitBtn.textContent = 'Save Showtime';
});

showtimeForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

    const formData = new FormData(showtimeForm);
    const isEdit = formMethod.value === 'PUT';

    try {
        const response = await fetch(showtimeForm.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: formData
        });

        const result = await response.json().catch(() => ({}));

        if (response.ok) {
            // Close modal immediately
            modal.hide();

            // Show success toast
            const message = isEdit ? 'Showtime updated successfully!' : 'Showtime added successfully!';
            showToast(message, 'success');

            if (isEdit) {
                // For edits, update the existing showtime in DOM
                updateShowtimeInDOM(result.showtime || showtimeId.value);
            } else if (result.showtime) {
                // For new showtimes, add to DOM immediately
                addShowtimeToDOM(result.showtime);
            } else {
                // Fallback: reload if no showtime data returned
                setTimeout(() => window.location.reload(), 300);
            }

            // Reset form
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        } else {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            showToast(result.message || 'Failed to save showtime', 'error');
        }
    } catch (error) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        showToast('An error occurred. Please try again.', 'error');
    }
});

// Add new showtime to DOM without reload
function addShowtimeToDOM(showtime) {
    const movie = showtime.movie;
    if (!movie) return;

    const container = document.getElementById('showtimesContainer');
    const movieName = movie.title.toLowerCase();

    // Check if movie group already exists
    let movieGroup = document.querySelector(`.movie-group[data-movie-name="${movieName}"]`);

    // Format showtime date
    const showtimeDate = new Date(showtime.showtime);
    const dateStr = showtimeDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    const timeStr = showtimeDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    const fullDateStr = `${dateStr} ${timeStr}`;

    // Create showtime card HTML
    const showtimeCardHTML = `
        <div class="col-lg-6 showtime-item" data-showtime-id="${showtime.id}" data-cinema="${showtime.cinema_hall}" style="animation: fadeIn 0.3s ease-out;">
            <div class="showtime-card p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="text-white mb-1">
                            <i class="bi bi-building text-danger me-2"></i>${showtime.cinema_hall}
                        </h6>
                    </div>
                    <span class="badge badge-pill bg-success">
                        ${showtime.available_seats_count} seats
                    </span>
                </div>
                <div class="d-flex flex-wrap gap-3 text-muted-light mb-3">
                    <div>
                        <i class="bi bi-calendar-event me-1"></i>
                        ${dateStr}
                    </div>
                    <div>
                        <i class="bi bi-clock me-1"></i>
                        ${timeStr}
                    </div>
                    <div>
                        <i class="bi bi-currency-exchange me-1"></i>
                        ₱${parseFloat(showtime.base_price).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-danger btn-sm btn-edit"
                        onclick="editShowtime('${showtime.id}')">Edit</button>
                    <button type="button" class="btn btn-outline-danger btn-sm btn-delete"
                        onclick="deleteShowtime('${showtime.id}', '${movie.title.replace(/'/g, "\\'") }', '${fullDateStr}')">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    `;

    if (movieGroup) {
        // Add to existing movie group
        const showtimesRow = movieGroup.querySelector('.row.g-3');
        showtimesRow.insertAdjacentHTML('afterbegin', showtimeCardHTML);

        // Update movie stats
        updateMovieGroupStats(movieGroup);
    } else {
        // Create new movie group
        const posterUrl = movie.poster_url || 'https://via.placeholder.com/100x140?text=Poster';
        const description = movie.description ? (movie.description.length > 150 ? movie.description.substring(0, 150) + '...' : movie.description) : 'No description yet.';
        const movieSeats = showtime.available_seats_count || 0;

        const movieGroupHTML = `
            <div class="mb-4 movie-group" data-movie-name="${movieName}" data-movie-seats="${movieSeats}" data-movie-showtime-count="1" style="animation: fadeIn 0.3s ease-out;">
                <div class="d-flex gap-3 align-items-start mb-3">
                    <div style="flex-shrink: 0;">
                        <img src="${posterUrl}" alt="${movie.title}"
                            style="width: 100px; height: 140px; object-fit: cover; border-radius: 8px;">
                    </div>
                    <div class="grow">
                        <h4 class="text-white mb-2">
                            ${movie.title}
                            ${!movie.is_active ? '<span class="badge bg-secondary ms-2" style="font-size: 0.6rem; vertical-align: middle;">Deactivated</span>' : ''}
                        </h4>
                        <p class="mb-1 text-muted-light">
                            ${movie.duration_minutes || 0} min • ${movie.language || 'N/A'}
                        </p>
                        <p class="mb-1">
                            <span class="badge bg-danger me-2 movie-showtime-count">1 showtime</span>
                            <span class="badge bg-success movie-seats-count">${movieSeats} seats available</span>
                        </p>
                        <p class="text-muted-light mb-0" style="font-size: 0.85rem;">${description}</p>
                    </div>
                </div>
                <div class="row g-3">
                    ${showtimeCardHTML}
                </div>
            </div>
        `;

        // Add at the beginning or create container if empty
        if (container) {
            container.insertAdjacentHTML('afterbegin', movieGroupHTML);
        } else {
            // If no container (empty state), replace the empty message
            const emptyMsg = document.querySelector('.showtimes-content .text-center.text-muted-light');
            if (emptyMsg) {
                const newContainer = document.createElement('div');
                newContainer.id = 'showtimesContainer';
                newContainer.innerHTML = movieGroupHTML;
                emptyMsg.replaceWith(newContainer);
            }
        }
    }

    // Update stats
    updateShowtimeStats();
}

// Update movie group stats (showtime count and seats available)
function updateMovieGroupStats(movieGroup) {
    const showtimeItems = movieGroup.querySelectorAll('.showtime-item');
    let totalSeats = 0;
    const showtimeCount = showtimeItems.length;

    showtimeItems.forEach(item => {
        const seatsBadge = item.querySelector('.badge');
        if (seatsBadge) {
            const seats = parseInt(seatsBadge.textContent.match(/\d+/)?.[0] || 0);
            totalSeats += seats;
        }
    });

    // Update data attributes
    movieGroup.dataset.movieSeats = totalSeats;
    movieGroup.dataset.movieShowtimeCount = showtimeCount;

    // Update visible badges
    const showtimeCountBadge = movieGroup.querySelector('.movie-showtime-count');
    const seatsCountBadge = movieGroup.querySelector('.movie-seats-count');

    if (showtimeCountBadge) {
        showtimeCountBadge.textContent = `${showtimeCount} showtime${showtimeCount > 1 ? 's' : ''}`;
    }
    if (seatsCountBadge) {
        seatsCountBadge.textContent = `${totalSeats} seats available`;
    }
}

// Update existing showtime in DOM after edit
function updateShowtimeInDOM(showtimeOrId) {
    // For edits, just reload to ensure consistency since we may have changed movie/date
    setTimeout(() => window.location.reload(), 300);
}

async function deleteShowtime(id, movieTitle, showtimeDate) {
    const modalResult = await showConfirm(
        'Delete Showtime',
        `Are you sure you want to delete "${movieTitle}" showtime on ${showtimeDate}? This will also delete all reservations and tickets.`
    );

    if (!modalResult.confirmed) return;

    // Find the delete button and showtime item
    const showtimeItem = document.querySelector(`[data-showtime-id="${id}"]`);
    const deleteBtn = showtimeItem?.querySelector('.btn-delete') || event.target.closest('button');
    const originalText = deleteBtn?.innerHTML || 'Delete';

    if (deleteBtn) {
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    }

    try {
        const response = await fetch(`/admin/showtimes/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        });

        // Hide confirm modal
        if (modalResult.modal) {
            modalResult.modal.hide();
        }

        if (response.ok) {
            showToast('Showtime deleted successfully!', 'success');

            // Remove the showtime item from DOM with animation
            if (showtimeItem) {
                showtimeItem.style.transition = 'all 0.3s ease';
                showtimeItem.style.opacity = '0';
                showtimeItem.style.transform = 'translateX(-20px)';

                setTimeout(() => {
                    const movieGroup = showtimeItem.closest('.movie-group');
                    showtimeItem.remove();

                    // If no more showtimes in this movie group, remove the group
                    if (movieGroup && movieGroup.querySelectorAll('.showtime-item').length === 0) {
                        movieGroup.style.transition = 'all 0.3s ease';
                        movieGroup.style.opacity = '0';
                        setTimeout(() => movieGroup.remove(), 300);
                    } else if (movieGroup) {
                        // Update movie group stats
                        updateMovieGroupStats(movieGroup);
                    }

                    // Update stats
                    updateShowtimeStats();
                }, 300);
            }
        } else {
            if (deleteBtn) {
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = originalText;
            }
            const error = await response.json().catch(() => ({}));
            showToast(error.message || 'Failed to delete showtime', 'error');
        }
    } catch (error) {
        if (deleteBtn) {
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = originalText;
        }
        showToast('An error occurred. Please try again.', 'error');
    }
}

function updateShowtimeStats() {
    const movieGroups = document.querySelectorAll('.movie-group');
    let totalShowtimes = 0;
    let totalSeats = 0;

    movieGroups.forEach(group => {
        if (group.style.display !== 'none') {
            const showtimeItems = group.querySelectorAll('.showtime-item');
            totalShowtimes += showtimeItems.length;

            showtimeItems.forEach(item => {
                const seatsBadge = item.querySelector('.badge');
                if (seatsBadge) {
                    const seats = parseInt(seatsBadge.textContent.match(/\d+/)?.[0] || 0);
                    totalSeats += seats;
                }
            });
        }
    });

    const statsEl = document.getElementById('showtimeStats');
    if (statsEl) {
        statsEl.textContent = `${totalShowtimes} showtimes shown (${totalSeats} seats available)`;
    }

    // Update total movies tracked
    const visibleGroups = document.querySelectorAll('.movie-group:not([style*="display: none"])');
    const totalMoviesEl = document.querySelector('.total-movies-count');
    if (totalMoviesEl) {
        totalMoviesEl.textContent = visibleGroups.length;
    }
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
                        <button type="button" class="btn btn-danger" id="confirmBtn">
                            <span class="btn-text">Delete</span>
                            <span class="btn-loading" style="display: none;">
                                <span class="spinner-border spinner-border-sm me-1"></span>Deleting...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        const confirmBtn = modal.querySelector('#confirmBtn');
        const btnText = confirmBtn.querySelector('.btn-text');
        const btnLoading = confirmBtn.querySelector('.btn-loading');

        confirmBtn.addEventListener('click', () => {
            // Show loading state
            confirmBtn.disabled = true;
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline-flex';

            modal._confirmed = true;
            resolve({ confirmed: true, modal: bsModal });
        });

        modal.addEventListener('hidden.bs.modal', () => {
            setTimeout(() => modal.remove(), 150);
            if (!modal._confirmed) {
                resolve({ confirmed: false, modal: null });
            }
        });

        bsModal.show();
    });
}

// Search functionality
function filterShowtimes() {
    const searchText = document.getElementById('searchMovie').value.toLowerCase();
    const movieGroups = document.querySelectorAll('.movie-group');

    let visibleShowtimes = 0;
    let visibleSeats = 0;

    movieGroups.forEach(group => {
        const movieName = group.dataset.movieName;

        // Check if movie name matches search
        const movieMatches = searchText === '' || movieName.includes(searchText);

        if (movieMatches) {
            group.style.display = '';
            const showtimeItems = group.querySelectorAll('.showtime-item');

            showtimeItems.forEach(item => {
                visibleShowtimes++;
                // Count seats
                const seatsBadge = item.querySelector('.badge');
                if (seatsBadge) {
                    const seats = parseInt(seatsBadge.textContent.match(/\d+/)?.[0] || 0);
                    visibleSeats += seats;
                }
            });
        } else {
            group.style.display = 'none';
        }
    });

    // Update stats
    document.getElementById('showtimeStats').textContent =
        `${visibleShowtimes} showtimes shown (${visibleSeats} seats available)`;
}

// Debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func(...args), wait);
    };
}

// Event listeners for search
if (document.getElementById('searchMovie')) {
    const debouncedFilter = debounce(filterShowtimes, 300);
    document.getElementById('searchMovie').addEventListener('input', debouncedFilter);
}

if (document.getElementById('clearSearch')) {
    document.getElementById('clearSearch').addEventListener('click', () => {
        document.getElementById('searchMovie').value = '';
        filterShowtimes();
    });
}
