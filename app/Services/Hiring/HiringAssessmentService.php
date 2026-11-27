<?php

declare(strict_types=1);

namespace App\Services\Hiring;

use App\Enums\HiringAssessmentDecision;
use App\Enums\HiringAssessmentRating;
use App\Models\HiringApplication;
use App\Models\HiringAssessment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Avaliação da candidatura (uma por hiring_application). Decisão humana; a IA não decide.
 * Alterar o conteúdo de uma avaliação aprovada desfaz a aprovação (o dossiê precisa ser aprovado de novo).
 */
final class HiringAssessmentService
{
    private const CONTENT_FIELDS = [
        'success_profile',
        'case_description',
        'case_result',
        'criteria',
        'interviewer_summary',
        'limitations',
        'decision',
    ];

    public function forApplication(HiringApplication $application): HiringAssessment
    {
        return $application->assessment ?? new HiringAssessment([
            'hiring_application_id' => $application->id,
            'decision' => HiringAssessmentDecision::Pending,
            'criteria' => [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(HiringApplication $application, array $data, ?int $userId): HiringAssessment
    {
        return DB::transaction(function () use ($application, $data, $userId): HiringAssessment {
            $assessment = HiringAssessment::query()
                ->where('hiring_application_id', $application->id)
                ->lockForUpdate()
                ->first() ?? new HiringAssessment([
                    'hiring_application_id' => $application->id,
                    'created_by' => $userId,
                ]);

            $assessment->fill([
                'success_profile' => $this->clean($data['success_profile'] ?? null),
                'case_description' => $this->clean($data['case_description'] ?? null),
                'case_result' => $this->clean($data['case_result'] ?? null),
                'criteria' => $this->normalizeCriteria($data['criteria'] ?? []),
                'interviewer_summary' => $this->clean($data['interviewer_summary'] ?? null),
                'limitations' => $this->clean($data['limitations'] ?? null),
                'decision' => HiringAssessmentDecision::from((string) ($data['decision'] ?? HiringAssessmentDecision::Pending->value)),
                'updated_by' => $userId,
            ]);

            if ($assessment->exists && $assessment->isApproved() && $assessment->isDirty(self::CONTENT_FIELDS)) {
                $assessment->approved_at = null;
                $assessment->approved_by = null;
            }

            $assessment->save();

            return $assessment;
        });
    }

    /** Aprovar o dossiê: exige avaliação salva, síntese da entrevistadora e decisão humana registrada. */
    public function approve(HiringApplication $application, ?int $userId): HiringAssessment
    {
        $assessment = $application->assessment;
        if ($assessment === null) {
            throw ValidationException::withMessages(['approve' => 'Salve a avaliação antes de aprovar o dossiê.']);
        }
        if ($assessment->decision === HiringAssessmentDecision::Pending) {
            throw ValidationException::withMessages(['approve' => 'Registre a decisão humana antes de aprovar o dossiê.']);
        }
        if (trim((string) $assessment->interviewer_summary) === '') {
            throw ValidationException::withMessages(['approve' => 'Preencha a síntese da entrevistadora antes de aprovar o dossiê.']);
        }

        $assessment->approved_at = now();
        $assessment->approved_by = $userId;
        $assessment->save();

        return $assessment;
    }

    /**
     * @return list<array{criterio: string, evidencia: string|null, origem: string|null, avaliacao: string}>
     */
    private function normalizeCriteria(mixed $criteria): array
    {
        if (! is_array($criteria)) {
            return [];
        }

        $out = [];
        foreach ($criteria as $row) {
            if (! is_array($row)) {
                continue;
            }
            $criterio = $this->clean($row['criterio'] ?? null);
            if ($criterio === null) {
                continue;
            }
            $rating = HiringAssessmentRating::tryFrom((string) ($row['avaliacao'] ?? '')) ?? HiringAssessmentRating::SemEvidencia;
            $evidence = $this->clean($row['evidencia'] ?? null);
            // Sem evidência registrada, o critério não pode ser dado como atendido.
            if ($evidence === null && $rating !== HiringAssessmentRating::SemEvidencia) {
                throw ValidationException::withMessages([
                    'criteria' => 'Informe a evidência do critério «'.$criterio.'» ou marque «Sem evidência».',
                ]);
            }

            $out[] = [
                'criterio' => $criterio,
                'evidencia' => $evidence,
                'origem' => $this->clean($row['origem'] ?? null),
                'avaliacao' => $rating->value,
            ];
        }

        return $out;
    }

    private function clean(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
