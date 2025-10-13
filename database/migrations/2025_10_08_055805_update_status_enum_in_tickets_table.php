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
            // Update the enum to include the new 'canceled' option
            $table->enum('status', ['riding', 'arrived', 'stopped', 'cancelled'])
                  ->default('stopped')
                  ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Roll back to the old enum without 'canceled'
            $table->enum('status', ['riding', 'arrived', 'stopped'])
                  ->default('stopped')
                  ->change();
        });
    }
};
