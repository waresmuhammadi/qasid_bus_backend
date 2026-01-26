<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->json('locked_seats_vip')->nullable();
            $table->json('locked_by_vip')->nullable();
            $table->json('locked_seats_580')->nullable();
            $table->json('locked_by_580')->nullable();
        });
    }

    public function down()
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['locked_seats_vip', 'locked_by_vip', 'locked_seats_580', 'locked_by_580']);
        });
    }
};