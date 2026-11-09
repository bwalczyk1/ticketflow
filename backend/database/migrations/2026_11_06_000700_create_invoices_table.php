<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year');
            $table->string('type', 20);
            $table->unsignedInteger('last_number')->default(0);

            $table->primary(['year', 'type']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('corrects_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->string('type', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('number');
            $table->string('pdf_path')->nullable();
            $table->datetimes();

            // MySQL treats NULLs as distinct, so a generated column makes the key work without a refund.
            $table->unsignedBigInteger('refund_key')->storedAs('COALESCE(refund_id, 0)');

            $table->unique(['type', 'year', 'number']);
            $table->unique(['order_id', 'type', 'refund_key']);
        });

        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_type_check CHECK (type IN ('INVOICE', 'CORRECTION'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_sequences');
    }
};
