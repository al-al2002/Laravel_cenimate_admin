<div class="sidebar d-flex flex-column p-3">

    <!-- Logo/Brand -->
    <div class="sidebar-brand mb-2">
        <i class="bi bi-film"></i>
        <h4 class="text-white mb-0">Cinemate Admin</h4>
    </div>

    <!-- Welcome Text -->
    <div class="text-center" style="margin-bottom: 2.5rem;">
        <span class="text-muted-light" style="font-size: 1.1rem; letter-spacing: 1px;">
            Welcome, {{ auth('admin')->user()->full_name ?? 'Admin' }}
        </span>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav grow d-flex flex-column" style="flex: 1 1 auto; min-height: 0;">
        <a class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
            href="{{ route('admin.dashboard') }}">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
        <a class="sidebar-link {{ request()->routeIs('admin.users') ? 'active' : '' }}"
            href="{{ route('admin.users') }}">
            <i class="bi bi-people"></i>
            <span>Users</span>
        </a>
        <a class="sidebar-link {{ request()->routeIs('admin.movies') ? 'active' : '' }}"
            href="{{ route('admin.movies') }}">
            <i class="bi bi-camera-reels"></i>
            <span>Movies</span>
        </a>
        <a class="sidebar-link {{ request()->routeIs('admin.showtimes') ? 'active' : '' }}"
            href="{{ route('admin.showtimes') }}">
            <i class="bi bi-calendar-event"></i>
            <span>Showtimes</span>
        </a>
        <a class="sidebar-link {{ request()->routeIs('admin.reservations') ? 'active' : '' }}"
            href="{{ route('admin.reservations') }}">
            <i class="bi bi-ticket-perforated"></i>
            <span>Reservations</span>
        </a>
        <a class="sidebar-link {{ request()->routeIs('admin.payments') ? 'active' : '' }}"
            href="{{ route('admin.payments') }}">
            <i class="bi bi-credit-card"></i>
            <span>Payments</span>
        </a>
    </nav>

    <!-- Logout Button at Bottom -->
    <div class="mt-auto">
        <form method="POST" action="{{ route('admin.logout') }}" id="logoutForm">
            @csrf
            <button type="button" class="sidebar-logout btn w-100" id="logoutBtn" onclick="handleLogout()">
                <i class="bi bi-box-arrow-right" id="logoutIcon"></i>
                <span id="logoutText">Logout</span>
            </button>
        </form>
    </div>
</div>

<script>
    async function handleLogout() {
        const btn = document.getElementById('logoutBtn');
        const icon = document.getElementById('logoutIcon');
        const text = document.getElementById('logoutText');
        const form = document.getElementById('logoutForm');

        // Show loading state
        btn.disabled = true;
        icon.className = 'spinner-border spinner-border-sm';
        text.textContent = 'Logging out...';

        try {
            // Refresh CSRF token first to avoid "Page Expired" error
            const response = await fetch('/csrf-token', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                if (data.token) {
                    form.querySelector('input[name="_token"]').value = data.token;
                    // Also update meta tag for other forms
                    const metaToken = document.querySelector('meta[name="csrf-token"]');
                    if (metaToken) metaToken.content = data.token;
                }
            }
        } catch (error) {
            // Continue with logout even if token refresh fails
            console.log('CSRF refresh failed, proceeding anyway');
        }

        // Submit the form
        form.submit();
    }
</script>
<style>
    .sidebar {
        background: linear-gradient(180deg, #0a0a0a 0%, #000000 100%);
        border-right: 1px solid #ef4444;
        min-height: 100vh;
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        width: 260px;
        overflow-y: auto;
        box-shadow: 4px 0 20px rgba(239, 68, 68, 0.1);
    }

    .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem;
        border-bottom: 1px solid rgba(239, 68, 68, 0.3);
    }

    .sidebar-brand i {
        font-size: 2rem;
        color: #ef4444;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.7;
        }
    }

    .sidebar-brand h4 {
        font-size: 1.25rem;
        font-weight: 700;
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .sidebar-nav {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.875rem 1rem;
        color: rgba(255, 255, 255, 0.7);
        text-decoration: none;
        border-radius: 0.5rem;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .sidebar-link::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 3px;
        background: #ef4444;
        transform: scaleY(0);
        transition: transform 0.3s ease;
    }

    .sidebar-link:hover {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        transform: translateX(5px);
    }

    .sidebar-link:hover::before {
        transform: scaleY(1);
    }

    .sidebar-link.active {
        background: linear-gradient(90deg, rgba(239, 68, 68, 0.2) 0%, rgba(239, 68, 68, 0.05) 100%);
        color: #ef4444;
        border-left: 3px solid #ef4444;
        font-weight: 600;
    }

    .sidebar-link i {
        font-size: 1.25rem;
        min-width: 24px;
    }

    .sidebar-link span {
        flex: 1;
    }

    .sidebar-logout {
        background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        border: none;
        color: white;
        padding: 0.875rem;
        border-radius: 0.5rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
    }

    .sidebar-logout:hover {
        background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
    }

    .sidebar-logout i {
        font-size: 1.25rem;
    }
</style>
