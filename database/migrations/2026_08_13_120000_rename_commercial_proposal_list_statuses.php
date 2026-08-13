<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('commercial_proposals')
            ->where('list_status', 'in_progress')
            ->update(['list_status' => 'negotiation']);

        DB::table('commercial_proposals')
            ->where('list_status', 'closed')
            ->update(['list_status' => 'approved']);
    }

    public function down(): void
    {
        DB::table('commercial_proposals')
            ->where('list_status', 'negotiation')
            ->update(['list_status' => 'in_progress']);

        DB::table('commercial_proposals')
            ->where('list_status', 'approved')
            ->update(['list_status' => 'closed']);

        // Encerradas não existiam antes; voltam a «em aberto» no rollback.
        DB::table('commercial_proposals')
            ->where('list_status', 'ended')
            ->update(['list_status' => 'open']);
    }
};
