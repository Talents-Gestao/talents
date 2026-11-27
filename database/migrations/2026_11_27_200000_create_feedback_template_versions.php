<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versões dos modelos de feedback.
 *
 * Cada versão é dona das próprias seções e perguntas (linhas imutáveis depois do primeiro uso); as respostas
 * continuam apontando para a linha da pergunta em que foram dadas, então nenhuma resposta é migrada.
 * Cada feedback (feedback_sessions) aponta para a versão usada na criação.
 *
 * Também: arquivamento do modelo (substitui Ativo/Inativo; os inativos viram arquivados) e cancelamento de
 * convites de assinatura («Reabrir para edição»).
 *
 * Dados: todo modelo (inclusive excluídos) ganha a versão 1 com o snapshot da estrutura atual; seções e
 * feedbacks apontam para ela. Ao final as contagens são conferidas; se não baterem, nada é gravado.
 *
 * down(): só desfaz com a versão 1 em todos os modelos (sem apagar dados). Com versão 2 ou maior, recusa,
 * porque as seções de várias versões cairiam misturadas no mesmo modelo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('structure')->nullable();
            $table->string('structure_hash', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['feedback_template_id', 'number']);
        });

        Schema::table('feedback_templates', function (Blueprint $table) {
            $table->foreignId('current_version_id')->nullable()->after('version')
                ->constrained('feedback_template_versions')->nullOnDelete();
            $table->timestamp('archived_at')->nullable()->after('is_active');
            $table->foreignId('archived_by_user_id')->nullable()->after('archived_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('feedback_template_sections', function (Blueprint $table) {
            $table->foreignId('feedback_template_version_id')->nullable()->after('feedback_template_id')
                ->constrained('feedback_template_versions');
        });

        Schema::table('feedback_sessions', function (Blueprint $table) {
            $table->foreignId('feedback_template_version_id')->nullable()->after('feedback_template_id')
                ->constrained('feedback_template_versions');
        });

        Schema::table('feedback_session_signatures', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('signed_at');
            $table->foreignId('cancelled_by_user_id')->nullable()->after('cancelled_at')
                ->constrained('users')->nullOnDelete();
        });

        DB::transaction(function () {
            $this->backfill();
            $this->assertCounts();
        });
    }

    /**
     * Versão 1 para todo modelo sem versão (inclusive excluídos), com o snapshot da estrutura; seções e feedbacks
     * passam a apontar para ela; modelos inativos ficam arquivados.
     */
    public function backfill(): void
    {
        $now = now();

        foreach (DB::table('feedback_templates')->whereNull('current_version_id')->orderBy('id')->get() as $template) {
            $structure = $this->structureOf((int) $template->id);
            $json = json_encode($structure, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $versionId = DB::table('feedback_template_versions')->insertGetId([
                'feedback_template_id' => $template->id,
                'number' => 1,
                'title' => $template->title,
                'description' => $template->description,
                'structure' => $json,
                'structure_hash' => hash('sha256', $json),
                'created_by' => $template->created_by,
                'created_at' => $template->created_at ?? $now,
                'updated_at' => $now,
            ]);

            DB::table('feedback_template_sections')
                ->where('feedback_template_id', $template->id)
                ->update(['feedback_template_version_id' => $versionId]);

            DB::table('feedback_sessions')
                ->where('feedback_template_id', $template->id)
                ->update(['feedback_template_version_id' => $versionId]);

            $update = ['current_version_id' => $versionId];
            if (! $template->is_active) {
                // Ativo/Inativo vira Arquivar/Reativar: o inativo de hoje fica arquivado.
                $update['archived_at'] = $template->updated_at ?? $now;
            }
            DB::table('feedback_templates')->where('id', $template->id)->update($update);
        }
    }

    /**
     * Conferência final: um modelo = uma versão 1; nenhum modelo, feedback ou seção sem versão.
     *
     * @throws RuntimeException quando as contagens não batem (a transação desfaz tudo)
     */
    public function assertCounts(): void
    {
        $templates = DB::table('feedback_templates')->count();
        $firstVersions = DB::table('feedback_template_versions')->where('number', 1)->count();
        $withoutCurrent = DB::table('feedback_templates')->whereNull('current_version_id')->count();
        $sessionsWithoutVersion = DB::table('feedback_sessions')->whereNull('feedback_template_version_id')->count();
        $sectionsWithoutVersion = DB::table('feedback_template_sections')->whereNull('feedback_template_version_id')->count();

        if ($templates !== $firstVersions || $withoutCurrent !== 0 || $sessionsWithoutVersion !== 0 || $sectionsWithoutVersion !== 0) {
            throw new RuntimeException(sprintf(
                'Versões de modelos de feedback: contagens não conferem (modelos=%d, versões 1=%d, modelos sem versão atual=%d, feedbacks sem versão=%d, seções sem versão=%d). Nada foi gravado.',
                $templates, $firstVersions, $withoutCurrent, $sessionsWithoutVersion, $sectionsWithoutVersion,
            ));
        }
    }

    public function down(): void
    {
        $this->assertReversible();

        Schema::table('feedback_session_signatures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by_user_id');
            $table->dropColumn('cancelled_at');
        });

        Schema::table('feedback_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('feedback_template_version_id');
        });

        Schema::table('feedback_template_sections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('feedback_template_version_id');
        });

        Schema::table('feedback_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('archived_by_user_id');
            $table->dropColumn('archived_at');
            $table->dropConstrainedForeignId('current_version_id');
        });

        Schema::dropIfExists('feedback_template_versions');
    }

    /**
     * Só a versão 1 em todos os modelos: reverter não apaga nem mistura dados.
     *
     * @throws RuntimeException quando já existe versão 2 ou maior
     */
    public function assertReversible(): void
    {
        if (DB::table('feedback_template_versions')->where('number', '>', 1)->exists()) {
            throw new RuntimeException(
                'Não é possível reverter as versões de modelos de feedback: já existem modelos com versão 2 ou maior. '
                .'Reverter misturaria as seções de várias versões no mesmo modelo.'
            );
        }
    }

    /**
     * Snapshot canônico (mesmo formato de App\Support\Feedback\FeedbackTemplateStructure::canonical()).
     *
     * @return list<array<string, mixed>>
     */
    private function structureOf(int $templateId): array
    {
        $sections = DB::table('feedback_template_sections')
            ->where('feedback_template_id', $templateId)
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        return $sections->map(function ($section) {
            $questions = DB::table('feedback_template_questions')
                ->where('feedback_template_section_id', $section->id)
                ->orderBy('sort_order')->orderBy('id')
                ->get();

            return [
                'key' => (string) $section->key,
                'section_type' => (string) ($section->section_type ?: 'questions'),
                'audience' => (string) ($section->audience ?: 'both'),
                'title' => (string) $section->title,
                'description' => (string) ($section->description ?? ''),
                'questions' => $questions->map(fn ($q) => [
                    'key' => (string) $q->key,
                    'body' => (string) $q->body,
                    'question_type' => (string) $q->question_type,
                    'is_required' => (bool) $q->is_required,
                    'options' => array_values(array_map(
                        fn ($o) => ['value' => (string) ($o['value'] ?? ''), 'label' => (string) ($o['label'] ?? '')],
                        json_decode((string) ($q->options ?? 'null'), true) ?: [],
                    )),
                ])->values()->all(),
            ];
        })->values()->all();
    }
};
