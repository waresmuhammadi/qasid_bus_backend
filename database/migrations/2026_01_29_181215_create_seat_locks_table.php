<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('seat_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->onDelete('cascade');
            $table->string('bus_type');
            $table->date('departure_date')->nullable(); // null = all days trip
            $table->integer('seat_number');
            $table->string('locked_for'); // "https://qasid.af" or "https://me.com"
            $table->timestamps();
            
            // Unique constraint: can't lock same seat for same trip+bus+date
            $table->unique(['trip_id', 'bus_type', 'departure_date', 'seat_number']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('seat_locks');
    }
};