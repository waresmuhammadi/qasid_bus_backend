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
        Schema::table('trips', function (Blueprint $table) {
            // Change additional_capacity from integer to JSON to store {"VIP": 2, "580": 5}
            $table->json('additional_capacity')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('trips', function (Blueprint $table) {
            // Convert back to integer (you'll lose the JSON data)
            $table->integer('additional_capacity')->default(0)->change();
        });
    }
};