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
        Schema::create('batch_sale_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_detail_id')->constrained('sale_details')->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('product_batches')->cascadeOnDelete();
            $table->integer('quantity');
            $table->timestamps();

            // Índices para consultas
            $table->index('sale_detail_id');
            $table->index('batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batch_sale_details');
    }
};
