<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('price');
            $table->integer('quantity');
            $table->integer('sold')->default(0);
            $table->integer('held')->default(0);
            $table->unsignedInteger('version')->default(0);
            $table->datetimes();

            $table->unique(['event_id', 'name']);
        });

        DB::statement('ALTER TABLE ticket_types ADD CONSTRAINT ticket_types_pool_check CHECK (sold >= 0 AND held >= 0 AND sold + held <= quantity)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_types');
    }
};
