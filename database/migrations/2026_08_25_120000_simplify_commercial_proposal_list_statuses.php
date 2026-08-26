<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('commercial_proposals')
            ->whereIn('list_status', ['negotiation', 'in_progress'])
            ->update(['list_status' => 'open']);

        DB::table('commercial_proposals')
            ->where('list_status', 'approved')
            ->update(['list_status' => 'closed']);
    }

    public function down(): void
    {
        DB::table('commercial_proposals')
            ->where('list_status', 'closed')
            ->update(['list_status' => 'approved']);
    }
};
