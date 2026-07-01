<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('nik')->nullable()->after('nis');
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->string('niy')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('nik');
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('niy');
        });
    }
};
