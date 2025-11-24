<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expired_batches_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('product_batches')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->enum('notification_type', ['near_expiry', 'expired']);
            $table->integer('days_until_expiry')->default(0);
            $table->boolean('is_read')->default(false);
            $table->boolean('is_dismissed')->default(false);
            $table->timestamps();

            // Índices para consultas
            $table->index(['business_id', 'is_read', 'is_dismissed'], 'ebn_biz_read_dismissed_index');
            $table->index(['batch_id', 'notification_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expired_batches_notifications');
    }
};
