<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShareNotificationController extends Controller
{
    public function getAllNotifications()
    {
        try{
            $authenticatedUser = Auth::user();
            $user = User::findOrFail($authenticatedUser->id);

            $notifications = $user->notifications()->get();

            return response()->json($notifications);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve all notification: ' . $e->getMessage(),
            ], 500);
        }

    }

    public function getUnreadNotifications()
    {
        try{
            $authenticatedUser = Auth::user();
            $user = User::findOrFail($authenticatedUser->id);

            $notifications = $user->unreadNotifications()->get();

            return response()->json($notifications);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve unread notification: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getReadNotifications()
    {
        try{
            $authenticatedUser = Auth::user();
            $user = User::findOrFail($authenticatedUser->id);

            $notifications = $user->readNotifications()->get();

            return response()->json($notifications);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve read notification: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function markAsRead($id)
    {
        try{
            $authenticatedUser = Auth::user();
            $user = User::findOrFail($authenticatedUser->id);

            $notification = $user->notifications()->where('id', $id)->first();

            if ($notification) {
                $notification->markAsRead();
                return response()->json([
                    'success' => true,
                    'message' => 'Notification marked as read successfully.',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found.',
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read: ' . $e->getMessage(),
            ], 500);
        }
    }
}
