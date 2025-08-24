<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up()
{
    Schema::create('tickets', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('trip_id');
        $table->string('seat_number');
        $table->string('name');
        $table->string('father_name');
        $table->string('email');
        $table->string('phone');
        $table->timestamps();
        $table->foreign('trip_id')->references('id')->on('trips')->onDelete('cascade');
    });
}

    public function down()
    {
        Schema::dropIfExists('tickets');
    }
};
