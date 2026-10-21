<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Conferência do backfill da fase 1 (só leitura): instrumento atribuído por template,
 * estado de auditoria de polaridade e distribuição de campanhas por método.
 */
class Nr1MethodVersionsReportCommand extends Command
{
    protected $signature = 'nr1:method-versions-report';

    protected $description = 'Lista instrumento e método atribuídos a templates e campanhas NR-1 (somente leitura)';

    public function handle(): int
    {
        $this->info('=== TEMPLATES E VERSÕES DE INSTRUMENTO ===');

        $templateRows = DB::table('survey_templates as t')
            ->leftJoin('survey_instrument_versions as v', 'v.survey_template_id', '=', 't.id')
            ->orderBy('t.id')
            ->orderBy('v.id')
            ->get([
                't.id as template_id',
                't.title',
                'v.id as version_id',
                'v.instrument_code',
                'v.version_label',
                'v.source',
                'v.polarity_audit_status',
                'v.item_matrix_sha256',
            ]);

        $campaignsByVersion = DB::table('surveys')
            ->select('instrument_version_id', DB::raw('count(*) as total'))
            ->groupBy('instrument_version_id')
            ->pluck('total', 'instrument_version_id');

        $this->table(
            ['Template', 'Título', 'Versão', 'Instrumento', 'Origem', 'Polaridade', 'Hash (12)', 'Campanhas'],
            $templateRows->map(fn (object $row): array => [
                $row->template_id,
                $row->title,
                $row->version_label ?? '—',
                $row->instrument_code ?? '—',
                $row->source ?? '—',
                $row->polarity_audit_status ?? '—',
                $row->item_matrix_sha256 !== null ? substr((string) $row->item_matrix_sha256, 0, 12) : '—',
                $row->version_id !== null ? (int) ($campaignsByVersion[$row->version_id] ?? 0) : 0,
            ])->all()
        );

        $this->info('=== CAMPANHAS POR MÉTODO (inclui soft delete) ===');

        $byMethod = DB::table('surveys as s')
            ->leftJoin('survey_method_versions as m', 'm.id', '=', 's.method_version_id')
            ->select(DB::raw("coalesce(m.code, '(sem método)') as code"), DB::raw('count(*) as total'))
            ->groupBy('m.code')
            ->get();

        $this->table(['Método', 'Campanhas'], $byMethod->map(fn (object $row): array => [$row->code, $row->total])->all());

        $this->info('=== RESULTADOS SEM MÉTODO REGISTRADO ===');
        $this->line((string) DB::table('survey_results')->whereNull('method_version_id')->count());

        $this->info('=== CAMPANHAS SEM VERSÃO DE INSTRUMENTO ===');
        $this->line((string) DB::table('surveys')->whereNull('instrument_version_id')->count());

        return self::SUCCESS;
    }
}
