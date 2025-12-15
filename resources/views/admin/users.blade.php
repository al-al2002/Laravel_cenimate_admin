@extends('layouts.admin')

@section('styles')
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://isqzlkxwpotvjkymirvn.supabase.co">
    <link href="{{ asset('css/users.css') }}" rel="stylesheet">
@endsection

@section('content')
    <div class="container-fluid users-page">
        <div class="hero-panel mb-4 text-white">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1>Manage Users</h1>
                    <p>View and manage user accounts, roles, and permissions</p>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="card mb-4 filters text-white">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label" for="searchInput">Search by name or email</label>
                        <input type="text" id="searchInput" class="form-control bg-slate-800 text-white"
                            placeholder="Search users..." autocomplete="off">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="filterRole">Filter by Role</label>
                        <select id="filterRole" class="form-select bg-slate-800 text-white">
                            <option value="all">All Roles</option>
                            <option value="user">Users</option>
                            <option value="admin">Admins</option>
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
                <div class="stat-pill" style="cursor: pointer;" onclick="filterByRole('all')">
                    <i class="bi bi-people"></i>
                    <div>
                        <strong id="statTotal">0</strong>
                        <span>Total Users</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-pill stat-online" style="cursor: pointer;" onclick="filterByRole('online')">
                    <i class="bi bi-circle-fill text-success"></i>
                    <div>
                        <strong id="statOnline">0</strong>
                        <span>Online</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-pill" style="cursor: pointer;" onclick="filterByRole('admin')">
                    <i class="bi bi-shield-check"></i>
                    <div>
                        <strong id="statAdmins">0</strong>
                        <span>Admins</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-pill" style="cursor: pointer;" onclick="filterByRole('user')">
                    <i class="bi bi-person"></i>
                    <div>
                        <strong id="statUsers">0</strong>
                        <span>Regular Users</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users List -->
        <div id="usersList"></div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <script src="{{ asset('js/users.js') }}"></script>
@endpush
