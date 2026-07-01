<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homeroom_assignments', function (Blueprint $table) {
            $table->unique(['classroom_term_id', 'teacher_id'], 'homeroom_assignments_unique_idx');
        });
    }

    public function down(): void
    {
        Schema::table('homeroom_assignments', function (Blueprint $table) {
            $table->dropUnique('homeroom_assignments_unique_idx');
        });
    }
};
