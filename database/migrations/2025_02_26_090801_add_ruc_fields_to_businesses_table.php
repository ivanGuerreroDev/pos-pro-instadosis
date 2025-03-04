<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('businesses', function (Blueprint $table) {
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
        });
    }

    public function down()
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'dtipoRuc', 'druc', 'ddv', 'dnombEm', 'dcoordEm', 
                'ddirecEm', 'dcodUbi', 'dcorreg', 'ddistr', 'dprov',
                'dtfnEm', 'dcorElectEmi'
            ]);
        });
    }
}; 