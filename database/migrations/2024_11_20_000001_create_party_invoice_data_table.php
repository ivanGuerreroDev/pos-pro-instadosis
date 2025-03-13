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
        Schema::create('party_invoice_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->onDelete('cascade');
            $table->string('dtipoRuc')->nullable();
            $table->string('druc')->nullable();
            $table->string('ddv')->nullable();
            $table->string('itipoRec')->nullable();
            $table->string('dnombRec')->nullable();
            $table->string('ddirecRec')->nullable();
            $table->string('dcodUbi')->nullable();
            $table->string('dcorreg')->nullable();
            $table->string('ddistr')->nullable();
            $table->string('dprov')->nullable();
            $table->string('dcorElectRec')->nullable();
            $table->string('didExt')->nullable();
            $table->string('dpaisExt')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('party_invoice_data');
    }
};