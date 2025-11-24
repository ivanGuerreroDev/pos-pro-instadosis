<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpiredBatchNotification extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'expired_batches_notifications';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'batch_id',
        'business_id',
        'notification_type',
        'days_until_expiry',
        'is_read',
        'is_dismissed',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_read' => 'boolean',
        'is_dismissed' => 'boolean',
    ];

    /**
     * Get the batch that owns the notification.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    /**
     * Get the business that owns the notification.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Scope a query to only include unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope a query to only include undismissed notifications.
     */
    public function scopeUndismissed($query)
    {
        return $query->where('is_dismissed', false);
    }

    /**
     * Scope a query to only include active notifications.
     */
    public function scopeActive($query)
    {
        return $query->where('is_read', false)->where('is_dismissed', false);
    }

    /**
     * Scope a query to only include specific notification type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('notification_type', $type);
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(): bool
    {
        $this->is_read = true;
        return $this->save();
    }

    /**
     * Dismiss notification.
     */
    public function dismiss(): bool
    {
        $this->is_dismissed = true;
        return $this->save();
    }

    /**
     * Create or update notification for a batch.
     */
    public static function createOrUpdate(
        int $batchId,
        int $businessId,
        string $type,
        int $daysUntilExpiry
    ): self {
        return self::updateOrCreate(
            [
                'batch_id' => $batchId,
                'notification_type' => $type,
            ],
            [
                'business_id' => $businessId,
                'days_until_expiry' => $daysUntilExpiry,
                'is_read' => false,
                'is_dismissed' => false,
            ]
        );
    }
}
