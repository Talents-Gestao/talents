<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modelos de feedback criados pela Talents passam a ser universais (company_id nulo).
 *
 * Converte só os modelos com empresa cujo autor (created_by) é super_admin (papel do usuário ou
 * workspace Talents); guarda a empresa antiga em converted_from_company_id. Modelos do RH do
 * cliente (company_admin/company_user) ou sem autor não mudam. Os feedbacks já feitos continuam
 * na empresa original (feedback_sessions.company_id não é tocado). down() restaura a empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback_templates', function (Blueprint $table) {
            $table->foreignId('converted_from_company_id')
                ->nullable()
                ->after('company_id')
                ->constrained('companies')
                ->nullOnDelete();
        });

        $this->convertTalentsTemplates();
    }

    public function down(): void
    {
        $this->restoreConvertedTemplates();

        Schema::table('feedback_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_from_company_id');
        });
    }

    /** Modelos com empresa criados por super_admin viram universais; a empresa antiga fica registrada. */
    public function convertTalentsTemplates(): int
    {
        return DB::table('feedback_templates')
            ->whereNotNull('company_id')
            ->whereNotNull('created_by')
            ->where(function (Builder $q) {
                $q->whereIn('created_by', DB::table('users')->select('id')->where('role', 'super_admin'))
                    ->orWhereIn('created_by', DB::table('user_workspaces')
                        ->select('user_id')
                        ->where('workspace_type', 'talents')
                        ->where('role', 'super_admin'));
            })
            ->update([
                'converted_from_company_id' => DB::raw('company_id'),
                'company_id' => null,
            ]);
    }

    /** Devolve cada modelo convertido à empresa de origem. */
    public function restoreConvertedTemplates(): int
    {
        return DB::table('feedback_templates')
            ->whereNotNull('converted_from_company_id')
            ->update([
                'company_id' => DB::raw('converted_from_company_id'),
                'converted_from_company_id' => null,
            ]);
    }
};
