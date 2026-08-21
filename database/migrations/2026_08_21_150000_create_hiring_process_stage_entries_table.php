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
        Schema::create('hiring_process_stage_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hiring_process_id')->constrained('hiring_processes')->cascadeOnDelete();
            $table->string('stage', 64);
            $table->text('notes')->nullable();
            $table->unsignedInteger('candidates_count')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hiring_process_id', 'stage']);
            $table->index(['hiring_process_id', 'stage']);
        });

        // Preserva dados já existentes nos campos globais como ficha da etapa atual.
        $now = now();
        $rows = DB::table('hiring_processes')
            ->where(function ($q) {
                $q->whereNotNull('notes')
                    ->orWhereNotNull('candidates_count');
            })
            ->get(['id', 'current_stage', 'notes', 'candidates_count', 'updated_by', 'notes_at', 'candidates_count_at']);

        foreach ($rows as $row) {
            $updatedAt = $row->notes_at ?? $row->candidates_count_at ?? $now;
            DB::table('hiring_process_stage_entries')->insert([
                'hiring_process_id' => $row->id,
                'stage' => $row->current_stage,
                'notes' => $row->notes,
                'candidates_count' => $row->candidates_count,
                'created_by' => $row->updated_by,
                'created_at' => $updatedAt,
                'updated_at' => $updatedAt,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hiring_process_stage_entries');
    }
};
