<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\HiringProcessStage;
use App\Models\HiringProcess;
use App\Models\HiringProcessStageEntry;
use App\Models\User;
use App\Support\Hiring\HiringProcessStageRecorder;
use Illuminate\Database\Seeder;

/**
 * Preenche o histórico de fichas por etapa (candidatos + comentário) nos processos
 * de acompanhamento, para demonstração e testes da UI.
 */
class HiringProcessStageHistorySeeder extends Seeder
{
    /** @var array<string, array{candidates: int, notes: string}> */
    private array $stageSamples = [
        'engenharia_cargo' => [
            'candidates' => 0,
            'notes' => 'Perfil de cargo alinhado com o gestor. Competências técnicas e comportamentais definidas.',
        ],
        'anuncio_vagas' => [
            'candidates' => 42,
            'notes' => 'Vaga publicada no LinkedIn e grupos regionais. Retorno inicial acima da média.',
        ],
        'analise_curriculo' => [
            'candidates' => 28,
            'notes' => 'Triagem concluída. Currículos fora do perfil eliminados; 12 pré-selecionados para entrevista.',
        ],
        'analise_comportamental' => [
            'candidates' => 12,
            'notes' => 'Profiler aplicado. Três candidatos com aderência alta ao perfil da função.',
        ],
        'entrevista_presencial' => [
            'candidates' => 8,
            'notes' => 'Entrevistas presenciais realizadas. Dois candidatos destacados em comunicação e postura.',
        ],
        'entrevista_gestor' => [
            'candidates' => 4,
            'notes' => 'Gestor entrevistou os finalistas. Preferência por candidato com experiência em liderança de equipe.',
        ],
        'visita_empresa' => [
            'candidates' => 2,
            'notes' => 'Visita opcional agendada para conhecer o ambiente e a equipe.',
        ],
    ];

    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@talents.local')->first()
            ?? User::query()->where('is_owner', true)->first();

        $recorder = app(HiringProcessStageRecorder::class);
        $seeded = 0;

        foreach (HiringProcess::query()->with('stageEntries')->orderBy('id')->get() as $process) {
            $currentOrder = $process->current_stage->order();
            $userId = $process->updated_by ?? $admin?->id;

            foreach (HiringProcessStage::ordered() as $stage) {
                if ($stage->order() >= $currentOrder) {
                    break;
                }

                $sample = $this->stageSamples[$stage->value] ?? [
                    'candidates' => max(1, 20 - $stage->order() * 3),
                    'notes' => 'Informações registradas na etapa '.$stage->label().'.',
                ];

                $recorder->upsertEntry(
                    $process,
                    $stage,
                    $sample['notes'],
                    $sample['candidates'],
                    $userId,
                );

                $seeded++;
            }

            // Ficha da etapa atual (rascunho visível no formulário).
            $currentSample = $this->stageSamples[$process->current_stage->value] ?? null;
            if ($currentSample !== null) {
                $recorder->upsertEntry(
                    $process,
                    $process->current_stage,
                    $process->notes ?? $currentSample['notes'],
                    $process->candidates_count ?? $currentSample['candidates'],
                    $userId,
                );

                if ($process->notes === null || $process->candidates_count === null) {
                    $process->notes = $process->notes ?? $currentSample['notes'];
                    $process->candidates_count = $process->candidates_count ?? $currentSample['candidates'];
                    $process->notes_at = $process->notes_at ?? now();
                    $process->candidates_count_at = $process->candidates_count_at ?? now();
                    $process->save();
                }

                $seeded++;
            }
        }

        // Processo demonstrativo com histórico rico (como o caso da gestora).
        $this->seedShowcaseProcess($admin?->id, $recorder);

        $total = HiringProcessStageEntry::query()->count();
        $this->command?->info("Histórico de etapas: {$seeded} ficha(s) gravada(s); total na base: {$total}.");
    }

    private function seedShowcaseProcess(?int $userId, HiringProcessStageRecorder $recorder): void
    {
        $company = \App\Models\Company::query()->where('is_active', true)->orderBy('id')->first();
        if ($company === null) {
            return;
        }

        $process = HiringProcess::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'title' => 'COORDENADOR DE LOJA | GELATO BORELLI',
            ],
            [
                'current_stage' => HiringProcessStage::EntrevistaGestor,
                'notes' => 'Dois finalistas aguardando retorno do gestor após entrevista presencial.',
                'candidates_count' => 2,
                'notes_at' => now(),
                'candidates_count_at' => now(),
                'updated_by' => $userId,
            ],
        );

        $showcaseHistory = [
            HiringProcessStage::EngenhariaCargo->value => ['candidates' => 0, 'notes' => 'Cargo de coordenação de loja mapeado com foco em liderança operacional e atendimento.'],
            HiringProcessStage::AnuncioVagas->value => ['candidates' => 35, 'notes' => 'Anúncio veiculado. Boa aderência de candidatos da região.'],
            HiringProcessStage::AnaliseCurriculo->value => ['candidates' => 22, 'notes' => 'Currículos triados. Perfil com experiência em varejo alimentício priorizado.'],
            HiringProcessStage::AnaliseComportamental->value => ['candidates' => 14, 'notes' => 'Análise comportamental concluída. Cinco perfis compatíveis com a cultura.'],
            HiringProcessStage::EntrevistaPresencial->value => ['candidates' => 19, 'notes' => 'Entrevista presencial realizada em 21/08. Destaque para candidatos com experiência em gestão de equipe de balcão.'],
        ];

        foreach ($showcaseHistory as $stageValue => $data) {
            $recorder->upsertEntry(
                $process,
                HiringProcessStage::from($stageValue),
                $data['notes'],
                $data['candidates'],
                $userId,
            );
        }

        $recorder->upsertEntry(
            $process,
            HiringProcessStage::EntrevistaGestor,
            $process->notes,
            $process->candidates_count,
            $userId,
        );
    }
}
