<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The reason becomes optional and long enough for the attendee's note, and a finished refund is
 * SUCCEEDED, the same word the payments use.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE refunds DROP CHECK refunds_status_check');
        DB::table('refunds')->where('status', 'COMPLETED')->update(['status' => 'SUCCEEDED']);
        DB::statement("ALTER TABLE refunds ADD CONSTRAINT refunds_status_check CHECK (status IN ('PENDING', 'SUCCEEDED', 'FAILED'))");

        Schema::table('refunds', function (Blueprint $table) {
            $table->string('reason', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('refunds')->whereNull('reason')->update(['reason' => '']);

        Schema::table('refunds', function (Blueprint $table) {
            $table->string('reason')->nullable(false)->change();
        });

        DB::statement('ALTER TABLE refunds DROP CHECK refunds_status_check');
        DB::table('refunds')->where('status', 'SUCCEEDED')->update(['status' => 'COMPLETED']);
        DB::statement("ALTER TABLE refunds ADD CONSTRAINT refunds_status_check CHECK (status IN ('PENDING', 'COMPLETED', 'FAILED'))");
    }
};
