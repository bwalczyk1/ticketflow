<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 160);
            $table->text('description');
            $table->string('venue', 160);
            $table->string('city', 100);
            $table->string('category', 50);
            $table->string('timezone', 64)->default('Europe/Warsaw');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('reminder_sent_at')->nullable();
            $table->string('status', 20)->default('DRAFT');
            $table->datetimes();

            $table->index(['status', 'starts_at']);
            $table->index(['city', 'category']);
        });

        DB::statement("ALTER TABLE events ADD CONSTRAINT events_status_check CHECK (status IN ('DRAFT', 'PUBLISHED', 'FINISHED', 'CANCELLED'))");
        DB::statement('ALTER TABLE events ADD CONSTRAINT events_dates_check CHECK (ends_at > starts_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
