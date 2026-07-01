<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_events', function (Blueprint $table) {
            $table->string('target_scope')->default('all')->after('event_type')->index();
            $table->string('target_level_name')->nullable()->after('target_scope')->index();
            $table->string('target_gender_group')->nullable()->after('target_level_name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('school_events', function (Blueprint $table) {
            $table->dropIndex(['target_scope']);
            $table->dropIndex(['target_level_name']);
            $table->dropIndex(['target_gender_group']);
            $table->dropColumn(['target_scope', 'target_level_name', 'target_gender_group']);
        });
    }
};
