<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED (PK)

            // Just store company id (no FK since companies table is in another project)
            $table->unsignedBigInteger('company_id');

            // Trip info
            $table->string('from');
            $table->string('to');
            $table->time('departure_time');
            $table->date('departure_date');
            $table->string('departure_terminal');
            $table->string('arrival_terminal');
            $table->decimal('price', 10, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
