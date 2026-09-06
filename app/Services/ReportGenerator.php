<?php

namespace App\Services;

use App\Models\ActionPlan;
use App\Models\Survey;
use App\Models\SurveyResult;
use App\Support\Nr1RiskScenarioResolver;
use App\Support\TalentsLogoDataUri;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class ReportGenerator
{
    /**
     * @return array<string, mixed>
     */
    private function baseViewData(Survey $survey): array
    {
        $resolved = Nr1RiskScenarioResolver::resolve($survey);
        $scenario = $resolved['scenario'] ?? 'green';
        $scenarioConfig = Nr1RiskScenarioResolver::scenarioConfig($scenario);

        return [
            'survey' => $survey,
            'scenario' => $scenario,
            'scenarioConfig' => $scenarioConfig,
            'riskLevelLabel' => fn (?string $l) => match ($l) {
                'green' => config('nr1.risk_labels.green'),
                'yellow' => config('nr1.risk_labels.yellow'),
                'red' => config('nr1.risk_labels.red'),
                default => strtoupper((string) $l),
            },
            'healthLevelLabel' => fn (?string $l) => match ($l) {
                'green' => 'Situação favorável',
                'yellow' => 'Risco intermediário',
                'red' => 'Risco elevado',
                default => strtoupper((string) $l),
            },
            'riskColor' => fn (?string $l) => match ($l) {
                'green' => '#10b981',
                'yellow' => '#f59e0b',
                'red' => '#ef4444',
                default => '#64748b',
            },
            'riskBg' => fn (?string $l) => match ($l) {
                'green' => '#d1fae5',
                'yellow' => '#fef3c7',
                'red' => '#fee2e2',
                default => '#f1f5f9',
            },
        ];
    }

    private function ensureDompdfWritableDirs(): void
    {
        foreach ([storage_path('fonts'), storage_path('app/dompdf-tmp')] as $dir) {
            File::ensureDirectoryExists($dir);
        }
    }

    private function applyDompdfOptions(\Barryvdh\DomPDF\PDF $pdf): \Barryvdh\DomPDF\PDF
    {
        $this->ensureDompdfWritableDirs();

        $fontDir = storage_path('fonts');
        $tempDir = storage_path('app/dompdf-tmp');
        $chroot = realpath(base_path()) ?: base_path();

        $pdf->setOption('fontDir', $fontDir);
        $pdf->setOption('fontCache', $fontDir);
        $pdf->setOption('tempDir', $tempDir);
        $pdf->setOption('chroot', $chroot);

        return $pdf;
    }

    public function executivePdf(Survey $survey): \Barryvdh\DomPDF\PDF
    {
        $survey->load([
            'company',
            'template.sections',
            'results' => fn ($q) => $q->orderBy('id'),
            'insights',
        ]);

        return $this->applyDompdfOptions(
            Pdf::loadView('reports.executive', $this->baseViewData($survey))->setPaper('a4')
        );
    }

    public function technicalPdf(Survey $survey): \Barryvdh\DomPDF\PDF
    {
        $survey->load([
            'company',
            'template.sections.questions',
            'results' => fn ($q) => $q->orderBy('id'),
            'insights',
            'responses',
        ]);

        return $this->applyDompdfOptions(
            Pdf::loadView('reports.technical', $this->baseViewData($survey))->setPaper('a4')
        );
    }

    public function technicalReferralPdf(Survey $survey): \Barryvdh\DomPDF\PDF
    {
        $survey->load([
            'company',
            'results' => fn ($q) => $q->orderBy('id'),
            'insights',
        ]);

        return $this->applyDompdfOptions(
            Pdf::loadView('reports.referral', $this->baseViewData($survey))->setPaper('a4')
        );
    }

    public function actionPlanPdf(Survey $survey, bool $includeActions = true): \Barryvdh\DomPDF\PDF
    {
        $survey->load([
            'company',
            'results' => fn ($q) => $q->orderBy('id'),
        ]);

        $plan = ActionPlan::query()
            ->where('survey_id', $survey->id)
            ->where('company_id', $survey->company_id)
            ->with('items')
            ->first();

        $presented = SurveyResultsPresenter::forSurvey($survey);

        /** @var Collection<int, SurveyResult> $bySectionModels */
        $bySectionModels = $presented['bySection'];
        $bySection = $bySectionModels->map(function (SurveyResult $r) {
            $meta = is_array($r->meta) ? $r->meta : [];

            return [
                'survey_template_section_id' => $r->survey_template_section_id,
                'average_score' => (float) $r->average_score,
                'risk_level' => $r->risk_level,
                'respondent_count' => $r->respondent_count,
                'section_title' => $meta['section_title'] ?? ($r->section?->title ?? 'Dimensão'),
            ];
        })->values()->all();

        $overallModel = $presented['overall'];
        $overall = $overallModel instanceof SurveyResult
            ? [
                'average_score' => (float) $overallModel->average_score,
                'risk_level' => $overallModel->risk_level,
                'respondent_count' => $overallModel->respondent_count,
            ]
            : null;

        $deptOveralls = collect($presented['deptOveralls'])->values()->all();
        $deptSectionsByDepartment = collect($presented['deptSectionsByDepartment'])->values()->all();

        $data = $this->baseViewData($survey);
        $data['logoBase64'] = TalentsLogoDataUri::get();
        $data['plan'] = $plan;
        $data['items'] = $plan?->items ?? collect();
        $data['includeActions'] = $includeActions;
        $data['technicalOpinion'] = $plan?->technical_opinion;
        $data['overall'] = $overall;
        $data['bySection'] = $bySection;
        $data['deptOveralls'] = $deptOveralls;
        $data['deptSectionsByDepartment'] = $deptSectionsByDepartment;
        $data['departmentParticipation'] = $presented['departmentParticipation'] ?? [];
        $data['questionDistributions'] = $presented['questionDistributions'] ?? [];
        $data['insights'] = $presented['insights'] ?? collect();
        $data['radarSvg'] = $this->buildRadarSvg($bySection, $data['riskColor']);
        $data['heatmapCell'] = function (array $deptSectionsByDepartment, int $departmentId, int $sectionId): ?array {
            foreach ($deptSectionsByDepartment as $group) {
                if ((int) ($group['department_id'] ?? 0) !== $departmentId) {
                    continue;
                }
                foreach ($group['sections'] ?? [] as $section) {
                    if ((int) ($section['survey_template_section_id'] ?? 0) === $sectionId) {
                        return [
                            'average_score' => (float) $section['average_score'],
                            'risk_level' => $section['risk_level'] ?? null,
                        ];
                    }
                }
            }

            return null;
        };
        $data['likertLabel'] = function (string $scale, int $value): string {
            if ($scale === 'agreement') {
                return match ($value) {
                    1 => 'Discordo totalmente',
                    2 => 'Discordo',
                    3 => 'Neutro',
                    4 => 'Concordo',
                    5 => 'Concordo totalmente',
                    default => (string) $value,
                };
            }

            return match ($value) {
                1 => 'Nunca',
                2 => 'Raramente',
                3 => 'Às vezes',
                4 => 'Frequentemente',
                5 => 'Sempre',
                default => (string) $value,
            };
        };

        return $this->applyDompdfOptions(
            Pdf::loadView('reports.action_plan', $data)->setPaper('a4')
        );
    }

    /**
     * @param  list<array{average_score: float, risk_level: ?string, section_title: string}>  $bySection
     * @param  callable(?string): string  $riskColor
     */
    private function buildRadarSvg(array $bySection, callable $riskColor): ?string
    {
        $n = count($bySection);
        if ($n < 3) {
            return null;
        }

        $size = 320;
        $cx = $size / 2;
        $cy = $size / 2;
        $maxR = 110;
        $labelR = 128;

        $grid = '';
        for ($tick = 1; $tick <= 5; $tick++) {
            $r = (($tick - 1) / 4) * $maxR;
            if ($r <= 0) {
                continue;
            }
            $points = [];
            for ($i = 0; $i < $n; $i++) {
                $angle = -M_PI_2 + (2 * M_PI * $i / $n);
                $points[] = sprintf('%.2f,%.2f', $cx + $r * cos($angle), $cy + $r * sin($angle));
            }
            $grid .= '<polygon points="'.implode(' ', $points).'" fill="none" stroke="#e2e8f0" stroke-width="1"/>';
        }

        $axes = '';
        $labels = '';
        $dataPoints = [];
        $markers = '';

        for ($i = 0; $i < $n; $i++) {
            $row = $bySection[$i];
            $angle = -M_PI_2 + (2 * M_PI * $i / $n);
            $score = max(1.0, min(5.0, (float) $row['average_score']));
            $r = (($score - 1) / 4) * $maxR;

            $x = $cx + $r * cos($angle);
            $y = $cy + $r * sin($angle);
            $dataPoints[] = sprintf('%.2f,%.2f', $x, $y);

            $ax = $cx + $maxR * cos($angle);
            $ay = $cy + $maxR * sin($angle);
            $axes .= sprintf(
                '<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="#e2e8f0" stroke-width="1"/>',
                $cx,
                $cy,
                $ax,
                $ay
            );

            $lx = $cx + $labelR * cos($angle);
            $ly = $cy + $labelR * sin($angle);
            $title = (string) ($row['section_title'] ?? 'Dimensão');
            if (mb_strlen($title) > 18) {
                $title = mb_substr($title, 0, 16).'…';
            }
            $anchor = abs(cos($angle)) < 0.2 ? 'middle' : (cos($angle) > 0 ? 'start' : 'end');
            $labels .= sprintf(
                '<text x="%.2f" y="%.2f" text-anchor="%s" font-size="9" font-weight="600" fill="#334155">%s</text>',
                $lx,
                $ly + 3,
                $anchor,
                htmlspecialchars($title, ENT_QUOTES | ENT_XML1, 'UTF-8')
            );

            $color = $riskColor($row['risk_level'] ?? null);
            $markers .= sprintf(
                '<circle cx="%.2f" cy="%.2f" r="5" fill="%s" stroke="#ffffff" stroke-width="1.5"/>',
                $x,
                $y,
                $color
            );
        }

        $polygon = '<polygon points="'.implode(' ', $dataPoints).'" fill="#cbd5e1" fill-opacity="0.25" stroke="#94a3b8" stroke-width="2"/>';

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 '.$size.' '.$size.'">'
            .$grid.$axes.$polygon.$markers.$labels
            .'</svg>';
    }
}
