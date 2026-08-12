<?php

declare(strict_types=1);

use App\Models\CommercialSale;
use App\Support\Commercial\ProposalListStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_proposals', function (Blueprint $table) {
            $table->string('list_status', 32)
                ->nullable()
                ->default(ProposalListStatus::OPEN)
                ->after('is_closed');
        });

        DB::table('commercial_proposals')
            ->where('is_closed', true)
            ->update(['list_status' => ProposalListStatus::CLOSED]);

        $partialProposalIds = DB::table('commercial_sales')
            ->where('status', CommercialSale::STATUS_PARCIAL)
            ->pluck('proposal_id');

        if ($partialProposalIds->isNotEmpty()) {
            DB::table('commercial_proposals')
                ->whereIn('id', $partialProposalIds)
                ->update(['list_status' => ProposalListStatus::IN_PROGRESS]);
        }
    }

    public function down(): void
    {
        Schema::table('commercial_proposals', function (Blueprint $table) {
            $table->dropColumn('list_status');
        });
    }
};
