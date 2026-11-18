<?php

declare(strict_types=1);

namespace App\Support\Feedback\Ai;

use App\Models\FeedbackEvolutionReport;
use App\Models\FeedbackSession;
use App\Services\Feedback\FeedbackEvolutionBuilder;

/**
 * Versão legível da narrativa de Evolução: cada fonte («S1») vira a sessão de origem com o período, para a tela
 * mostrar a evidência e o link. As referências seguem a ordem das sessões usadas naquela geração.
 */
final class FeedbackEvolutionPresenter
{
    public const SEAL = 'Gerado por IA — apoio à conversa; revise antes de usar';

    /**
     * @param  list<int>  $currentEligibleIds  sessões que entrariam numa geração agora (para avisar se ficou desatualizada)
     * @return array<string, mixed>|null
     */
    public static function present(?FeedbackEvolutionReport $evolution, array $currentEligibleIds): ?array
    {
        if ($evolution === null) {
            return null;
        }

        $evolution->loadMissing('generatedBy:id,name');
        $sessions = FeedbackSession::query()
            ->whereIn('id', $evolution->sessionIds())
            ->orderByRaw('scheduled_at IS NULL, scheduled_at')
            ->orderBy('id')
            ->get(['id', 'title', 'scheduled_at']);
        $sources = [];
        foreach ($sessions->values() as $i => $s) {
            $sources['S'.($i + 1)] = [
                'ref' => 'S'.($i + 1),
                'session_id' => $s->id,
                'label' => 'S'.($i + 1).' · '.FeedbackEvolutionBuilder::periodLabel($s->scheduled_at),
                'title' => $s->title,
            ];
        }
        $map = fn (array $fontes) => array_values(array_filter(array_map(fn ($f) => $sources[$f] ?? null, $fontes)));

        $data = $evolution->status === FeedbackEvolutionReport::STATUS_READY ? $evolution->safeReport() : null;
        $ids = $evolution->sessionIds();
        sort($ids);
        $current = $currentEligibleIds;
        sort($current);

        return [
            'id' => $evolution->id,
            'status' => $evolution->status,
            'error' => $evolution->status === FeedbackEvolutionReport::STATUS_FAILED ? $evolution->error : null,
            'outdated' => $ids !== $current,
            'sources' => array_values($sources),
            'seal' => self::SEAL,
            'report' => $data === null ? null : [
                'resumo' => $data['resumo'] ?? '',
                'mudancas' => array_map(fn ($m) => ['texto' => $m['texto'], 'fontes' => $map($m['fontes'])], $data['mudancas'] ?? []),
                'temas_recorrentes' => array_map(fn ($t) => [
                    'chave' => $t['chave'],
                    'label' => FeedbackReportSchema::THEMES[$t['chave']] ?? $t['chave'],
                    'fontes' => $map($t['fontes']),
                ], $data['temas_recorrentes'] ?? []),
                'compromissos_retomados' => array_map(fn ($c) => ['texto' => $c['texto'], 'fontes' => $map($c['fontes'])], $data['compromissos_retomados'] ?? []),
                'nao_afirmado' => $data['nao_afirmado'] ?? [],
                'assunto_sensivel_citado' => (bool) ($data['assunto_sensivel_citado'] ?? false),
                'sensitive_note' => FeedbackReportPresenter::SENSITIVE_NOTE,
            ],
            'meta' => [
                'provider_label' => FeedbackAiGate::providerLabel($evolution->provider),
                'model' => $evolution->model,
                'prompt_version' => $evolution->prompt_version,
                'schema_version' => $evolution->schema_version,
                'generated_at' => $evolution->created_at?->toIso8601String(),
                'generated_by' => $evolution->generatedBy?->name,
            ],
        ];
    }
}
