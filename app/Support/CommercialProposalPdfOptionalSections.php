<?php

namespace App\Support;

use App\Models\CommercialProposal;
use App\Models\CommercialSetting;

class CommercialProposalPdfOptionalSections
{
    public const KEY_METAMORFOSE_PESSOAL = 'metamorfose_pessoal';

    public const KEY_ANALISE_SALARIAL = 'analise_salarial';

    public const KEY_ESTRUTURA_ORGANIZACIONAL = 'estrutura_organizacional';

    public const KEY_TREINAMENTOS = 'treinamentos';

    public const KEY_PLATAFORMA_MODULOS = 'plataforma_modulos';

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return [
            self::KEY_METAMORFOSE_PESSOAL,
            self::KEY_ANALISE_SALARIAL,
            self::KEY_ESTRUTURA_ORGANIZACIONAL,
            self::KEY_TREINAMENTOS,
            self::KEY_PLATAFORMA_MODULOS,
        ];
    }

    /**
     * Opções para o formulário da proposta.
     *
     * @return array<int, array{key: string, label: string, hint: string}>
     */
    public static function options(): array
    {
        return [
            [
                'key' => self::KEY_METAMORFOSE_PESSOAL,
                'label' => 'Metamorfose Pessoal™',
                'hint' => 'Desenvolvimento individual para líderes e colaboradores.',
            ],
            [
                'key' => self::KEY_ANALISE_SALARIAL,
                'label' => 'Análise Salarial',
                'hint' => 'Pesquisa salarial regional e posicionamento de mercado.',
            ],
            [
                'key' => self::KEY_ESTRUTURA_ORGANIZACIONAL,
                'label' => 'Estrutura Organizacional',
                'hint' => 'Organograma, estruturação de RH e Contrato de Expectativa.',
            ],
            [
                'key' => self::KEY_TREINAMENTOS,
                'label' => 'Treinamentos e Desenvolvimento',
                'hint' => 'Liderança, comunicação, NR-1 e outros temas.',
            ],
            [
                'key' => self::KEY_PLATAFORMA_MODULOS,
                'label' => 'Plataforma — módulos extras',
                'hint' => 'NR-1, Canal de Denúncias e RH Analytics (Control-ID).',
            ],
        ];
    }

    /**
     * Textos padrão exibidos no PDF (referência modelo SOEM).
     *
     * @return array<string, string>
     */
    public static function defaultTexts(): array
    {
        return [
            self::KEY_METAMORFOSE_PESSOAL => <<<'TXT'
Desenvolvimento individual para líderes e colaboradores.

Pode incluir:
• Mapeamento Comportamental
• Análise de Perfil Individual
• Relatório com Direcionamento Estratégico

Conforme proposta específica.
TXT,
            self::KEY_ANALISE_SALARIAL => <<<'TXT'
• Pesquisa Salarial Regional
• Posicionamento de Mercado
• Recomendações Estratégicas

Conforme proposta específica.
TXT,
            self::KEY_ESTRUTURA_ORGANIZACIONAL => <<<'TXT'
• Organograma
• Estruturação de RH
• Contrato de Expectativa

Conforme proposta específica.
TXT,
            self::KEY_TREINAMENTOS => <<<'TXT'
• Liderança
• Comunicação
• Trabalho em Equipe
• Comportamento
• Cultura Organizacional
• NR-1
• Outros temas de acordo com a necessidade da empresa

Conforme proposta específica.
TXT,
            self::KEY_PLATAFORMA_MODULOS => <<<'TXT'
Outros módulos da nossa plataforma:
• NR-1 — Riscos Psicossociais
• Canal de Denúncias
• RH Analytics (Control-ID)

A contratação dos módulos poderá ser realizada individualmente e integrada à consultoria estratégica, conforme a necessidade da empresa.
TXT,
        ];
    }

    /**
     * @param  array<string, bool>|null  $selected
     * @return array<string, bool>
     */
    public static function normalizeSelection(?array $selected): array
    {
        $normalized = [];
        foreach (self::keys() as $key) {
            $normalized[$key] = ! empty($selected[$key]);
        }

        return $normalized;
    }

    /**
     * @param  array<string, string|null>|null  $storedOverrides
     * @return array<string, string>
     */
    public static function textsForSettings(?array $storedOverrides): array
    {
        return array_merge(self::defaultTexts(), array_filter($storedOverrides ?? [], fn ($v) => filled($v)));
    }

    /**
     * Seções habilitadas para render no PDF.
     *
     * @return array<int, array{key: string, label: string, text: string}>
     */
    public static function forProposal(CommercialProposal $proposal, ?CommercialSetting $settings = null): array
    {
        $settings = $settings ?? CommercialSetting::current();
        $selection = self::normalizeSelection($proposal->pdf_optional_sections);
        $texts = self::textsForSettings($settings->pdf_secoes_opcionais ?? null);
        $labels = collect(self::options())->keyBy('key');

        $sections = [];
        foreach (self::keys() as $key) {
            if (! $selection[$key]) {
                continue;
            }

            $sections[] = [
                'key' => $key,
                'label' => $labels[$key]['label'] ?? $key,
                'text' => $texts[$key] ?? '',
            ];
        }

        return $sections;
    }
}
