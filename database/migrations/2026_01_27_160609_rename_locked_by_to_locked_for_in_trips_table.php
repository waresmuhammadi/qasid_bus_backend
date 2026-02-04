<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // For MySQL
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE trips CHANGE locked_by_vip locked_for_vip JSON NULL');
            DB::statement('ALTER TABLE trips CHANGE locked_by_580 locked_for_580 JSON NULL');
        }
        // For PostgreSQL
        elseif (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE trips RENAME COLUMN locked_by_vip TO locked_for_vip');
            DB::statement('ALTER TABLE trips RENAME COLUMN locked_by_580 TO locked_for_580');
        }
        // For SQLite
        elseif (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite doesn't support RENAME COLUMN easily, need to recreate table
            // This is more complex for SQLite
        }
    }

    public function down()
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE trips CHANGE locked_for_vip locked_by_vip JSON NULL');
            DB::statement('ALTER TABLE trips CHANGE locked_for_580 locked_by_580 JSON NULL');
        }
        elseif (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE trips RENAME COLUMN locked_for_vip TO locked_by_vip');
            DB::statement('ALTER TABLE trips RENAME COLUMN locked_for_580 TO locked_by_580');
        }
    }
};