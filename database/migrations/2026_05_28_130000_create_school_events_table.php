<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('event_type')->default('general')->index();
            $table->date('starts_on')->index();
            $table->date('ends_on')->index();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->boolean('show_to_teachers')->default(true);
            $table->boolean('show_to_guardians')->default(true);
            $table->timestamps();

            $table->index(['academic_term_id', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_events');
    }
};
