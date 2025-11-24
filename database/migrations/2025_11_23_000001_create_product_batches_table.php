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
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number');
            $table->integer('quantity')->default(0);
            $table->integer('remaining_quantity')->default(0);
            $table->double('purchase_price', 10, 2)->default(0);
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['active', 'expired', 'discarded'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Índices para optimizar consultas
            $table->index(['product_id', 'status']);
            $table->index(['business_id', 'status']);
            $table->index('expiry_date');
            
            // Batch number debe ser único por producto
            $table->unique(['product_id', 'batch_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};
