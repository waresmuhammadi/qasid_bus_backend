<?php

// database/migrations/xxxx_xx_xx_create_buses_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('buses', function (Blueprint $table) {
            $table->id();
            $table->string('bus_no');          // internal bus number
            $table->string('number_plate');    // license plate number
            $table->string('license_number');  // bus license number
            $table->enum('type', ['standard', 'vip']); // type of bus
            $table->string('model');           // model like 580 or 380
            $table->unsignedBigInteger('company_id'); // company owner
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('buses');
    }
};
