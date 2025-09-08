<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Add new JSON column for multiple seats
            $table->json('seat_numbers')->nullable()->after('trip_id');

            // Add bus_type / bus_model column
            $table->string('bus_type')->nullable()->after('seat_numbers');

            // Make old seat_number optional for backward compatibility
            $table->string('seat_number')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['seat_numbers', 'bus_type']);
            $table->string('seat_number')->nullable(false)->change();
        });
    }
};
