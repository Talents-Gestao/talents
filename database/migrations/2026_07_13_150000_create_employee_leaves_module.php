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
        $moduleId = DB::table('modules')->where('key', 'ferias')->value('id');
        if (! $moduleId) {
            DB::table('modules')->insert([
                'key' => 'ferias',
                'name' => 'Férias',
                'description' => 'Gestão de períodos de férias dos colaboradores.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $moduleId = DB::table('modules')->where('key', 'ferias')->value('id');
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
            $table->boolean('ferias_access')->nullable()->after('feedbacks_access');
        });

        Schema::create('employee_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_employee_id')->constrained('company_employees')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 32)->default('scheduled');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'start_date']);
            $table->index(['company_employee_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leaves');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('ferias_access');
        });

        $id = DB::table('modules')->where('key', 'ferias')->value('id');
        if ($id) {
            DB::table('module_plan')->where('module_id', $id)->delete();
            DB::table('modules')->where('id', $id)->delete();
        }
    }
};
