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
        Schema::create('batch_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('product_batches')->cascadeOnDelete();
            $table->enum('transaction_type', ['purchase', 'sale', 'adjustment', 'discard', 'return']);
            $table->integer('quantity');
            $table->string('reference_type')->nullable(); // Sale, Purchase, etc
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Índices para consultas
            $table->index(['batch_id', 'transaction_type']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batch_transactions');
    }
};
