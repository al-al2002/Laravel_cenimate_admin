<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Cinema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #000000 0%, #1a0000 50%, #000000 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            position: relative;
            overflow: hidden;
        }

        /* Animated Background */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(circle at 20% 50%, rgba(239, 68, 68, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(239, 68, 68, 0.1) 0%, transparent 50%);
            animation: pulse 8s ease-in-out infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
            padding: 1rem;
        }

        .login-card {
            background: linear-gradient(135deg, #0a0a0a 0%, #000000 100%);
            border: 1px solid #ef4444;
            border-radius: 20px;
            padding: 3rem 2.5rem;
            box-shadow:
                0 20px 60px rgba(0, 0, 0, 0.8),
                0 0 0 1px rgba(239, 68, 68, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.05);
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(239, 68, 68, 0.05) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .login-header {
            position: relative;
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .login-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border-radius: 20px;
            margin-bottom: 1.5rem;
            box-shadow:
                0 10px 30px rgba(239, 68, 68, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .login-icon i {
            font-size: 2.5rem;
            color: white;
        }

        .login-title {
            color: white;
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .login-subtitle {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.95rem;
        }

        .form-content {
            position: relative;
        }

        .alert {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 10px;
            color: #ef4444;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .alert ul {
            margin-bottom: 0;
            padding-left: 1.25rem;
        }

        .form-label {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 10px;
            color: white;
            padding: 0.875rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
            color: white;
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }

        .password-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: rgba(255, 255, 255, 0.5);
            font-size: 1.25rem;
            transition: color 0.3s ease;
            user-select: none;
        }

        .password-toggle:hover {
            color: #ef4444;
        }

        .btn-login {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border: none;
            border-radius: 10px;
            color: white;
            padding: 1rem;
            font-weight: 600;
            font-size: 1rem;
            width: 100%;
            margin-top: 1.5rem;
            transition: all 0.3s ease;
            box-shadow:
                0 4px 15px rgba(239, 68, 68, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.8;
            cursor: not-allowed;
            transform: none;
        }

        .btn-login:disabled:hover {
            transform: none;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .login-footer {
            text-align: center;
            margin-top: 2rem;
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.85rem;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-icon">
                    <i class="bi bi-shield-lock"></i>
                </div>
                <h3 class="login-title">Cinema Admin</h3>
                <p class="login-subtitle">Sign in to manage your cinema</p>
            </div>

            <div class="form-content">
                <!-- Display Validation Errors -->
                @if ($errors->any())
                    <div class="alert">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ url('admin/login') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">
                            <i class="bi bi-envelope me-2"></i>Email Address
                        </label>
                        <input type="email" name="email" class="form-control" id="email"
                            value="{{ old('email') }}" placeholder="admin@cinema.com" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock me-2"></i>Password
                        </label>
                        <div class="password-wrapper">
                            <input type="password" name="password" class="form-control" id="password"
                                placeholder="Enter your password" required>
                            <i class="bi bi-eye password-toggle" id="togglePassword"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn-login" id="loginBtn">
                        <span class="btn-text"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</span>
                        <span class="btn-loading" style="display: none;">
                            <span class="spinner-border spinner-border-sm me-2"></span>Signing In...
                        </span>
                    </button>
                </form>
            </div>

            <div class="login-footer">
                <i class="bi bi-shield-check me-1"></i>
                Secure Admin Access
            </div>
        </div>
    </div>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            togglePassword.classList.toggle('bi-eye');
            togglePassword.classList.toggle('bi-eye-slash');
        });

        // Handle form submission with loading state
        const loginForm = document.querySelector('form');
        const loginBtn = document.getElementById('loginBtn');
        const btnText = loginBtn.querySelector('.btn-text');
        const btnLoading = loginBtn.querySelector('.btn-loading');

        loginForm.addEventListener('submit', function() {
            // Show loading state
            loginBtn.disabled = true;
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline-flex';
        });
    </script>
</body>

</html>
