<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->uuid('code')->unique();
            $table->string('status', 20)->default('VALID');
            $table->dateTime('checked_in_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->datetimes();

            $table->unique(['order_item_id', 'position']);
        });

        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_status_check CHECK (status IN ('VALID', 'USED', 'VOID'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
