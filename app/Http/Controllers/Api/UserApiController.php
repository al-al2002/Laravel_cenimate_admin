<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserApiController extends Controller
{
    /**
     * Get all users: Laravel admins + Supabase Auth users
     *
     * @return JsonResponse
     */
    public function getRegularUsers(): JsonResponse
    {
        try {
            $laravelUsers = $this->fetchLaravelUsers();
            $supabaseUsersById = $this->fetchSupabaseUsers();
            $allUsers = $this->mergeUsers($laravelUsers, $supabaseUsersById);
            $allUsers = $this->addOnlineStatus($allUsers);
            $stats = $this->calculateStats($allUsers);

            return response()->json([
                'success' => true,
                'data' => $allUsers,
                'count' => $stats['total'],
                'stats' => $stats,
            ])->header('Access-Control-Allow-Origin', '*')
              ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
              ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        } catch (\Exception $e) {
            Log::error('Error fetching users: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Fetch Laravel admin users from database
     */
    private function fetchLaravelUsers()
    {
        return User::all()->map(function ($user) {
            return [
                'id' => $user->id,
                'email' => $user->email,
                'full_name' => $user->full_name ?? $user->email,
                'phone_number' => $user->phone_number ?? null,
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
                'role' => $user->role,
                'source' => 'laravel',
                'email_confirmed' => true,
                'last_sign_in' => $user->last_sign_in?->toIso8601String(),
            ];
        });
    }

    /**
     * Fetch Supabase Auth users
     */
    private function fetchSupabaseUsers()
    {
        $supabaseUrl = config('supabase.url');
        $serviceRoleKey = config('supabase.service_role_key') ?: config('supabase.anon_key');

        if (!$supabaseUrl || !$serviceRoleKey) {
            return collect();
        }

        // Fetch auth users
        $response = Http::withHeaders([
            'apikey' => $serviceRoleKey,
            'Authorization' => 'Bearer ' . $serviceRoleKey,
        ])->get("{$supabaseUrl}/auth/v1/admin/users");

        if (!$response->successful()) {
            return collect();
        }

        // Fetch online status from users table
        $onlineStatusResponse = Http::withHeaders([
            'apikey' => $serviceRoleKey,
            'Authorization' => 'Bearer ' . $serviceRoleKey,
        ])->get("{$supabaseUrl}/rest/v1/users", [
            'select' => 'id,is_online,last_seen',
        ]);

        $onlineStatusMap = [];
        if ($onlineStatusResponse->successful()) {
            foreach ($onlineStatusResponse->json() ?? [] as $user) {
                $onlineStatusMap[$user['id']] = [
                    'is_online' => $user['is_online'] ?? false,
                    'last_seen' => $user['last_seen'] ?? null,
                ];
            }
        }

        return collect($response->json('users', []))->keyBy('id')->map(function ($user) use ($onlineStatusMap) {
            $userId = $user['id'];
            $onlineData = $onlineStatusMap[$userId] ?? ['is_online' => false, 'last_seen' => null];

            return [
                'id' => $userId,
                'email' => $user['email'] ?? null,
                'full_name' => $user['user_metadata']['full_name'] ?? $user['email'] ?? 'Unknown',
                'phone_number' => $user['user_metadata']['phone_number'] ?? $user['phone'] ?? null,
                'created_at' => $user['created_at'] ?? null,
                'updated_at' => $user['updated_at'] ?? null,
                'role' => 'user',
                'source' => 'supabase',
                'email_confirmed' => isset($user['email_confirmed_at']) && $user['email_confirmed_at'],
                'last_sign_in' => $user['last_sign_in_at'] ?? null,
                'is_online' => $onlineData['is_online'] ?? false,
                'last_seen' => $onlineData['last_seen'] ?? null,
            ];
        });
    }

    /**
     * Merge Laravel and Supabase users
     */
    private function mergeUsers($laravelUsers, $supabaseUsersById)
    {
        $merged = $laravelUsers->map(function ($laravelUser) use ($supabaseUsersById) {
            if ($supabaseUsersById->has($laravelUser['id'])) {
                $supabaseUser = $supabaseUsersById->get($laravelUser['id']);
                return array_merge($supabaseUser, [
                    'role' => $laravelUser['role'],
                    'full_name' => $laravelUser['full_name'] ?: $supabaseUser['full_name'],
                ]);
            }
            return $laravelUser;
        });

        $laravelIds = $laravelUsers->pluck('id')->toArray();
        $supabaseOnly = $supabaseUsersById->filter(fn($user, $id) => !in_array($id, $laravelIds))->values();

        return $merged->merge($supabaseOnly)->values();
    }

    /**
     * Add online status to users (for Laravel users, check user_sessions; Supabase users already have is_online)
     */
    private function addOnlineStatus($users)
    {
        // For local user_sessions table (fallback)
        $localOnlineUserIds = DB::table('user_sessions')
            ->where('is_online', true)
            ->where('last_activity_at', '>=', now()->subMinutes(5))
            ->pluck('user_id')
            ->unique()
            ->toArray();

        return $users->map(function ($user) use ($localOnlineUserIds) {
            // If is_online is already set from Supabase, use it
            // Otherwise check local user_sessions table
            if (!isset($user['is_online'])) {
                $user['is_online'] = in_array($user['id'], $localOnlineUserIds);
            }
            return $user;
        });
    }

    /**
     * Calculate user stats
     */
    private function calculateStats($users): array
    {
        $total = $users->count();
        $admins = $users->where('role', 'admin')->count();

        return [
            'total' => $total,
            'admins' => $admins,
            'users' => $total - $admins,
            'online' => $users->where('role', '!=', 'admin')->where('is_online', true)->count(),
        ];
    }

    /**
     * Delete a user (Laravel or Supabase)
     *
     * @param string $id
     * @return JsonResponse
     */
    public function deleteUser(string $id): JsonResponse
    {
        try {
            // Try Laravel database first

            $laravelUser = User::find($id);
            if ($laravelUser) {
                // Delete all reviews for this user to avoid FK errors
                $deletedReviews = DB::table('reviews')->where('user_id', $laravelUser->id)->delete();
                // Check if this is an admin - don't allow deleting yourself
                if ($laravelUser->role === 'admin') {
                    $laravelUser->delete();
                    return response()->json([
                        'success' => true,
                        'message' => 'Admin user deleted successfully',
                        'reviews_deleted' => $deletedReviews
                    ]);
                }
            }

            // For regular users, always try to delete from Supabase Auth first
            $supabaseUrl = config('supabase.url');
            $serviceRoleKey = config('supabase.service_role_key');

            if (!$supabaseUrl || !$serviceRoleKey) {
                Log::error('Supabase not configured for user deletion');
                return response()->json([
                    'success' => false,
                    'message' => 'Supabase not configured. Cannot delete user.'
                ], 500);
            }

            // Delete user from Supabase Auth Admin API
            $response = Http::withHeaders([
                'apikey' => $serviceRoleKey,
                'Authorization' => 'Bearer ' . $serviceRoleKey,
            ])->delete("{$supabaseUrl}/auth/v1/admin/users/{$id}");

            Log::info('Supabase delete response', [
                'user_id' => $id,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful() || $response->status() === 204) {
                // Also delete from local database if exists

                if ($laravelUser) {
                    // Delete all reviews for this user to avoid FK errors
                    $deletedReviews = DB::table('reviews')->where('user_id', $laravelUser->id)->delete();
                    $laravelUser->delete();
                }

                return response()->json([
                    'success' => true,
                    'message' => 'User deleted successfully from Supabase'
                ]);
            }

            // Check if user not found in Supabase (might be local only)
            if ($response->status() === 404) {
                if ($laravelUser) {
                    // Delete all reviews for this user to avoid FK errors
                    $deletedReviews = DB::table('reviews')->where('user_id', $laravelUser->id)->delete();
                    $laravelUser->delete();
                    return response()->json([
                        'success' => true,
                        'message' => 'User deleted from local database',
                        'reviews_deleted' => $deletedReviews
                    ]);
                }
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            Log::error('Failed to delete Supabase user', [
                'user_id' => $id,
                'status' => $response->status(),
                'error' => $response->json()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user from Supabase: ' . ($response->json('message') ?? $response->json('error') ?? 'Unknown error')
            ], 500);

        } catch (\Exception $e) {
            Log::error('Error deleting user: ' . $e->getMessage(), [
                'user_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
}
