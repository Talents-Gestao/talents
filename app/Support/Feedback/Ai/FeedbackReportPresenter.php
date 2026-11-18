<?php

declare(strict_types=1);

namespace App\Support\Feedback\Ai;

use App\Models\FeedbackRecordingReport;

/**
 * Versão legível do relatório, montada a partir do JSON validado (rótulos da taxonomia, do sentimento e do
 * responsável). Nada é recalculado nem inferido aqui.
 */
final class FeedbackReportPresenter
{
    public const SEAL = 'Gerado por IA — apoio à conversa; revise antes de usar';

    public const SENSITIVE_NOTE = 'Assunto sensível citado (não detalhado no relatório).';

    /** @return array<string, mixed>|null */
    public static function present(?FeedbackRecordingReport $report, int $versions = 1): ?array
    {
        $data = $report?->safeReport();
        if ($report === null || $data === null) {
            return null;
        }

        $report->loadMissing('generatedBy');

        return [
            'id' => $report->id,
            'seal' => self::SEAL,
            'resumo' => $data['resumo'] ?? '',
            'pontos_fortes' => $data['pontos_fortes'] ?? [],
            'pontos_atencao' => $data['pontos_atencao'] ?? [],
            'temas' => array_map(fn ($t) => [
                'chave' => $t['chave'],
                'label' => FeedbackReportSchema::THEMES[$t['chave']] ?? $t['chave'],
                'sentimento' => $t['sentimento'],
                'sentimento_label' => FeedbackReportSchema::SENTIMENTS[$t['sentimento']] ?? $t['sentimento'],
            ], $data['temas'] ?? []),
            'compromissos' => array_map(fn ($c) => [
                'id' => $c['id'],
                'descricao' => $c['descricao'],
                'responsavel' => $c['responsavel'],
                'responsavel_label' => FeedbackReportSchema::OWNERS[$c['responsavel']] ?? $c['responsavel'],
                'prazo' => $c['prazo'],
                'trecho' => $c['trecho'],
            ], $data['compromissos'] ?? []),
            'trechos_chave' => array_map(fn ($t) => [
                'texto' => $t['texto'],
                'falante_label' => FeedbackReportSchema::SPEAKERS[$t['falante']] ?? $t['falante'],
            ], $data['trechos_chave'] ?? []),
            'nao_afirmado' => $data['nao_afirmado'] ?? [],
            'assunto_sensivel_citado' => (bool) ($data['assunto_sensivel_citado'] ?? false),
            'sensitive_note' => self::SENSITIVE_NOTE,
            'meta' => [
                'provider' => $report->provider,
                'provider_label' => FeedbackAiGate::providerLabel($report->provider),
                'model' => $report->model,
                'prompt_version' => $report->prompt_version,
                'schema_version' => $report->schema_version,
                'generated_at' => $report->created_at?->toIso8601String(),
                'generated_by' => $report->generatedBy?->name,
                'clean_revision' => $report->clean_revision,
                'versions' => $versions,
            ],
        ];
    }
}
