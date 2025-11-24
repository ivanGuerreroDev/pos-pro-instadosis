<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpiredBatchNotification;
use App\Services\ExpiryNotificationService;
use Illuminate\Http\Request;

class ExpiredBatchNotificationController extends Controller
{
    protected $notificationService;

    public function __construct(ExpiryNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of notifications.
     */
    public function index(Request $request)
    {
        $query = ExpiredBatchNotification::where('business_id', auth()->user()->business_id)
            ->with(['batch.product']);

        // Filter by read status
        if ($request->has('is_read')) {
            $query->where('is_read', $request->boolean('is_read'));
        }

        // Filter by dismissed status
        if ($request->has('is_dismissed')) {
            $query->where('is_dismissed', $request->boolean('is_dismissed'));
        }

        // Filter by type
        if ($request->has('notification_type')) {
            $query->where('notification_type', $request->notification_type);
        }

        // Only active by default
        if (!$request->has('include_dismissed')) {
            $query->undismissed();
        }

        $notifications = $query->orderBy('days_until_expiry', 'asc')->get();

        return response()->json([
            'message' => __('Data fetched successfully.'),
            'data' => $notifications,
            'stats' => $this->notificationService->getNotificationStats(auth()->user()->business_id),
        ]);
    }

    /**
     * Get unread notifications.
     */
    public function unread()
    {
        $notifications = ExpiredBatchNotification::where('business_id', auth()->user()->business_id)
            ->unread()
            ->undismissed()
            ->with(['batch.product'])
            ->orderBy('days_until_expiry', 'asc')
            ->get();

        return response()->json([
            'message' => __('Data fetched successfully.'),
            'data' => $notifications,
            'count' => $notifications->count(),
        ]);
    }

    /**
     * Display the specified notification.
     */
    public function show(ExpiredBatchNotification $expiredNotification)
    {
        // Verify ownership
        if ($expiredNotification->business_id !== auth()->user()->business_id) {
            return response()->json(['message' => __('Unauthorized')], 403);
        }

        $notification = $expiredNotification->load(['batch.product', 'batch.transactions']);

        return response()->json([
            'message' => __('Data fetched successfully.'),
            'data' => $notification,
        ]);
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(ExpiredBatchNotification $expiredNotification)
    {
        // Verify ownership
        if ($expiredNotification->business_id !== auth()->user()->business_id) {
            return response()->json(['message' => __('Unauthorized')], 403);
        }

        $this->notificationService->markAsRead($expiredNotification->id);

        return response()->json([
            'message' => __('Notification marked as read.'),
            'data' => $expiredNotification->fresh(),
        ]);
    }

    /**
     * Dismiss notification.
     */
    public function dismiss(ExpiredBatchNotification $expiredNotification)
    {
        // Verify ownership
        if ($expiredNotification->business_id !== auth()->user()->business_id) {
            return response()->json(['message' => __('Unauthorized')], 403);
        }

        $this->notificationService->dismissNotification($expiredNotification->id);

        return response()->json([
            'message' => __('Notification dismissed.'),
            'data' => $expiredNotification->fresh(),
        ]);
    }

    /**
     * Mark all as read.
     */
    public function markAllAsRead()
    {
        ExpiredBatchNotification::where('business_id', auth()->user()->business_id)
            ->unread()
            ->update(['is_read' => true]);

        return response()->json([
            'message' => __('All notifications marked as read.'),
        ]);
    }

    /**
     * Get notification statistics.
     */
    public function stats()
    {
        $stats = $this->notificationService->getNotificationStats(auth()->user()->business_id);

        return response()->json([
            'message' => __('Stats fetched successfully.'),
            'data' => $stats,
        ]);
    }
}
