// Users Management JavaScript - Optimized

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

const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
let usersCache = [];
let statsCache = null;
let lastFetchTime = 0;
const CACHE_DURATION = 30000; // 30 seconds cache

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

async function fetchUsers(forceRefresh = false) {
    const refreshBtn = document.getElementById('refreshBtn');
    const originalText = refreshBtn?.innerHTML;
    const now = Date.now();

    // Use cache if available and not expired (unless force refresh)
    if (!forceRefresh && usersCache.length > 0 && (now - lastFetchTime) < CACHE_DURATION) {
        applyFiltersAndRender();
        return;
    }

    if (refreshBtn) {
        refreshBtn.disabled = true;
        refreshBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Refreshing...';
    }

    try {
        const response = await fetch('/api/v1/users');

        if (!response.ok) {
            throw new Error('Failed to fetch users');
        }

        const payload = await response.json();

        // Store all users in cache
        usersCache = payload.data || [];
        // Store stats from server (already computed)
        statsCache = payload.stats || null;
        lastFetchTime = now;

        // Update stats from server response (optimized - no client-side computation)
        if (statsCache) {
            document.getElementById('statTotal').textContent = statsCache.total;
            document.getElementById('statAdmins').textContent = statsCache.admins;
            document.getElementById('statUsers').textContent = statsCache.users;
            document.getElementById('statOnline').textContent = statsCache.online || 0;
        }

        // Apply filters and render
        applyFiltersAndRender();
    } catch (error) {
        console.error('Error fetching users:', error);
        showToast('Failed to load users', 'error');
    } finally {
        if (refreshBtn) {
            refreshBtn.disabled = false;
            refreshBtn.innerHTML = originalText;
        }
    }
}

function applyFiltersAndRender() {
    let filteredUsers = [...usersCache];
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const roleFilter = document.getElementById('filterRole').value;

    if (searchTerm) {
        filteredUsers = filteredUsers.filter(user =>
            (user.full_name && user.full_name.toLowerCase().includes(searchTerm)) ||
            (user.email && user.email.toLowerCase().includes(searchTerm))
        );
    }

    if (roleFilter === 'online') {
        // Filter for online users (using server-side is_online flag)
        filteredUsers = filteredUsers.filter(user => {
            if (user.role === 'admin') return false; // Don't include admins
            return user.is_online === true;
        });
    } else if (roleFilter !== 'all') {
        filteredUsers = filteredUsers.filter(user => user.role === roleFilter);
    }

    // Sort users: admins first, then by name
    filteredUsers.sort((a, b) => {
        // Admins always on top
        if (a.role === 'admin' && b.role !== 'admin') return -1;
        if (a.role !== 'admin' && b.role === 'admin') return 1;
        // Then sort by name
        return (a.full_name || '').localeCompare(b.full_name || '');
    });

    renderUsers(filteredUsers);
}

// Client-side filtering (uses cache, no API call)
function filterUsersLocally() {
    if (usersCache.length === 0) {
        fetchUsers();
        return;
    }
    applyFiltersAndRender();
}

function renderUsers(users) {
    const container = document.getElementById('usersList');

    if (users.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="bi bi-people"></i>
                <p>No users found</p>
            </div>
        `;
        return;
    }

    container.innerHTML = users.map(user => {
        const isAdmin = user.role === 'admin';
        const createdDate = new Date(user.created_at);
        const formattedDate = createdDate.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });

        // Format last activity - use last_seen if available (more accurate), fallback to last_sign_in
        let lastSeen = '<span class="text-warning">Never logged in</span>';
        const lastActivityTime = user.last_seen || user.last_sign_in;
        if (lastActivityTime) {
            const activityDate = new Date(lastActivityTime);
            const now = new Date();
            const diffMs = now - activityDate;
            const diffSecs = Math.floor(diffMs / 1000);
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);

            // Show relative time with seconds precision for recent activity
            if (diffSecs < 5) {
                lastSeen = '<span class="text-success">Just now</span>';
            } else if (diffSecs < 60) {
                lastSeen = `<span class="text-success">${diffSecs} second${diffSecs > 1 ? 's' : ''} ago</span>`;
            } else if (diffMins < 60) {
                lastSeen = `<span class="text-success">${diffMins} minute${diffMins > 1 ? 's' : ''} ago</span>`;
            } else if (diffHours < 24) {
                lastSeen = `<span class="text-info">${diffHours} hour${diffHours > 1 ? 's' : ''} ago</span>`;
            } else if (diffDays < 7) {
                lastSeen = `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
            } else {
                lastSeen = activityDate.toLocaleString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });
            }
        }

        // Check if user is online (using server-side is_online flag)
        // Admin always shows Online badge, regular users only if is_online is true
        const isOnline = isAdmin ? true : user.is_online === true;

        // For online users, show "Just now" instead of last seen time
        const displayLastSeen = isOnline ? '<span class="text-success">Just now</span>' : lastSeen;

        const onlineBadge = isAdmin
            ? '<span class="badge bg-success online-badge"><i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>Online</span>'
            : (isOnline ? '<span class="badge bg-success online-badge"><i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>Online</span>' : '');

        return `
            <div class="user-card ${isAdmin ? 'admin' : ''}" data-user-id="${user.id}">
                <div class="user-card-header" onclick="toggleUserCard('${user.id}')">
                    <div class="user-avatar ${isAdmin ? 'admin' : 'user'} ${isOnline ? 'online' : ''}">
                        <i class="bi bi-${isAdmin ? 'shield-check' : 'person'}"></i>
                        ${isOnline ? '<span class="online-dot"></span>' : ''}
                    </div>
                    <div class="user-info">
                        <div class="user-name">
                            ${user.full_name || 'Unknown'}
                            ${isAdmin ? '<span class="badge bg-danger">ADMIN</span>' : ''}
                            ${user.email_confirmed ? '<span class="badge bg-success">Verified</span>' : '<span class="badge bg-warning">Unverified</span>'}
                            ${onlineBadge}
                        </div>
                        <div class="user-email">${user.email || 'No email'}</div>
                        <div class="user-joined">Joined ${formattedDate}</div>
                    </div>
                    <i class="bi bi-chevron-down expand-icon" id="icon-${user.id}"></i>
                </div>
                <div class="user-card-body" id="body-${user.id}">
                    <div class="user-details">
                        ${user.phone_number ? `
                            <div class="detail-row">
                                <i class="bi bi-phone"></i>
                                <span>${user.phone_number}</span>
                            </div>
                        ` : ''}
                        <div class="detail-row">
                            <i class="bi bi-envelope"></i>
                            <span>${user.email}</span>
                        </div>
                        <div class="detail-row">
                            <i class="bi bi-calendar"></i>
                            <span>Joined ${createdDate.toLocaleDateString('en-US', {
                                month: 'long',
                                day: 'numeric',
                                year: 'numeric'
                            })}</span>
                        </div>
                        ${!isAdmin ? `
                        <div class="detail-row">
                            <i class="bi bi-clock"></i>
                            <span>Last seen: ${displayLastSeen}</span>
                        </div>
                        ` : ''}
                        <div class="detail-row">
                            <i class="bi bi-check-circle"></i>
                            <span>Email ${user.email_confirmed ? 'confirmed' : 'not confirmed'}</span>
                        </div>
                    </div>
                    <div class="user-actions">
                        <button class="btn btn-danger btn-delete-user"
                            data-user-id="${user.id}" data-user-name="${(user.full_name || 'this user').replace(/"/g, '&quot;')}">
                            <i class="bi bi-trash"></i>
                            Delete User
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function toggleUserCard(userId) {
    const body = document.getElementById(`body-${userId}`);
    const icon = document.getElementById(`icon-${userId}`);

    body.classList.toggle('expanded');
    icon.classList.toggle('expanded');
}

function filterByRole(role) {
    const filterSelect = document.getElementById('filterRole');
    filterSelect.value = role;
    fetchUsers();
}

// Custom confirmation modal
function showConfirmModal(title, message, userName) {
    return new Promise((resolve) => {
        const modal = document.createElement('div');
        modal.className = 'confirm-modal-overlay';
        modal.innerHTML = `
            <div class="confirm-modal">
                <div class="confirm-modal-header">
                    <i class="bi bi-exclamation-triangle text-danger"></i>
                    <h5>${title}</h5>
                </div>
                <div class="confirm-modal-body">
                    <p>Are you sure you want to delete <strong>${userName}</strong>?</p>
                    <p class="text-muted small">This action cannot be undone. All user data will be permanently removed.</p>
                </div>
                <div class="confirm-modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmBtn">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Animate in
        requestAnimationFrame(() => {
            modal.classList.add('show');
        });

        const confirmBtn = modal.querySelector('#confirmBtn');
        const cancelBtn = modal.querySelector('#cancelBtn');

        const closeModal = (result) => {
            modal.classList.remove('show');
            setTimeout(() => modal.remove(), 300);
            resolve(result);
        };

        confirmBtn.addEventListener('click', () => closeModal(true));
        cancelBtn.addEventListener('click', () => closeModal(false));
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal(false);
        });

        // ESC key to close
        const handleEsc = (e) => {
            if (e.key === 'Escape') {
                closeModal(false);
                document.removeEventListener('keydown', handleEsc);
            }
        };
        document.addEventListener('keydown', handleEsc);
    });
}

async function deleteUser(userId, userName, btn) {
    const confirmed = await showConfirmModal('Delete User', '', userName);
    if (!confirmed) return;

    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    try {
        const response = await fetch(`/api/v1/users/${userId}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
            }
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.message || 'Failed to delete user');
        }

        // Remove from cache and update stats locally
        const deletedUser = usersCache.find(u => u.id === userId);
        usersCache = usersCache.filter(u => u.id !== userId);

        // Update stats cache locally to avoid refetch
        if (statsCache && deletedUser) {
            statsCache.total--;
            if (deletedUser.role === 'admin') {
                statsCache.admins--;
            } else {
                statsCache.users--;
            }
            document.getElementById('statTotal').textContent = statsCache.total;
            document.getElementById('statAdmins').textContent = statsCache.admins;
            document.getElementById('statUsers').textContent = statsCache.users;
        }

        // Animate the user card removal
        const userCard = document.querySelector(`[data-user-id="${userId}"]`);
        if (userCard) {
            userCard.style.transition = 'all 0.4s ease-out';
            userCard.style.transform = 'translateX(-100%)';
            userCard.style.opacity = '0';
            await new Promise(r => setTimeout(r, 400));
        }

        applyFiltersAndRender();
        showToast(result.message || 'User deleted successfully', 'success');
    } catch (error) {
        btn.disabled = false;
        btn.innerHTML = originalText;
        console.error('Error:', error);
        showToast(error.message || 'Failed to delete user', 'error');
    }
}

// Event listeners - use local filtering for search/filter (no API calls)
const debouncedLocalFilter = debounce(filterUsersLocally, 300);
document.getElementById('searchInput').addEventListener('input', debouncedLocalFilter);
document.getElementById('filterRole').addEventListener('change', filterUsersLocally);
document.getElementById('refreshBtn').addEventListener('click', () => fetchUsers(true)); // Force refresh

// Event delegation for user action buttons
document.getElementById('usersList').addEventListener('click', (e) => {
    const target = e.target.closest('button');
    if (!target) return;

    if (target.classList.contains('btn-delete-user')) {
        const userId = target.dataset.userId;
        const userName = target.dataset.userName;
        deleteUser(userId, userName, target);
    }
});

// Initial load - defer to avoid blocking page render
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        // Use requestIdleCallback for non-critical initial load
        if ('requestIdleCallback' in window) {
            requestIdleCallback(() => fetchUsers(), { timeout: 1000 });
        } else {
            setTimeout(fetchUsers, 100);
        }
    });
} else {
    // Page already loaded
    if ('requestIdleCallback' in window) {
        requestIdleCallback(() => fetchUsers(), { timeout: 1000 });
    } else {
        setTimeout(fetchUsers, 100);
    }
}
