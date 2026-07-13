<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategic_calendar_item_company', function (Blueprint $table) {
            $table->id();
            $table->foreignId('strategic_calendar_item_id')
                ->constrained('strategic_calendar_items')
                ->cascadeOnDelete();
            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['strategic_calendar_item_id', 'company_id'],
                'strategic_calendar_item_company_unique',
            );
        });

        DB::table('strategic_calendar_items')
            ->whereNotNull('company_id')
            ->orderBy('id')
            ->each(function (object $row): void {
                DB::table('strategic_calendar_item_company')->insertOrIgnore([
                    'strategic_calendar_item_id' => $row->id,
                    'company_id' => $row->company_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategic_calendar_item_company');
    }
};
