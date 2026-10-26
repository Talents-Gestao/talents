<?php

declare(strict_types=1);

namespace App\Services\Psychosocial;

use App\Models\PsychosocialDocument;
use App\Models\PsychosocialDocumentVersion;

/**
 * T19: documento oficial não sai com campo vazio ou fictício. A checagem olha o SNAPSHOT CONGELADO da versão
 * (o que o PDF imprime), não o cadastro ao vivo: corrigir o cadastro depois exige gerar uma nova versão.
 */
class DocumentPublicationChecklist
{
    public const WHERE_REGISTRATION = 'Cadastro técnico da campanha';

    /** Valores que parecem preenchidos, mas são marcadores de lugar. */
    private const PLACEHOLDER_EXACT = '/^(n[aã]o informad[oa]|a confirmar|a definir|a informar|a identificar|pendente|preencher|exemplo|teste|tbd|n\/?a|x+|\.+|-+|\?+|0+)$/iu';

    private const PLACEHOLDER_CONTAINS = ['fictício', 'ficticio', 'fictícia', 'ficticia', 'lorem', 'demonstrativ', 'exemplo', 'a confirmar', 'não informado', 'nao informado'];

    /** @var array<string, string> campo do cadastro → rótulo */
    private const REQUIRED_REGISTRATION = [
        'legal_name_snapshot' => 'Razão social',
        'unit_name' => 'Estabelecimento',
        'eligible_source' => 'Fonte do número de elegíveis',
        'technical_responsible_name' => 'Responsável técnico (nome)',
        'technical_responsible_role' => 'Responsável técnico (função)',
        'technical_responsible_registration' => 'Responsável técnico (registro profissional)',
    ];

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<array{field: string, label: string, problem: string, where: string}>
     */
    public function pendingForSnapshot(array $snapshot, string $type): array
    {
        $registration = (array) ($snapshot['registration'] ?? []);
        $pending = [];

        foreach (self::REQUIRED_REGISTRATION as $field => $label) {
            $problem = $this->problemWith($registration[$field] ?? null);
            if ($problem !== null) {
                $pending[] = $this->item($field, $label, $problem);
            }
        }

        $cnpj = $registration['cnpj_snapshot'] ?? null;
        $problem = $this->problemWith($cnpj);
        if ($problem === null && ! self::validCnpj((string) $cnpj)) {
            $problem = 'inválido';
        }
        if ($problem !== null) {
            $pending[] = $this->item('cnpj_snapshot', 'CNPJ', $problem);
        }

        $eligible = (array) ($snapshot['eligible'] ?? []);
        $status = $eligible['status'] ?? 'missing';
        if ($status !== 'ok') {
            $pending[] = $this->item(
                'eligible_count',
                'Total de elegíveis',
                $status === 'inconsistent' ? 'menor que o número de respostas' : 'não informado',
            );
        }

        if (empty($snapshot['has_results']) || (int) ($snapshot['respondents'] ?? 0) < 1) {
            $pending[] = [
                'field' => 'results',
                'label' => 'Resultados da campanha',
                'problem' => 'ainda não calculados',
                'where' => 'Painel da campanha',
            ];
        }

        if ($type === PsychosocialDocument::TYPE_TECHNICAL_REPORT && count((array) ($snapshot['plan'] ?? [])) === 0) {
            $pending[] = [
                'field' => 'plan',
                'label' => 'Plano de ação',
                'problem' => 'nenhuma medida registrada',
                'where' => 'Plano de ação',
            ];
        }

        return $pending;
    }

    /**
     * @param  list<PsychosocialDocumentVersion|null>  $versions
     * @return list<array{field: string, label: string, problem: string, where: string}>
     */
    public function pendingForVersions(array $versions): array
    {
        $pending = [];

        foreach ($versions as $version) {
            if ($version === null) {
                continue;
            }

            $type = $version->document->type;
            foreach ($this->pendingForSnapshot($version->snapshot, $type) as $item) {
                $pending[$item['field']] ??= $item;
            }

            if ($version->pdf_status !== PsychosocialDocumentVersion::PDF_READY) {
                $pending['pdf_'.$type] = [
                    'field' => 'pdf_'.$type,
                    'label' => 'PDF de '.PsychosocialDocumentService::label($type),
                    'problem' => 'não foi gerado',
                    'where' => 'Documentos',
                ];
            }
        }

        return array_values($pending);
    }

    public static function validCnpj(string $value): bool
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) !== 14 || preg_match('/^(\d)\1{13}$/', $digits) === 1) {
            return false;
        }

        foreach ([12, 13] as $length) {
            $sum = 0;
            $weight = $length - 7;
            for ($i = 0; $i < $length; $i++) {
                $sum += (int) $digits[$i] * $weight;
                $weight = $weight === 2 ? 9 : $weight - 1;
            }
            $check = $sum % 11 < 2 ? 0 : 11 - ($sum % 11);
            if ((int) $digits[$length] !== $check) {
                return false;
            }
        }

        return true;
    }

    public static function looksFictitious(string $value): bool
    {
        $text = trim($value);

        if (preg_match(self::PLACEHOLDER_EXACT, $text) === 1) {
            return true;
        }

        $lower = mb_strtolower($text);
        foreach (self::PLACEHOLDER_CONTAINS as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function problemWith(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return 'vazio';
        }

        $text = trim((string) $value);

        if ($text === '') {
            return 'vazio';
        }

        return self::looksFictitious($text) ? 'fictício ou marcador de lugar' : null;
    }

    /**
     * @return array{field: string, label: string, problem: string, where: string}
     */
    private function item(string $field, string $label, string $problem): array
    {
        return ['field' => $field, 'label' => $label, 'problem' => $problem, 'where' => self::WHERE_REGISTRATION];
    }
}
