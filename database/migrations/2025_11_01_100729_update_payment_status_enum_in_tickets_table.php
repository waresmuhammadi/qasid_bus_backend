<?php



use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // ✅ Change the enum to include "in_processing"
            $table->enum('payment_status', ['paid', 'unpaid', 'in_processing'])
                  ->default('in_processing')
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Rollback to the old version
            $table->enum('payment_status', ['paid', 'unpaid'])
                  ->default('unpaid')
                  ->change();
        });
    }
};
