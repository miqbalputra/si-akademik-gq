<?php

use App\Services\DiniyyahSchoolKkm;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Data-only rollout: open scopes receive a versioned school policy; locked history is preserved.
        app(DiniyyahSchoolKkm::class)->initializeExisting();
    }

    public function down(): void
    {
        // Never delete KKM versions or restore stale thresholds when rolling back application code.
    }
};
