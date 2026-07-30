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
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->double('opening_balance', 10, 2)->default(0);
            $table->text('opening_notes')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->double('total_sales', 10, 2)->nullable();
            $table->double('total_income', 10, 2)->nullable();
            $table->double('total_expense', 10, 2)->nullable();
            $table->double('closing_expected_balance', 10, 2)->nullable();
            $table->double('closing_counted_balance', 10, 2)->nullable();
            $table->double('closing_difference', 10, 2)->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_registers');
    }
};
