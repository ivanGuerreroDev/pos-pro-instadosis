<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modificar el enum para agregar 'out_of_stock'
        DB::statement("ALTER TABLE expired_batches_notifications MODIFY COLUMN notification_type ENUM('near_expiry', 'expired', 'out_of_stock')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar notificaciones de tipo 'out_of_stock' antes de revertir
        DB::table('expired_batches_notifications')
            ->where('notification_type', 'out_of_stock')
            ->delete();
            
        // Revertir el enum a su estado anterior
        DB::statement("ALTER TABLE expired_batches_notifications MODIFY COLUMN notification_type ENUM('near_expiry', 'expired')");
    }
};
