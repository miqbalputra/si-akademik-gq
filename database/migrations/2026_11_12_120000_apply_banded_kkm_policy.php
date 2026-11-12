<?php

use App\Services\DiniyyahKkmPolicyRollout;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(DiniyyahKkmPolicyRollout::class)->initializeExisting();
    }

    public function down(): void
    {
        // Policy versions and the original approved history must survive a code rollback.
    }
};
