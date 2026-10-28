<?php

namespace App\Console\Commands;

use App\Models\SurveyInstrumentVersion;
use App\Models\SurveyTemplate;
use App\Models\SurveyTemplateQuestion;
use App\Models\SurveyTemplateSection;
use App\Support\Nr1InstrumentMatrix;
use App\Support\SurveyTemplateLock;
use Database\Seeders\Talents39InstrumentData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa o instrumento Talents NR-1 V11 (39 itens). Idempotente: se o template já existe com a
 * mesma matriz, não faz nada; se existe com matriz diferente e está travado, recusa (nova versão = novo import).
 * A polaridade entra como pendente de homologação (nr1:accept-polarity).
 */
class Nr1ImportInstrumentV11Command extends Command
{
    protected $signature = 'nr1:import-instrument-v11';

    protected $description = 'Importa o instrumento V11 (39 itens, 7 dimensões) como template versionado.';

    public function handle(): int
    {
        $expected = $this->expectedMatrix();
        $template = SurveyTemplate::query()->where('title', Talents39InstrumentData::TEMPLATE_TITLE)->first();

        if ($template !== null) {
            $current = Nr1InstrumentMatrix::forTemplate((int) $template->id);

            if ($this->sameContent($current, $expected)) {
                $this->info("O instrumento V11 já está importado (template #{$template->id}).");
                $this->ensureInstrumentVersion($template, $current);

                return self::SUCCESS;
            }

            $this->error(SurveyTemplateLock::isLocked((int) $template->id)
                ? "O template #{$template->id} já está em uso e difere do Apêndice A. Crie uma nova versão pelo Admin em vez de reimportar."
                : "O template #{$template->id} existe com conteúdo diferente do Apêndice A. Revise-o ou apague-o antes de reimportar.");

            return self::FAILURE;
        }

        $template = DB::transaction(function (): SurveyTemplate {
            $template = SurveyTemplate::query()->create([
                'title' => Talents39InstrumentData::TEMPLATE_TITLE,
                'description' => 'Instrumento Talents NR-1 V11: 39 afirmações em 7 dimensões, escala de frequência (Nunca a Sempre).',
                'is_active' => true,
            ]);

            $items = collect(Talents39InstrumentData::items())->groupBy('dimension');

            foreach (Talents39InstrumentData::dimensions() as $order => $dimension) {
                $section = SurveyTemplateSection::query()->create([
                    'survey_template_id' => $template->id,
                    'title' => $dimension,
                    'description' => null,
                    'sort_order' => $order,
                ]);

                foreach ($items[$dimension] as $position => $item) {
                    SurveyTemplateQuestion::query()->create([
                        'survey_template_section_id' => $section->id,
                        'body' => $item['body'],
                        'reverse_score' => $item['reverse_score'],
                        'weight' => 1.0,
                        'response_scale' => 'frequency',
                        'sort_order' => $position,
                    ]);
                }
            }

            return $template;
        });

        $this->ensureInstrumentVersion($template, Nr1InstrumentMatrix::forTemplate((int) $template->id));
        $this->info("Instrumento V11 importado (template #{$template->id}, 39 itens). Polaridade pendente de homologação.");

        return self::SUCCESS;
    }

    /**
     * @return list<array{body: string, section_title: string, reverse_score: bool}>
     */
    private function expectedMatrix(): array
    {
        return array_map(fn (array $i): array => [
            'body' => $i['body'],
            'section_title' => $i['dimension'],
            'reverse_score' => $i['reverse_score'],
        ], Talents39InstrumentData::items());
    }

    /**
     * @param  list<array<string, mixed>>  $current
     * @param  list<array{body: string, section_title: string, reverse_score: bool}>  $expected
     */
    private function sameContent(array $current, array $expected): bool
    {
        $pick = fn (array $row): array => [
            'body' => $row['body'],
            'section_title' => $row['section_title'],
            'reverse_score' => $row['reverse_score'],
        ];

        return array_map($pick, $current) === $expected;
    }

    /**
     * @param  list<array<string, mixed>>  $matrix
     */
    private function ensureInstrumentVersion(SurveyTemplate $template, array $matrix): void
    {
        $hash = Nr1InstrumentMatrix::hash($matrix);

        if (SurveyInstrumentVersion::query()
            ->where('survey_template_id', $template->id)
            ->where('item_matrix_sha256', $hash)
            ->where('instrument_code', Nr1InstrumentMatrix::CODE_TALENTS39)
            ->exists()) {
            return;
        }

        SurveyInstrumentVersion::query()->create([
            'survey_template_id' => $template->id,
            'instrument_code' => Nr1InstrumentMatrix::CODE_TALENTS39,
            'version_label' => Talents39InstrumentData::VERSION_LABEL,
            'source' => Nr1InstrumentMatrix::SOURCE_IMPORT,
            'item_matrix' => $matrix,
            'item_matrix_sha256' => $hash,
            'validation_reference' => Talents39InstrumentData::REFERENCE,
            'polarity_audit_status' => Nr1InstrumentMatrix::initialAuditStatus(Nr1InstrumentMatrix::CODE_TALENTS39),
            'frozen_at' => now(),
        ]);
    }
}
