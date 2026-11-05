<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->string('reason');
            $table->string('status', 20)->default('PENDING');
            $table->datetimes();
        });

        DB::statement("ALTER TABLE refunds ADD CONSTRAINT refunds_status_check CHECK (status IN ('PENDING', 'COMPLETED', 'FAILED'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
