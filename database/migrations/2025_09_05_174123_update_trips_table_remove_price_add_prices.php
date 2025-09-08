<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            // Remove the old single price column
            if (Schema::hasColumn('trips', 'price')) {
                $table->dropColumn('price');
            }
            
            // Add prices column as JSON to store different prices for different bus types
            if (!Schema::hasColumn('trips', 'prices')) {
                $table->json('prices')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            // Reverse the changes if we need to rollback
            if (!Schema::hasColumn('trips', 'price')) {
                $table->decimal('price', 10, 2)->nullable();
            }
            
            if (Schema::hasColumn('trips', 'prices')) {
                $table->dropColumn('prices');
            }
        });
    }
};