<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('buses', function (Blueprint $table) {
            // Remove 'model' column
            if (Schema::hasColumn('buses', 'model')) {
                $table->dropColumn('model');
            }

            // Change 'type' column to accept only 'vip' and '580'
            $table->enum('type', ['vip', '580'])->change();
        });
    }

    public function down()
    {
        Schema::table('buses', function (Blueprint $table) {
            // Add 'model' back (as string)
            $table->string('model')->nullable();

            // Revert 'type' column back to original values
            $table->enum('type', ['standard', 'vip'])->change();
        });
    }
};
