<?php

namespace App\Support;

/**
 * Analytics de justificativas RHID (espelha rhidJustificationsAnalytics.js).
 */
final class RhidJustificationAnalytics
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array<string, mixed>>
     */
    public static function buildTypeMapFromPayload(array $payload): array
    {
        $items = self::extractListItems($payload);
        $map = [];
        foreach ($items as $it) {
            if (! is_array($it)) {
                continue;
            }
            $id = $it['id'] ?? $it['Id'] ?? null;
            if ($id === null) {
                continue;
            }
            $map[(string) $id] = $it;
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, mixed>>  $typeMap
     */
    public static function isAtestadoByKeyword(array $row, array $typeMap): bool
    {
        $tid = $row['idJustificationType'] ?? null;
        $label = self::justificationTypeLabel($tid, $typeMap);
        if (preg_match('/atest/i', $label)) {
            return true;
        }
        $j = ($row['justificativa'] ?? '').' '.($row['description'] ?? '');

        return (bool) preg_match('/atest/i', $j);
    }

    /**
     * Considera feriado quando o tipo ou justificativa/descrição contém "feriad" (sem distinguir maiúsculas).
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, mixed>>  $typeMap
     */
    public static function isFeriadoByKeyword(array $row, array $typeMap): bool
    {
        $tid = $row['idJustificationType'] ?? null;
        $label = self::justificationTypeLabel($tid, $typeMap);
        if (preg_match('/feriad/i', $label)) {
            return true;
        }
        $j = ($row['justificativa'] ?? '').' '.($row['description'] ?? '');

        return (bool) preg_match('/feriad/i', $j);
    }

    /**
     * @param  array<string, array<string, mixed>>  $typeMap
     */
    public static function justificationTypeLabel(mixed $id, array $typeMap): string
    {
        if ($id === null || $id === '') {
            return 'Sem tipo';
        }
        $t = $typeMap[(string) $id] ?? null;
        if (! is_array($t)) {
            return '#'.$id;
        }
        foreach (['name', 'nome', 'description', 'descricao'] as $k) {
            if (isset($t[$k]) && trim((string) $t[$k]) !== '') {
                return trim((string) $t[$k]);
            }
        }

        return '#'.$id;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public static function extractListItems(array $payload): array
    {
        if (isset($payload['data']) && is_array($payload['data'])) {
            return array_values(array_filter($payload['data'], 'is_array'));
        }
        if (isset($payload['Data']) && is_array($payload['Data'])) {
            return array_values(array_filter($payload['Data'], 'is_array'));
        }
        if (array_is_list($payload)) {
            return array_values(array_filter($payload, 'is_array'));
        }

        return [];
    }
}
