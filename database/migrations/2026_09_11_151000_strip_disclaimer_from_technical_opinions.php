<?php

declare(strict_types=1);

use App\Support\TechnicalOpinionDisclaimerStripper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('action_plans')) {
            DB::table('action_plans')
                ->whereNotNull('technical_opinion')
                ->orderBy('id')
                ->each(function (object $row): void {
                    $cleaned = TechnicalOpinionDisclaimerStripper::strip($row->technical_opinion);
                    if ($cleaned === $row->technical_opinion) {
                        return;
                    }

                    DB::table('action_plans')
                        ->where('id', $row->id)
                        ->update(['technical_opinion' => $cleaned]);
                });
        }

        if (! Schema::hasTable('ai_analyses')) {
            return;
        }

        DB::table('ai_analyses')
            ->where('type', 'nr1_technical_opinion')
            ->whereNotNull('content')
            ->orderBy('id')
            ->each(function (object $row): void {
                $cleaned = TechnicalOpinionDisclaimerStripper::strip($row->content);
                if ($cleaned === $row->content) {
                    return;
                }

                DB::table('ai_analyses')
                    ->where('id', $row->id)
                    ->update(['content' => $cleaned]);
            });
    }

    public function down(): void
    {
        // Conteúdo de disclaimer já gerado não é restaurado.
    }
};
