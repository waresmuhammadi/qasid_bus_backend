<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
    $table->id();
    $table->string('from');
    $table->string('to');
    $table->time('departure_time');
    $table->date('departure_date');
    $table->string('departure_terminal');
    $table->string('arrival_terminal');
    $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
