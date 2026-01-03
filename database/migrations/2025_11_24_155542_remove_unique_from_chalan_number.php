<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('chalans', function (Blueprint $table) {
            $table->dropUnique(['chalan_number']); // remove UNIQUE index
        });
    }

    public function down()
    {
        Schema::table('chalans', function (Blueprint $table) {
            $table->unique('chalan_number'); // back to unique if rollback
        });
    }
};
