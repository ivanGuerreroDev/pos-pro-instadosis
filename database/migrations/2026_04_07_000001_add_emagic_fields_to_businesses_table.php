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
        Schema::table('businesses', function (Blueprint $table) {
            $table->text('emagic_api_key')->nullable()->after('shopOpeningBalance');
            $table->string('billing_status')->default('active')->after('emagic_api_key');
            $table->timestamp('billing_linked_at')->nullable()->after('billing_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['emagic_api_key', 'billing_status', 'billing_linked_at']);
        });
    }
};
