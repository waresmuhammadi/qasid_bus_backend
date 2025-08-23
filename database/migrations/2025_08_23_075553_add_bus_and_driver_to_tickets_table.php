<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('bus_id')->nullable()->after('trip_id');
            $table->unsignedBigInteger('driver_id')->nullable()->after('bus_id');

            $table->foreign('bus_id')->references('id')->on('buses')->onDelete('set null');
            $table->foreign('driver_id')->references('id')->on('drivers')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['bus_id']);
            $table->dropForeign(['driver_id']);
            $table->dropColumn(['bus_id', 'driver_id']);
        });
    }
};
