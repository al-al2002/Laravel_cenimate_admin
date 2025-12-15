<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserSessionController extends Controller
{
    /**
     * Update user online status (called from Flutter app)
     * POST /api/v1/user/session
     */
    public function updateSession(Request $request)
    {
        $request->validate([
            'user_id' => 'required|uuid',
            'is_online' => 'required|boolean',
            'device_token' => 'nullable|string',
        ]);

        $userId = $request->input('user_id');
        $isOnline = $request->input('is_online');
        $deviceToken = $request->input('device_token', 'default');

        if ($isOnline) {
            // User is logging in or app is active
            DB::table('user_sessions')->updateOrInsert(
                ['user_id' => $userId, 'device_token' => $deviceToken],
                [
                    'is_online' => true,
                    'last_activity_at' => now(),
                    'logged_out_at' => null,
                    'updated_at' => now(),
                ]
            );
        } else {
            // User is logging out
            DB::table('user_sessions')
                ->where('user_id', $userId)
                ->update([
                    'is_online' => false,
                    'logged_out_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        return response()->json([
            'success' => true,
            'message' => $isOnline ? 'User online' : 'User offline',
        ]);
    }

    /**
     * Heartbeat to keep session alive (called periodically from Flutter)
     * POST /api/v1/user/heartbeat
     */
    public function heartbeat(Request $request)
    {
        $request->validate([
            'user_id' => 'required|uuid',
            'device_token' => 'nullable|string',
        ]);

        $userId = $request->input('user_id');
        $deviceToken = $request->input('device_token', 'default');

        DB::table('user_sessions')->updateOrInsert(
            ['user_id' => $userId, 'device_token' => $deviceToken],
            [
                'is_online' => true,
                'last_activity_at' => now(),
                'logged_out_at' => null,
                'updated_at' => now(),
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * Get online users count
     */
    public function getOnlineUsers()
    {
        // Users are considered online if:
        // 1. is_online = true AND
        // 2. last_activity_at within last 5 minutes (in case app crashed without logout)
        $onlineUsers = DB::table('user_sessions')
            ->where('is_online', true)
            ->where('last_activity_at', '>=', now()->subMinutes(5))
            ->pluck('user_id')
            ->unique()
            ->values();

        return response()->json([
            'success' => true,
            'online_user_ids' => $onlineUsers,
            'count' => $onlineUsers->count(),
        ]);
    }
}
