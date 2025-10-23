<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Add 'in_processing' to status enum
        DB::statement("ALTER TABLE `tickets` MODIFY COLUMN `status` ENUM('riding', 'arrived', 'stopped', 'cancelled', 'in_processing') NOT NULL DEFAULT 'stopped'");
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // Remove 'in_processing' from status enum
        DB::statement("ALTER TABLE `tickets` MODIFY COLUMN `status` ENUM('riding', 'arrived', 'stopped', 'cancelled') NOT NULL DEFAULT 'stopped'");
    }
};
