<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->role === 'admin') {
            app(\App\Services\TripDepartureMonitor::class)->check();
        }
        $notifications = Notification::with('trip')
            ->where('user_id', $request->user()->id)
            ->whereNull('dismissed_at')
            ->latest()
            ->get();

        return response()->json($notifications);
    }

    public function markAsRead(Request $request, Notification $notification)
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(403);
        }

        $notification->update(['is_read' => true]);

        return response()->json([
            'message' => 'Notification marked as read',
        ]);
    }

    public function unreadCount(Request $request)
    {
        if ($request->user()->role === 'admin') {
            app(\App\Services\TripDepartureMonitor::class)->check();
        }
        $count = Notification::where('user_id', $request->user()->id)
            ->whereNull('dismissed_at')
            ->where('is_read', false)
            ->count();

        return response()->json([
            'count' => $count,
        ]);
    }

    public function destroy(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        // Keep the event key so periodic monitors do not recreate this alert.
        $notification->dismissed_at = $notification->dismissed_at ?? now();
        $notification->is_read = true;
        $notification->save();

        return response()->noContent();
    }
}
