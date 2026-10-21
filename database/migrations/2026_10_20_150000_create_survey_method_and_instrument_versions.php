<?php

use App\Support\Nr1InstrumentMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 — versão de método e de instrumento por campanha.
 *
 * Backfill: toda campanha (inclusive com soft delete) e toda linha de survey_results
 * passam a apontar para production_v1. average_score e risk_level não são tocados.
 * Nenhuma campanha aponta para talents39_v1.
 */
return new class extends Migration
{
    private const PRODUCTION_V1 = 'production_v1';

    private const TALENTS39_V1 = 'talents39_v1';

    public function up(): void
    {
        Schema::create('survey_method_versions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('label');
            $table->string('aggregation', 32);
            $table->string('rounding_mode', 16);
            $table->unsignedSmallInteger('rounding_scale')->nullable();
            $table->string('band_basis', 16);
            $table->decimal('green_max', 4, 2);
            $table->decimal('yellow_max', 4, 2);
            $table->string('weighting', 16);
            $table->decimal('min_item_coverage', 5, 4)->nullable();
            $table->unsignedSmallInteger('min_group_size_new_outputs');
            $table->text('manual_reference')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('survey_instrument_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('instrument_code', 32);
            $table->string('version_label', 32);
            $table->string('source', 16);
            $table->json('item_matrix');
            $table->char('item_matrix_sha256', 64);
            $table->text('validation_reference')->nullable();
            $table->string('polarity_audit_status', 16);
            $table->timestamp('polarity_audit_accepted_at')->nullable();
            $table->foreignId('polarity_audit_accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('frozen_at');
            $table->timestamps();

            $table->unique(['survey_template_id', 'version_label']);
            $table->index(['survey_template_id', 'item_matrix_sha256']);
        });

        Schema::table('surveys', function (Blueprint $table) {
            $table->foreignId('method_version_id')->nullable()->constrained('survey_method_versions');
            $table->foreignId('instrument_version_id')->nullable()->constrained('survey_instrument_versions')->nullOnDelete();
        });

        Schema::table('survey_results', function (Blueprint $table) {
            $table->foreignId('method_version_id')->nullable()->constrained('survey_method_versions');
            $table->decimal('score_numerator', 14, 4)->nullable();
            $table->unsignedInteger('score_denominator')->nullable();
        });

        $this->seedMethodCatalog();
        $this->backfill();
    }

    public function down(): void
    {
        if (Schema::hasTable('survey_method_versions') && Schema::hasColumn('surveys', 'method_version_id')) {
            $talents39Id = DB::table('survey_method_versions')->where('code', self::TALENTS39_V1)->value('id');

            if ($talents39Id !== null && DB::table('surveys')->where('method_version_id', $talents39Id)->exists()) {
                throw new RuntimeException(
                    'Rollback bloqueado: existem campanhas com método talents39_v1. '
                    .'O código revertido recalcularia essas campanhas com a fórmula de produção.'
                );
            }
        }

        if (Schema::hasColumn('survey_results', 'method_version_id')) {
            Schema::table('survey_results', function (Blueprint $table) {
                $table->dropConstrainedForeignId('method_version_id');
                $table->dropColumn(['score_numerator', 'score_denominator']);
            });
        }

        if (Schema::hasColumn('surveys', 'method_version_id')) {
            Schema::table('surveys', function (Blueprint $table) {
                $table->dropConstrainedForeignId('instrument_version_id');
                $table->dropConstrainedForeignId('method_version_id');
            });
        }

        Schema::dropIfExists('survey_instrument_versions');
        Schema::dropIfExists('survey_method_versions');
    }

    private function seedMethodCatalog(): void
    {
        $now = now();

        DB::table('survey_method_versions')->insert([
            [
                'code' => self::PRODUCTION_V1,
                'label' => 'Produção v1 — média das médias por respondente',
                'aggregation' => 'respondent_mean_of_means',
                'rounding_mode' => 'none',
                'rounding_scale' => null,
                'band_basis' => 'raw',
                'green_max' => 2.33,
                'yellow_max' => 3.66,
                'weighting' => 'item_weight',
                'min_item_coverage' => null,
                'min_group_size_new_outputs' => 5,
                'manual_reference' => 'SurveyResultCalculator e Nr1Scoring em 20/10/2026: média ponderada por respondente, média das médias, 3 casas gravadas, faixa sobre o valor bruto.',
                'created_at' => $now,
            ],
            [
                'code' => self::TALENTS39_V1,
                'label' => 'Talents 39 itens v1 — soma das respostas válidas',
                'aggregation' => 'pooled_item_mean',
                'rounding_mode' => 'half_up',
                'rounding_scale' => 2,
                'band_basis' => 'rounded',
                'green_max' => 2.33,
                'yellow_max' => 3.66,
                'weighting' => 'equal',
                'min_item_coverage' => null,
                'min_group_size_new_outputs' => 5,
                'manual_reference' => 'Talents_Metodologia_Dashboard_Equipe.docx v1.0: soma dos escores orientados ÷ respostas válidas, peso igual, duas casas meio para cima sobre a fração exata, faixa após o arredondamento.',
                'created_at' => $now,
            ],
        ]);
    }

    public function backfill(): void
    {
        $productionId = (int) DB::table('survey_method_versions')->where('code', self::PRODUCTION_V1)->value('id');
        $now = now();

        DB::table('survey_templates')->orderBy('id')->get(['id', 'title'])->each(
            function (object $template) use ($now): void {
                $matrix = Nr1InstrumentMatrix::forTemplate((int) $template->id);
                $code = Nr1InstrumentMatrix::instrumentCodeForTitle((string) $template->title);

                $versionId = DB::table('survey_instrument_versions')->insertGetId([
                    'survey_template_id' => $template->id,
                    'instrument_code' => $code,
                    'version_label' => 'v1-backfill',
                    'source' => Nr1InstrumentMatrix::SOURCE_BACKFILL,
                    'item_matrix' => Nr1InstrumentMatrix::encode($matrix),
                    'item_matrix_sha256' => Nr1InstrumentMatrix::hash($matrix),
                    'validation_reference' => null,
                    'polarity_audit_status' => Nr1InstrumentMatrix::initialAuditStatus($code),
                    'frozen_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('surveys')
                    ->where('survey_template_id', $template->id)
                    ->update(['instrument_version_id' => $versionId]);
            }
        );

        DB::table('surveys')->update(['method_version_id' => $productionId]);
        DB::table('survey_results')->update(['method_version_id' => $productionId]);
    }
};
