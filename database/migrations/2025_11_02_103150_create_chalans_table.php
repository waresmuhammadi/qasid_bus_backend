<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chalans', function (Blueprint $table) {
            $table->id();
            $table->string('chalan_number')->unique();
            $table->json('ticket_ids'); // store ticket IDs as JSON
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chalans');
    }
};

