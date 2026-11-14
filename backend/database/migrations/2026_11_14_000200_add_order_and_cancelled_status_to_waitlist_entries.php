<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An accepted offer points at the order it became, and an entry left by its user or closed
     * with its event gets its own status instead of looking like a lapsed offer.
     */
    public function up(): void
    {
        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('offered_until')->constrained()->restrictOnDelete();

            $table->index(['status', 'offered_until']);
        });

        DB::statement('ALTER TABLE waitlist_entries DROP CHECK waitlist_status_check');
        DB::statement("ALTER TABLE waitlist_entries ADD CONSTRAINT waitlist_status_check CHECK (status IN ('WAITING', 'OFFERED', 'CONVERTED', 'EXPIRED', 'CANCELLED'))");
    }

    public function down(): void
    {
        DB::table('waitlist_entries')->where('status', 'CANCELLED')->update(['status' => 'EXPIRED']);

        DB::statement('ALTER TABLE waitlist_entries DROP CHECK waitlist_status_check');
        DB::statement("ALTER TABLE waitlist_entries ADD CONSTRAINT waitlist_status_check CHECK (status IN ('WAITING', 'OFFERED', 'CONVERTED', 'EXPIRED'))");

        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->dropIndex(['status', 'offered_until']);
            $table->dropConstrainedForeignId('order_id');
        });
    }
};
