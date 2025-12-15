<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Panel</title>

    <!-- Preconnect to CDN for faster loading -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://isqzlkxwpotvjkymirvn.supabase.co" crossorigin>

    <!-- Preload critical resources -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" as="style">
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
        as="script">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Common admin styles for better performance -->
    <link href="{{ asset('css/admin-common.css') }}" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: #0f172a;
            color: #f8fafc;
        }

        .sidebar {
            min-height: 100vh;
            width: 260px;
            background: #000;
            border-right: 3px solid #dc2626;
        }

        .sidebar a {
            padding: 12px 18px;
            border-radius: 8px;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .sidebar a:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #f8fafc;
        }

        .sidebar a.active {
            background: linear-gradient(135deg, #dc2626, #ef4444);
            color: #fff;
            box-shadow: 0 15px 30px rgba(220, 38, 38, 0.4);
        }

        .sidebar h4 {
            letter-spacing: 0.1em;
            text-transform: uppercase;
            font-size: 0.9rem;
            color: #f87171;
        }

        .content-wrapper {
            flex: 1;
            margin-left: 260px;
            padding: 20px 30px;
            min-height: 100vh;
        }

        .card {
            border-radius: 12px;
        }
    </style>
    @yield('styles')
</head>

<body>
    <div class="d-flex">
        @include('layouts.sidebar')
        <div class="content-wrapper">@yield('content')</div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>

</html>
