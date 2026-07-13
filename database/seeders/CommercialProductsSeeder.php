<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CommercialProductPricingType;
use App\Models\CommercialProduct;
use App\Models\CommercialSetting;
use App\Support\CommercialProposalPdfDefaults;
use Illuminate\Database\Seeder;

/**
 * Catálogo de produtos comerciais para testes manuais (propostas, PDF, observações).
 * Idempotente: usa slug fixo em cada produto.
 */
class CommercialProductsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = CommercialProposalPdfDefaults::defaultServiceDescriptions();

        $products = [
            [
                'slug' => 'palestras',
                'name' => 'Palestras e Treinamentos',
                'description' => $defaults['palestras'] ?? null,
                'pricing_type' => CommercialProductPricingType::ThresholdMultiplier,
                'pricing_config' => [
                    'base_cents' => 157_700,
                    'threshold_employees' => 30,
                    'multiplier' => 2,
                ],
                'sort_order' => 10,
            ],
            [
                'slug' => 'pesquisas',
                'name' => 'Pesquisas e Organograma',
                'description' => $defaults['pesquisas'] ?? null,
                'pricing_type' => CommercialProductPricingType::TieredPerEmployee,
                'pricing_config' => [
                    'tier1_max' => 10,
                    'tier1_cents' => 12_700,
                    'tier2_max' => 20,
                    'tier2_cents' => 12_000,
                    'tier3_max' => 30,
                    'tier3_cents' => 11_450,
                    'tier4_cents' => 10_700,
                ],
                'sort_order' => 20,
            ],
            [
                'slug' => 'profiler',
                'name' => 'Profiler — Diagnóstico Comportamental',
                'description' => $defaults['profiler'] ?? null,
                'pricing_type' => CommercialProductPricingType::TieredPerEmployee,
                'pricing_config' => [
                    'tier1_max' => 5,
                    'tier1_cents' => 32_700,
                    'tier2_max' => 10,
                    'tier2_cents' => 30_500,
                    'tier3_max' => 20,
                    'tier3_cents' => 28_500,
                    'tier4_cents' => 26_500,
                ],
                'sort_order' => 30,
            ],
            [
                'slug' => 'mapeamento-comportamental',
                'name' => 'Mapeamento Comportamental',
                'description' => "Diagnóstico comportamental simplificado para equipes.\n\nObjetivo: visão rápida do perfil da equipe para apoiar decisões de alocação.",
                'pricing_type' => CommercialProductPricingType::Fixed,
                'pricing_config' => ['amount_cents' => 0],
                'sort_order' => 35,
            ],
            [
                'slug' => 'devolutiva',
                'name' => 'Devolutiva e Diagnóstico',
                'description' => $defaults['devolutiva'] ?? null,
                'pricing_type' => CommercialProductPricingType::FixedModality,
                'pricing_config' => [
                    'modalities' => [
                        ['key' => 'individual', 'label' => 'Individual', 'cents' => 157_700],
                        ['key' => 'grupo', 'label' => 'Em grupo', 'cents' => 210_700],
                    ],
                ],
                'sort_order' => 40,
            ],
            [
                'slug' => 'nr1',
                'name' => 'NR-1 — Mapeamento de Riscos Psicossociais',
                'description' => $defaults['nr1'] ?? null,
                'pricing_type' => CommercialProductPricingType::PerEmployee,
                'pricing_config' => ['cents_per_employee' => 1_700],
                'sort_order' => 50,
            ],
            [
                'slug' => 'contratacao',
                'name' => 'Contratação / Recrutamento',
                'description' => $defaults['contratacao'] ?? null,
                'pricing_type' => CommercialProductPricingType::SalaryTimesEmployees,
                'pricing_config' => [],
                'sort_order' => 60,
            ],
            [
                'slug' => 'direcionamento',
                'name' => 'Direcionamento Estratégico',
                'description' => $defaults['direcionamento'] ?? null,
                'pricing_type' => CommercialProductPricingType::FlexibleRates,
                'pricing_config' => [
                    'rates' => [
                        'hour' => ['enabled' => true, 'cents_per_unit' => 54_700],
                        'quantity' => ['enabled' => true, 'cents_per_unit' => 35_000],
                        'unit' => ['enabled' => false, 'cents_per_unit' => 0],
                    ],
                ],
                'sort_order' => 70,
            ],
            [
                'slug' => 'metamorfose-pessoal',
                'name' => 'Metamorfose Pessoal™',
                'description' => "Programa de desenvolvimento individual com acompanhamento da liderança.\n\nObjetivo: acelerar a maturidade comportamental e a performance do colaborador.",
                'pricing_type' => CommercialProductPricingType::Fixed,
                'pricing_config' => ['amount_cents' => 89_900],
                'sort_order' => 80,
            ],
            [
                'slug' => 'voz-do-time',
                'name' => 'Voz do Time',
                'description' => "Pesquisa de clima e engajamento com canal de escuta ativo.\n\nObjetivo: medir percepções da equipe e apoiar planos de ação de RH.",
                'pricing_type' => CommercialProductPricingType::PerEmployee,
                'pricing_config' => ['cents_per_employee' => 4_500],
                'sort_order' => 90,
            ],
            [
                'slug' => 'imersao-perfil-grupo',
                'name' => 'Imersão de Perfil — Grupo',
                'description' => "Workshop presencial de perfil comportamental para turmas.\n\nObjetivo: alinhar a equipe sobre perfis, comunicação e papéis.",
                'pricing_type' => CommercialProductPricingType::FixedModality,
                'pricing_config' => [
                    'modalities' => [
                        ['key' => 'online', 'label' => 'On-line', 'cents' => 120_000],
                        ['key' => 'presencial', 'label' => 'Presencial', 'cents' => 185_000],
                    ],
                ],
                'sort_order' => 100,
            ],
            [
                'slug' => 'diagnostico-empresarial',
                'name' => 'Diagnóstico Empresarial',
                'description' => "Raio-X da gestão de pessoas, processos e indicadores de RH.\n\nObjetivo: subsidiar decisões estratégicas com dados consolidados.",
                'pricing_type' => CommercialProductPricingType::Fixed,
                'pricing_config' => ['amount_cents' => 245_000],
                'sort_order' => 110,
            ],
        ];

        foreach ($products as $payload) {
            CommercialProduct::query()->updateOrCreate(
                ['slug' => $payload['slug']],
                [
                    'name' => $payload['name'],
                    'description' => $payload['description'],
                    'pricing_type' => $payload['pricing_type'],
                    'pricing_config' => $payload['pricing_config'],
                    'sort_order' => $payload['sort_order'],
                    'is_active' => true,
                ],
            );
        }

        $this->syncPdfDescriptions($defaults);

        $count = CommercialProduct::query()->where('is_active', true)->count();

        $this->command?->info("CommercialProductsSeeder: {$count} produto(s) ativo(s) no catálogo.");
        $this->command?->line('  Produtos: /admin/comercial/settings?tab=produtos');
        $this->command?->line('  Nova proposta: /admin/comercial/propostas/create');
    }

    /**
     * @param  array<string, string>  $defaults
     */
    private function syncPdfDescriptions(array $defaults): void
    {
        $settings = CommercialSetting::current();
        $stored = $settings->pdf_descricoes_servicos ?? [];

        foreach ($defaults as $slug => $text) {
            if (! array_key_exists($slug, $stored) || $stored[$slug] === '' || $stored[$slug] === null) {
                $stored[$slug] = $text;
            }
        }

        CommercialProduct::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->each(function (CommercialProduct $product) use (&$stored): void {
                if (
                    filled($product->description)
                    && (! array_key_exists($product->slug, $stored) || blank($stored[$product->slug] ?? null))
                ) {
                    $stored[$product->slug] = $product->description;
                }
            });

        $settings->update(['pdf_descricoes_servicos' => $stored]);
    }
}
