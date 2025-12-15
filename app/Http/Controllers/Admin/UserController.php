<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return view('admin.users');
    }

    /**
     * Optimized: Combined users list with stats in single response
     * - Select only needed fields
     * - Include stats to avoid extra API call
     * - Cache stats for 2 minutes
     */
    public function list(Request $request)
    {
        // Optimized: Select only needed fields
        $query = User::select([
            'id', 'email', 'full_name', 'phone_number', 'role',
            'email_confirmed', 'last_sign_in', 'created_at'
        ]);

        // Apply role filter
        if ($role = $request->query('role')) {
            if ($role !== 'all') {
                $query->where('role', $role);
            }
        }

        // Apply search with index-friendly query
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(full_name) LIKE ?', ['%' . strtolower($search) . '%'])
                  ->orWhereRaw('LOWER(email) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }

        // Limit results to prevent loading too many users
        $users = $query->orderByDesc('created_at')
            ->limit(200)
            ->get();

        // Get cached stats or compute them (for ALL users, not filtered)
        // Use CASE WHEN for better compatibility
        $stats = Cache::remember('users_stats', 120, function () {
            return User::selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
                SUM(CASE WHEN role = 'user' OR role IS NULL THEN 1 ELSE 0 END) as users
            ")->first();
        });

        return response()->json([
            'data' => $users,
            'stats' => [
                'total' => (int) ($stats->total ?? 0),
                'admins' => (int) ($stats->admins ?? 0),
                'users' => (int) ($stats->users ?? 0),
            ]
        ]);
    }

    public function updateRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(['user', 'admin'])],
        ]);

        $user->update(['role' => $validated['role']]);

        // Clear stats cache when role changes
        Cache::forget('users_stats');

        return response()->json([
            'success' => true,
            'message' => 'User role updated successfully',
            'data' => $user
        ]);
    }

    public function destroy(User $user)
    {
        // Prevent deleting yourself
        $currentUser = auth('admin')->user();
        if ($currentUser && $user->id === $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account'
            ], 403);
        }

        try {
            $deletedCount = 0;
            DB::transaction(function () use ($user, &$deletedCount) {
                $deletedCount = DB::table('reviews')->where('user_id', $user->id)->delete();
                // delete the user
                $user->delete();
            });

            Log::info('Admin deleted user and reviews', ['user_id' => $user->id, 'reviews_deleted' => $deletedCount]);

            // Clear stats cache when user is deleted
            Cache::forget('users_stats');

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully',
                'reviews_deleted' => $deletedCount
            ]);

        } catch (\Exception $e) {
            // Log full error for debugging
            Log::error('Error deleting user', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            // Return DB error (include message to help debug in admin UI)
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user: ' . $e->getMessage()
            ], 500);
        }
    }
}
