<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateProposalIds = DB::table('commercial_sales')
            ->select('proposal_id')
            ->whereNotNull('proposal_id')
            ->groupBy('proposal_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('proposal_id');

        foreach ($duplicateProposalIds as $proposalId) {
            $ids = DB::table('commercial_sales')
                ->where('proposal_id', $proposalId)
                ->orderBy('id')
                ->pluck('id');
            $keep = $ids->shift();
            unset($keep);

            if ($ids->isNotEmpty()) {
                DB::table('commercial_sales')->whereIn('id', $ids)->delete();
            }
        }

        $duplicateSaleIds = DB::table('commercial_commissions')
            ->select('sale_id')
            ->groupBy('sale_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('sale_id');

        foreach ($duplicateSaleIds as $saleId) {
            $ids = DB::table('commercial_commissions')
                ->where('sale_id', $saleId)
                ->orderBy('id')
                ->pluck('id');
            $keep = $ids->shift();
            unset($keep);

            if ($ids->isNotEmpty()) {
                DB::table('commercial_commissions')->whereIn('id', $ids)->delete();
            }
        }

        Schema::table('commercial_sales', function (Blueprint $table) {
            $table->unique('proposal_id');
        });

        Schema::table('commercial_commissions', function (Blueprint $table) {
            $table->unique('sale_id');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_commissions', function (Blueprint $table) {
            $table->dropUnique(['sale_id']);
        });

        Schema::table('commercial_sales', function (Blueprint $table) {
            $table->dropUnique(['proposal_id']);
        });
    }
};
