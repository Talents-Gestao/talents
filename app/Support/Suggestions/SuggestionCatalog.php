<?php

declare(strict_types=1);

namespace App\Support\Suggestions;

/**
 * Opções do canal público, alinhadas ao formulário de referência
 * (sugestão, dúvida, área e pedido de retorno).
 */
final class SuggestionCatalog
{
    public const PREFER_NOT_TO_SAY = 'prefiro_nao_informar';

    /**
     * @return array<string, string>
     */
    public static function topics(): array
    {
        return [
            'sugestao_melhoria' => 'Sugestão de melhoria',
            'duvida' => 'Dúvida',
            'ideia' => 'Ideia',
            'feedback' => 'Feedback',
            'relato_situacao' => 'Relato de situação no trabalho',
            'outro' => 'Outro',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function areas(): array
    {
        return [
            'rh' => 'RH',
            'dp' => 'DP',
            'fiscal' => 'Fiscal',
            'financeiro' => 'Financeiro',
            'contabil' => 'Contábil',
            'sucesso_cliente' => 'Sucesso do Cliente',
            'atendimento' => 'Atendimento',
            'marketing' => 'Marketing',
            'comercial' => 'Comercial',
            'desenvolvimento' => 'Desenvolvimento',
            'legalizacao' => 'Legalização',
            'servicos_gerais' => 'Serviços Gerais',
            self::PREFER_NOT_TO_SAY => 'PREFIRO NÃO INFORMAR',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function responsePreferences(): array
    {
        return [
            'sim' => 'Sim',
            'nao' => 'Não',
            'apenas_sugestao' => 'Não preciso, é apenas uma sugestão',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            'new' => 'Nova',
            'in_review' => 'Em análise',
            'answered' => 'Respondida',
            'archived' => 'Arquivada',
        ];
    }

    public static function label(array $map, ?string $key, string $fallback = '—'): string
    {
        if ($key === null || $key === '') {
            return $fallback;
        }

        return $map[$key] ?? $key;
    }
}
