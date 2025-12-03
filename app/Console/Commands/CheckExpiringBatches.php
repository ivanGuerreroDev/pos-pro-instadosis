<?php

namespace App\Console\Commands;

use App\Services\ExpiryNotificationService;
use Illuminate\Console\Command;

class CheckExpiringBatches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'batches:check-expiry
                            {--cleanup : Clean up old notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for expiring and expired batches and create notifications';

    protected $notificationService;

    /**
     * Create a new command instance.
     */
    public function __construct(ExpiryNotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expiring batches...');

        // Check expiring batches
        $results = $this->notificationService->checkExpiringBatches();

        // Display results
        $this->line('');
        $this->info('Results:');
        $this->line("  Expired batches: {$results['expired']}");
        $this->line("  Near expiry (15 days): {$results['near_expiry_15']}");
        $this->line("  Near expiry (1 month/30 days): {$results['near_expiry_30']}");
        $this->line("  Near expiry (2 months/60 days): {$results['near_expiry_60']}");
        $this->line("  Near expiry (3 months/90 days): {$results['near_expiry_90']}");
        $this->line("  Out of stock batches: {$results['out_of_stock']}");
        $this->line("  Batch statuses updated: {$results['updated_status']}");

        // Cleanup if requested
        if ($this->option('cleanup')) {
            $this->line('');
            $this->info('Cleaning up old notifications...');
            $deleted = $this->notificationService->cleanupOldNotifications();
            $this->line("  Deleted notifications: {$deleted}");
        }

        $this->line('');
        $this->info('Batch expiry check completed successfully!');

        return 0;
    }
}
