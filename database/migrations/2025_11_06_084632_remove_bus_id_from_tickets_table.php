<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['bus_id']);
            
            // Remove the bus_id column
            $table->dropColumn('bus_id');
            
            // Add bus_number_plate column
            $table->string('bus_number_plate')->nullable()->after('driver_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Remove bus_number_plate
            $table->dropColumn('bus_number_plate');
            
            // Add back bus_id
            $table->foreignId('bus_id')->nullable()->constrained()->onDelete('cascade');
        });
    }
};