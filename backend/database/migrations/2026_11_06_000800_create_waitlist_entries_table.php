<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status', 20)->default('WAITING');
            $table->dateTime('offered_until')->nullable();
            $table->datetimes();

            // MySQL has no partial indexes: the generated column is NULL for closed entries.
            $table->unsignedTinyInteger('active_key')->nullable()
                ->storedAs("IF(status IN ('WAITING', 'OFFERED'), 1, NULL)");

            $table->unique(['user_id', 'ticket_type_id', 'active_key']);
            $table->index(['ticket_type_id', 'status', 'id']);
        });

        DB::statement("ALTER TABLE waitlist_entries ADD CONSTRAINT waitlist_status_check CHECK (status IN ('WAITING', 'OFFERED', 'CONVERTED', 'EXPIRED'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
