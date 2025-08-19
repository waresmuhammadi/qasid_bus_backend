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
        $table->dropColumn('father_name');
        $table->dropColumn('email');
        $table->string('last_name')->after('name');
        $table->enum('payment_method', ['hessappay', 'doorpay'])->after('phone');
    });
}

public function down()
{
    Schema::table('tickets', function (Blueprint $table) {
        $table->dropColumn('last_name');
        $table->dropColumn('payment_method');
        $table->string('father_name');
        $table->string('email');
    });
}

};
