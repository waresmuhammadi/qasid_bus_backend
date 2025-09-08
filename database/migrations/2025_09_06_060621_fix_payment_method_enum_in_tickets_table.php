<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Change enum values
        Schema::table('tickets', function (Blueprint $table) {
            $table->enum('payment_method', ['hessabpay', 'doorpay'])->change();
        });
    }

    public function down(): void
    {
        // Rollback to previous enum if needed
        Schema::table('tickets', function (Blueprint $table) {
            $table->enum('payment_method', ['hessappay', 'doorpay'])->change();
        });
    }
};
