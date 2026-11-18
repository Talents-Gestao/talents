<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use App\Models\CompanyEmployee;
use App\Models\FeedbackEvolutionReport;
use App\Models\FeedbackRecordingReport;
use App\Models\FeedbackSession;
use App\Models\User;
use App\Support\Feedback\Ai\FeedbackReportSchema;
use App\Support\Feedback\FeedbackVisibility;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Evolução de um colaborador, camada 1 (sem IA, determinística): só com as sessões que o usuário pode ver
 * (FeedbackVisibility) e só com dados já estruturados nos relatórios atuais. Linha do tempo com tom e temas por
 * período, pontos de atenção e compromissos de cada sessão, e temas de atenção que se repetem.
 */
class FeedbackEvolutionBuilder
{
    public const MIN_SESSIONS = 2;

    public const TONES = [
        'positivo' => 'Positivo',
        'misto' => 'Misto',
        'atencao' => 'Atenção',
        'neutro' => 'Neutro',
    ];

    /** @return Collection<int, FeedbackSession> */
    public function visibleSessions(CompanyEmployee $employee, User $user): Collection
    {
        return FeedbackVisibility::scopeSessions(
            FeedbackSession::query()->where('company_id', $employee->company_id)->where('company_employee_id', $employee->id),
            $user,
        )
            ->with(['leader:id,name', 'template:id,title'])
            ->orderByRaw('scheduled_at IS NULL, scheduled_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Relatório atual de cada sessão (só de gravações não excluídas), indexado pelo id da sessão.
     *
     * @param  Collection<int, FeedbackSession>  $sessions
     * @return Collection<int, FeedbackRecordingReport>
     */
    public function currentReports(Collection $sessions): Collection
    {
        if ($sessions->isEmpty()) {
            return collect();
        }

        return FeedbackRecordingReport::query()
            ->whereIn('feedback_session_id', $sessions->pluck('id'))
            ->where('is_current', true)
            ->whereHas('recording')
            ->orderByDesc('id')
            ->get()
            ->unique('feedback_session_id')
            ->keyBy('feedback_session_id');
    }

    /** @return array<string, mixed> */
    public function build(CompanyEmployee $employee, User $user): array
    {
        $sessions = $this->visibleSessions($employee, $user);
        $reports = $this->currentReports($sessions);

        $eligible = $sessions->filter(fn (FeedbackSession $s) => $reports->has($s->id))->values();
        $refs = $eligible->mapWithKeys(fn (FeedbackSession $s, int $i) => [$s->id => 'S'.($i + 1)]);

        $timeline = $eligible->map(function (FeedbackSession $s) use ($reports, $refs) {
            $data = $reports[$s->id]->safeReport() ?? [];
            $temas = array_map(fn ($t) => [
                'chave' => $t['chave'],
                'label' => FeedbackReportSchema::THEMES[$t['chave']] ?? $t['chave'],
                'sentimento' => $t['sentimento'],
                'sentimento_label' => FeedbackReportSchema::SENTIMENTS[$t['sentimento']] ?? $t['sentimento'],
            ], $data['temas'] ?? []);
            $tone = self::tone($data['temas'] ?? []);

            return [
                'ref' => $refs[$s->id],
                'session_id' => $s->id,
                'title' => $s->title,
                'scheduled_at' => $s->scheduled_at?->toIso8601String(),
                'period_label' => self::periodLabel($s->scheduled_at),
                'status_label' => $s->status->label(),
                'leader' => $s->leader?->name,
                'template' => $s->template?->title,
                'tone' => $tone,
                'tone_label' => self::TONES[$tone],
                'temas' => $temas,
                'pontos_atencao' => array_values(array_map(fn ($p) => ['texto' => $p['texto'], 'trecho' => $p['trecho'] ?? null], $data['pontos_atencao'] ?? [])),
                'compromissos' => array_values(array_map(fn ($c) => [
                    'id' => $c['id'],
                    'descricao' => $c['descricao'],
                    'responsavel' => $c['responsavel'],
                    'responsavel_label' => FeedbackReportSchema::OWNERS[$c['responsavel']] ?? $c['responsavel'],
                    'prazo' => $c['prazo'] ?? null,
                ], $data['compromissos'] ?? [])),
                'assunto_sensivel_citado' => (bool) ($data['assunto_sensivel_citado'] ?? false),
            ];
        })->values()->all();

        // Temas de atenção que se repetem: mesma chave com sentimento «atenção» ou «misto» em 2 sessões ou mais.
        $recurring = [];
        foreach ($timeline as $item) {
            foreach ($item['temas'] as $t) {
                if (in_array($t['sentimento'], ['atencao', 'misto'], true)) {
                    $recurring[$t['chave']]['label'] = $t['label'];
                    $recurring[$t['chave']]['refs'][] = $item['ref'];
                }
            }
        }
        $recurring = array_values(array_filter(
            array_map(fn ($chave, $r) => ['chave' => $chave, 'label' => $r['label'], 'refs' => array_values(array_unique($r['refs']))], array_keys($recurring), $recurring),
            fn ($r) => count($r['refs']) >= 2,
        ));

        $templates = $eligible->pluck('feedback_template_id')->unique()->count();
        $warnings = [];
        if ($eligible->count() === self::MIN_SESSIONS) {
            $warnings[] = 'Só há dois feedbacks com relatório: compare com cuidado; dois pontos não mostram tendência.';
        }
        if ($templates > 1) {
            $warnings[] = 'Os feedbacks usaram modelos diferentes; as perguntas não são as mesmas entre os períodos.';
        }
        $withoutReport = $sessions->count() - $eligible->count();
        if ($withoutReport > 0 && $eligible->count() > 0) {
            $warnings[] = $withoutReport === 1
                ? '1 feedback desta pessoa ainda não tem relatório e ficou fora da comparação.'
                : "{$withoutReport} feedbacks desta pessoa ainda não têm relatório e ficaram fora da comparação.";
        }

        return [
            'sessions_count' => $sessions->count(),
            'eligible_count' => $eligible->count(),
            'min_sessions' => self::MIN_SESSIONS,
            'ready' => $eligible->count() >= self::MIN_SESSIONS,
            'timeline' => $timeline,
            'recurring_attention' => $recurring,
            'warnings' => $warnings,
            'eligible_session_ids' => $eligible->pluck('id')->all(),
            'eligible_report_ids' => $eligible->map(fn ($s) => $reports[$s->id]->id)->values()->all(),
        ];
    }

    /** Narrativa mais recente que o usuário pode ver (todas as sessões usadas visíveis para ele). */
    public function latestVisibleNarrative(CompanyEmployee $employee, array $visibleSessionIds): ?FeedbackEvolutionReport
    {
        return FeedbackEvolutionReport::query()
            ->where('company_employee_id', $employee->id)
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->first(fn (FeedbackEvolutionReport $r) => array_diff($r->sessionIds(), $visibleSessionIds) === []);
    }

    /** @param list<array{sentimento: string}> $temas */
    public static function tone(array $temas): string
    {
        $sentiments = array_column($temas, 'sentimento');
        $good = in_array('positivo', $sentiments, true);
        $bad = in_array('atencao', $sentiments, true);

        return match (true) {
            in_array('misto', $sentiments, true), $good && $bad => 'misto',
            $bad => 'atencao',
            $good => 'positivo',
            default => 'neutro',
        };
    }

    public static function periodLabel(?Carbon $date): string
    {
        return $date ? $date->copy()->timezone('America/Sao_Paulo')->locale('pt_BR')->translatedFormat('M/Y') : 'Sem data';
    }
}
