<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('trip_id');

            $table->json('seat_numbers');

            $table->string('name');
            $table->string('phone');
            $table->string('email');

            $table->enum('bus_type', ['VIP', '580']);
            $table->date('departure_date')->nullable();

            $table->string('coupon_code')->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('final_price', 10, 2);

            $table->enum('status', ['pending', 'paid', 'failed'])->default('pending');

            $table->string('from_website')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
