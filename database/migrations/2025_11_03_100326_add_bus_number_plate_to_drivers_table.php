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
    Schema::table('drivers', function (Blueprint $table) {
        $table->string('bus_number_plate')->nullable(); // ✅ new column
    });
}

public function down()
{
    Schema::table('drivers', function (Blueprint $table) {
        $table->dropColumn('bus_number_plate');
    });
}

};
