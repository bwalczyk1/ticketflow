<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('PENDING');
            $table->unsignedInteger('amount');
            $table->string('provider_ref', 191)->nullable();
            $table->datetimes();
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('PENDING', 'SUCCEEDED', 'FAILED'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
