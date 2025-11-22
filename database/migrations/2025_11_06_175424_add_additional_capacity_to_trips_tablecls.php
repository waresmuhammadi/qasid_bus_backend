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
        $table->integer('additional_capacity')->default(0)->after('bus_type');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips_tablecls', function (Blueprint $table) {
            //
        });
    }
};
