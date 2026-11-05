<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_sales_summaries', function (Blueprint $table) {
            $table->foreignId('event_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('tickets_sold')->default(0);
            $table->unsignedBigInteger('revenue')->default(0);
            $table->unsignedBigInteger('refunds')->default(0);
            $table->unsignedInteger('checked_in')->default(0);
            $table->datetimes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_sales_summaries');
    }
};
