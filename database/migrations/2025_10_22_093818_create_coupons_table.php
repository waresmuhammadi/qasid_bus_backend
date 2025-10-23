<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id(); // primary key
            $table->string('code')->unique(); // coupon code
            $table->decimal('amount', 10, 2); // discount amount
            $table->date('expiry_date')->nullable(); // optional expiry
            $table->integer('usage_limit')->nullable(); // optional usage limit
            $table->timestamps(); // created_at and updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
