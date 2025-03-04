<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('business_invoice_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->string('dtipoRuc')->nullable();
            $table->string('druc')->nullable();
            $table->string('ddv')->nullable();
            $table->string('dnombEm')->nullable();
            $table->string('dcoordEm')->nullable();
            $table->string('ddirecEm')->nullable();
            $table->string('dcodUbi')->nullable();
            $table->string('dcorreg')->nullable();
            $table->string('ddistr')->nullable();
            $table->string('dprov')->nullable();
            $table->string('dtfnEm')->nullable();
            $table->string('dcorElectEmi')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('business_invoice_data');
    }
}; 