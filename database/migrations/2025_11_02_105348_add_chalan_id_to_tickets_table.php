<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('chalan_id')->nullable()->after('id');
            $table->foreign('chalan_id')->references('id')->on('chalans')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['chalan_id']);
            $table->dropColumn('chalan_id');
        });
    }
};
