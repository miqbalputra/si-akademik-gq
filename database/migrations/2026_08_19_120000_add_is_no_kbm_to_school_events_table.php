<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_events', function (Blueprint $table): void {
            $table->boolean('is_no_kbm')->default(false)->after('event_type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('school_events', function (Blueprint $table): void {
            $table->dropIndex(['is_no_kbm']);
            $table->dropColumn('is_no_kbm');
        });
    }
};
