<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cleaners', function (Blueprint $table) {
            $table->id();
            $table->string('cleaner_name');
            $table->string('cleaner_phone');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cleaners');
    }
};
