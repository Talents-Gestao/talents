<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Matriz congelada de itens de um template NR-1 (ordem, seção, polaridade, peso e escala).
 *
 * Trabalha só com o query builder para servir tanto à migration de backfill
 * quanto ao congelamento no início da campanha, sem depender de models.
 */
final class Nr1InstrumentMatrix
{
    public const CODE_COPSOQ_TALENTS = 'copsoq_talents';

    public const CODE_HSE_IT = 'hse_it';

    public const CODE_TALENTS39 = 'talents39';

    public const CODE_CUSTOM = 'custom';

    public const AUDIT_NOT_FLAGGED = 'not_flagged';

    public const AUDIT_PENDING = 'pending_audit';

    public const AUDIT_ACCEPTED = 'accepted';

    public const SOURCE_BACKFILL = 'backfill';

    public const SOURCE_CAMPAIGN_START = 'campaign_start';

    /** Títulos semeados em TalentsSeeder; a identificação é por igualdade exata. */
    private const SEEDED_TITLES = [
        'NR-1 / COPSOQ (modelo Talents)' => self::CODE_COPSOQ_TALENTS,
        'HSE-IT / Management Standards (NR-1)' => self::CODE_HSE_IT,
    ];

    public static function instrumentCodeForTitle(string $title): string
    {
        return self::SEEDED_TITLES[$title] ?? self::CODE_CUSTOM;
    }

    /**
     * A polaridade semeada do HSE-IT segue a convenção «maior = melhor», oposta a Nr1Scoring;
     * até a auditoria ser aceita, o instrumento fica marcado como pendente.
     */
    public static function initialAuditStatus(string $instrumentCode): string
    {
        return $instrumentCode === self::CODE_HSE_IT ? self::AUDIT_PENDING : self::AUDIT_NOT_FLAGGED;
    }

    /**
     * @return list<array{item_number: int, question_id: int, section_id: int, section_title: string, body: string, reverse_score: bool, weight: string, response_scale: string}>
     */
    public static function forTemplate(int $templateId): array
    {
        $rows = DB::table('survey_template_questions as q')
            ->join('survey_template_sections as s', 's.id', '=', 'q.survey_template_section_id')
            ->where('s.survey_template_id', $templateId)
            ->orderBy('s.sort_order')
            ->orderBy('s.id')
            ->orderBy('q.sort_order')
            ->orderBy('q.id')
            ->get([
                'q.id as question_id',
                's.id as section_id',
                's.title as section_title',
                'q.body',
                'q.reverse_score',
                'q.weight',
                'q.response_scale',
            ]);

        $matrix = [];
        foreach ($rows->values() as $index => $row) {
            $matrix[] = [
                'item_number' => $index + 1,
                'question_id' => (int) $row->question_id,
                'section_id' => (int) $row->section_id,
                'section_title' => (string) $row->section_title,
                'body' => (string) $row->body,
                'reverse_score' => (bool) $row->reverse_score,
                'weight' => sprintf('%.2f', (float) ($row->weight ?? 1)),
                'response_scale' => (string) ($row->response_scale ?? 'frequency'),
            ];
        }

        return $matrix;
    }

    /**
     * @param  list<array<string, mixed>>  $matrix
     */
    public static function encode(array $matrix): string
    {
        return json_encode($matrix, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @param  list<array<string, mixed>>  $matrix
     */
    public static function hash(array $matrix): string
    {
        return hash('sha256', self::encode($matrix));
    }
}
