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
        $moduleId = DB::table('modules')->where('key', 'acompanhamento')->value('id');
        if (! $moduleId) {
            DB::table('modules')->insert([
                'key' => 'acompanhamento',
                'name' => 'Acompanhamento',
                'description' => 'Acompanhamento visual das fases de contratação (processo operacional na Sólides).',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $moduleId = DB::table('modules')->where('key', 'acompanhamento')->value('id');
        }

        $planIds = DB::table('plans')->pluck('id');
        foreach ($planIds as $planId) {
            $exists = DB::table('module_plan')
                ->where('plan_id', $planId)
                ->where('module_id', $moduleId)
                ->exists();

            if (! $exists) {
                DB::table('module_plan')->insert([
                    'plan_id' => $planId,
                    'module_id' => $moduleId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('acompanhamento_access')->nullable()->after('desligamento_access');
        });

        Schema::create('hiring_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('current_stage', 64)->default('analise_curriculo');
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'current_stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hiring_processes');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('acompanhamento_access');
        });

        $id = DB::table('modules')->where('key', 'acompanhamento')->value('id');
        if ($id) {
            DB::table('module_plan')->where('module_id', $id)->delete();
            DB::table('modules')->where('id', $id)->delete();
        }
    }
};
