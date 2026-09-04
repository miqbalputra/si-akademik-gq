<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rpp_sync_mappings', function (Blueprint $table): void {
            $table->id();
            $table->string('mapping_type', 30);
            $table->string('source_id');
            $table->unsignedBigInteger('target_id');
            $table->timestamps();
            $table->unique(['mapping_type', 'source_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('rpp_sync_mappings'); }
};
