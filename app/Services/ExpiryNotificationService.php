<?php

namespace App\Services;

use App\Models\ProductBatch;
use App\Models\ExpiredBatchNotification;
use Carbon\Carbon;

class ExpiryNotificationService
{
    /**
     * Check for expiring batches and create notifications.
     */
    public function checkExpiringBatches(): array
    {
        $results = [
            'expired' => 0,
            'near_expiry_15' => 0,
            'near_expiry_30' => 0,
            'near_expiry_60' => 0,
            'near_expiry_90' => 0,
            'out_of_stock' => 0,
            'updated_status' => 0,
        ];

        // Get all active batches with expiry dates
        $batches = ProductBatch::active()
            ->whereNotNull('expiry_date')
            ->get();

        foreach ($batches as $batch) {
            // Check for out of stock batches
            if ($batch->remaining_quantity <= 0) {
                $this->createOrUpdateNotification(
                    $batch,
                    'out_of_stock',
                    0
                );
                $results['out_of_stock']++;
                continue;
            }

            // Update batch status if expired
            if ($batch->isExpired()) {
                $batch->update(['status' => 'expired']);
                $this->createOrUpdateNotification(
                    $batch,
                    'expired',
                    0
                );
                $results['expired']++;
                $results['updated_status']++;
                continue;
            }

            // Check for near expiry
            $daysUntilExpiry = $batch->getDaysUntilExpiry();

            if ($daysUntilExpiry !== null) {
                if ($daysUntilExpiry <= 15 && $daysUntilExpiry > 0) {
                    $this->createOrUpdateNotification(
                        $batch,
                        'near_expiry',
                        $daysUntilExpiry
                    );
                    $results['near_expiry_15']++;
                } elseif ($daysUntilExpiry <= 30 && $daysUntilExpiry > 15) {
                    $this->createOrUpdateNotification(
                        $batch,
                        'near_expiry',
                        $daysUntilExpiry
                    );
                    $results['near_expiry_30']++;
                } elseif ($daysUntilExpiry <= 60 && $daysUntilExpiry > 30) {
                    $this->createOrUpdateNotification(
                        $batch,
                        'near_expiry',
                        $daysUntilExpiry
                    );
                    $results['near_expiry_60']++;
                } elseif ($daysUntilExpiry <= 90 && $daysUntilExpiry > 60) {
                    $this->createOrUpdateNotification(
                        $batch,
                        'near_expiry',
                        $daysUntilExpiry
                    );
                    $results['near_expiry_90']++;
                }
            }
        }

        return $results;
    }

    /**
     * Create or update notification for a batch.
     */
    private function createOrUpdateNotification(
        ProductBatch $batch,
        string $type,
        int $daysUntilExpiry
    ): void {
        ExpiredBatchNotification::createOrUpdate(
            $batch->id,
            $batch->business_id,
            $type,
            $daysUntilExpiry
        );
    }

    /**
     * Get active notifications for a business.
     */
    public function getActiveNotifications(int $businessId): array
    {
        $notifications = ExpiredBatchNotification::where('business_id', $businessId)
            ->active()
            ->with(['batch.product'])
            ->orderBy('days_until_expiry', 'asc')
            ->get();

        return [
            'total' => $notifications->count(),
            'expired' => $notifications->where('notification_type', 'expired')->count(),
            'near_expiry' => $notifications->where('notification_type', 'near_expiry')->count(),
            'notifications' => $notifications,
        ];
    }

    /**
     * Get unread notifications count for a business.
     */
    public function getUnreadCount(int $businessId): int
    {
        return ExpiredBatchNotification::where('business_id', $businessId)
            ->unread()
            ->undismissed()
            ->count();
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(int $notificationId): bool
    {
        $notification = ExpiredBatchNotification::findOrFail($notificationId);
        return $notification->markAsRead();
    }

    /**
     * Dismiss notification.
     */
    public function dismissNotification(int $notificationId): bool
    {
        $notification = ExpiredBatchNotification::findOrFail($notificationId);
        return $notification->dismiss();
    }

    /**
     * Clean up old notifications for discarded batches only.
     * Batches with zero quantity keep their notifications.
     */
    public function cleanupOldNotifications(): int
    {
        // Delete notifications for discarded batches only
        $discardedBatches = ProductBatch::where('status', 'discarded')->pluck('id');
        $deletedDiscarded = ExpiredBatchNotification::whereIn('batch_id', $discardedBatches)->delete();

        // Ya NO eliminamos notificaciones de lotes con cantidad 0
        // Esos lotes mantienen sus notificaciones de tipo 'out_of_stock'

        return $deletedDiscarded;
    }

    /**
     * Get notification statistics for dashboard.
     */
    public function getNotificationStats(int $businessId): array
    {
        $now = Carbon::now();

        return [
            'total_notifications' => ExpiredBatchNotification::where('business_id', $businessId)
                ->active()
                ->count(),
            'critical' => ExpiredBatchNotification::where('business_id', $businessId)
                ->active()
                ->where('days_until_expiry', '<=', 15)
                ->where('notification_type', 'near_expiry')
                ->count(),
            'warning_30' => ExpiredBatchNotification::where('business_id', $businessId)
                ->active()
                ->whereBetween('days_until_expiry', [16, 30])
                ->where('notification_type', 'near_expiry')
                ->count(),
            'warning_60' => ExpiredBatchNotification::where('business_id', $businessId)
                ->active()
                ->whereBetween('days_until_expiry', [31, 60])
                ->where('notification_type', 'near_expiry')
                ->count(),
            'info' => ExpiredBatchNotification::where('business_id', $businessId)
                ->active()
                ->whereBetween('days_until_expiry', [61, 90])
                ->where('notification_type', 'near_expiry')
                ->count(),
            'expired' => ExpiredBatchNotification::where('business_id', $businessId)
                ->active()
                ->ofType('expired')
                ->count(),
            'out_of_stock' => ExpiredBatchNotification::where('business_id', $businessId)
                ->active()
                ->ofType('out_of_stock')
                ->count(),
        ];
    }
}
