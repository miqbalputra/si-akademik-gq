<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('classroom_term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('diniyyah_subjects')->nullOnDelete();
            $table->string('audience_role');
            $table->string('metric_key');
            $table->string('metric_label')->nullable();
            $table->decimal('metric_value_numeric', 10, 2)->nullable();
            $table->string('metric_value_text')->nullable();
            $table->jsonb('metric_payload')->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('academic_term_id');
            $table->index('classroom_term_id');
            $table->index('subject_id');
            $table->index('audience_role');
            $table->index('metric_key');
            $table->index('generated_at');
            $table->index('expires_at');
        });

        Schema::create('panel_saved_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('panel_key');
            $table->string('page_key');
            $table->string('name');
            $table->jsonb('filters')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('user_id');
            $table->index('panel_key');
            $table->index('page_key');
        });

        Schema::create('panel_user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('panel_key');
            $table->jsonb('preferences')->nullable();
            $table->jsonb('dashboard_layout')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'panel_key']);
            $table->index('panel_key');
        });

        Schema::create('panel_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('audience_role')->nullable();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('severity')->default('info');
            $table->string('notification_type')->default('system');
            $table->string('link_url')->nullable();
            $table->string('status')->default('unread');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('audience_role');
            $table->index('status');
            $table->index('notification_type');
            $table->index('created_at');
        });

        Schema::create('report_export_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('export_type');
            $table->string('panel_key')->nullable();
            $table->jsonb('filters')->nullable();
            $table->string('status')->default('queued');
            $table->string('file_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('requested_by');
            $table->index('export_type');
            $table->index('status');
            $table->index('requested_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_export_requests');
        Schema::dropIfExists('panel_notifications');
        Schema::dropIfExists('panel_user_preferences');
        Schema::dropIfExists('panel_saved_filters');
        Schema::dropIfExists('dashboard_metric_snapshots');
    }
};