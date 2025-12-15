<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    /**
     * Show the admin login form
     */
    public function showLoginForm()
    {
        return view('auth.login'); // Blade in resources/views/auth/login.blade.php
    }

    /**
     * Handle admin login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');

        // Attempt login with 'admin' guard and role = admin
        if (Auth::guard('admin')->attempt(array_merge($credentials, ['role' => 'admin']))) {
            // Update last_sign_in timestamp
            User::where('id', Auth::guard('admin')->id())->update(['last_sign_in' => now()]);

            $request->session()->regenerate();
            return redirect()->intended(route('admin.dashboard'));
        }        return back()->withErrors([
            'email' => 'Invalid credentials or not an admin',
        ])->withInput($request->only('email'));
    }

    /**
     * Handle admin logout
     */
    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
