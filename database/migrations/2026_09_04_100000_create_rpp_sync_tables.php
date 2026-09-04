<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rpps', function (Blueprint $table): void {
            $table->timestamp('source_updated_at')->nullable()->after('legacy_metadata');
            $table->string('source_payload_hash', 64)->nullable()->after('source_updated_at');
            $table->timestamp('source_synced_at')->nullable()->after('source_payload_hash');
            $table->index('source_updated_at');
        });
        Schema::table('rpp_promes', function (Blueprint $table): void {
            $table->string('legacy_source_id')->nullable()->unique()->after('nama_file');
            $table->timestamp('source_updated_at')->nullable()->after('legacy_source_id');
            $table->softDeletes();
        });
        Schema::create('rpp_sync_states', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 50)->unique();
            $table->text('cursor')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
        Schema::create('rpp_sync_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event_type', 40);
            $table->string('entity_id');
            $table->timestamp('occurred_at');
            $table->string('payload_hash', 64);
            $table->string('status', 20)->default('received');
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['event_type', 'entity_id']);
        });
        Schema::create('rpp_sync_conflicts', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 50);
            $table->string('source_type', 30);
            $table->string('source_id');
            $table->string('reason', 255);
            $table->json('details')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['source', 'source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rpp_sync_conflicts');
        Schema::dropIfExists('rpp_sync_events');
        Schema::dropIfExists('rpp_sync_states');
        Schema::table('rpp_promes', function (Blueprint $table): void { $table->dropSoftDeletes(); $table->dropColumn(['legacy_source_id', 'source_updated_at']); });
        Schema::table('rpps', function (Blueprint $table): void { $table->dropIndex(['source_updated_at']); $table->dropColumn(['source_updated_at', 'source_payload_hash', 'source_synced_at']); });
    }
};
