<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CommercialCommission;
use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use App\Models\User;
use App\Services\Commercial\ProposalSaleConversionService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Cenários de demonstração para Comercial (fila de propostas) e Financeiro (vendas, parcelas, comissões).
 * Idempotente: usa códigos fixos PROP-DEMO-* e VENDA-DEMO-*.
 */
class CommercialDemoSeeder extends Seeder
{
    public function run(): void
    {
        $karen = User::query()->where('email', 'karen@talents.local')->first();
        $luciana = User::query()->where('email', 'luciana@talents.local')->first();
        $admin = User::query()->where('email', 'admin@talents.local')->first();

        if (! $karen || ! $luciana) {
            $this->command?->warn('CommercialDemoSeeder: execute CommercialSellersSeeder antes (Karen e Luciana).');

            return;
        }

        $this->seedOpenQueue($karen, $luciana, $admin);
        $this->seedClosedWithoutSale($luciana, $admin);
        $this->seedSalesAndCommissions($karen, $luciana, $admin);

        $this->command?->info('CommercialDemoSeeder: cenários comerciais e financeiros prontos.');
        $this->command?->line('  Login: admin@talents.local / password');
        $this->command?->line('  Fila: /admin/comercial/propostas?status=abertas&ordenacao=fila');
        $this->command?->line('  Comissões: /admin/financeiro/comissoes');
    }

    private function seedOpenQueue(User $karen, User $luciana, ?User $admin): void
    {
        $scenarios = [
            [
                'code' => 'PROP-DEMO-0001',
                'client_name' => 'SOEM Indústria (fila #1)',
                'client_cnpj' => '12.345.678/0001-90',
                'client_email' => 'contato@soem-demo.local',
                'client_phone' => '(11) 98765-4321',
                'seller_id' => $karen->id,
                'employee_count' => 85,
                'total_final_cents' => 1_444_000,
                'commission_percent' => 10,
                'commission_cents' => 144_400,
                'created_at' => now()->subDays(14),
                'notes' => 'Demo: proposta mais antiga na fila — prioridade de atendimento.',
                'pdf_subtitle' => 'Consultoria em Gestão de Pessoas e Calendário Estratégico',
                'pdf_objetivo' => 'Apoiar a SOEM na estruturação da gestão de pessoas, com ritos mensais e acompanhamento da liderança.',
            ],
            [
                'code' => 'PROP-DEMO-0002',
                'client_name' => 'Metalúrgica Horizonte (fila #2)',
                'client_cnpj' => '98.765.432/0001-10',
                'client_email' => 'rh@horizonte-demo.local',
                'seller_id' => $luciana->id,
                'employee_count' => 120,
                'total_final_cents' => 2_800_000,
                'commission_percent' => 10,
                'commission_cents' => 280_000,
                'created_at' => now()->subDays(9),
                'notes' => 'Demo: segunda posição na fila (Luciana).',
            ],
            [
                'code' => 'PROP-DEMO-0003',
                'client_name' => 'Cooperativa Sem Vendedor (fila #3)',
                'client_cnpj' => '11.222.333/0001-44',
                'seller_id' => null,
                'employee_count' => 45,
                'total_final_cents' => 1_200_000,
                'commission_percent' => 10,
                'commission_cents' => 120_000,
                'created_at' => now()->subDays(6),
                'notes' => 'Demo: em aberto sem vendedor atribuído.',
            ],
            [
                'code' => 'PROP-DEMO-0004',
                'client_name' => 'TechNova Soluções (fila #4)',
                'client_email' => 'diretoria@technova-demo.local',
                'seller_id' => $karen->id,
                'employee_count' => 200,
                'total_final_cents' => 6_750_000,
                'commission_percent' => 10,
                'commission_cents' => 675_000,
                'created_at' => now()->subDay(),
                'notes' => 'Demo: proposta mais recente na fila.',
            ],
        ];

        foreach ($scenarios as $data) {
            $this->upsertOpenProposal($data, $admin);
        }
    }

    private function seedClosedWithoutSale(User $luciana, ?User $admin): void
    {
        $this->upsertClosedProposal([
            'code' => 'PROP-DEMO-0005',
            'client_name' => 'Viva Saúde — pronta para converter',
            'client_cnpj' => '55.666.777/0001-88',
            'client_email' => 'financeiro@vivsaude-demo.local',
            'client_phone' => '(21) 99876-5432',
            'seller_id' => $luciana->id,
            'employee_count' => 60,
            'total_final_cents' => 3_300_000,
            'commission_percent' => 10,
            'commission_cents' => 330_000,
            'closed_at' => now()->subDays(3),
            'created_at' => now()->subDays(20),
            'notes' => 'Demo: fechada/ganha, ainda sem venda — use «Converter em venda» na listagem.',
        ], $admin);
    }

    private function seedSalesAndCommissions(User $karen, User $luciana, ?User $admin): void
    {
        $conversion = app(ProposalSaleConversionService::class);

        // Venda quitada + comissão paga
        $p6 = $this->upsertClosedProposal([
            'code' => 'PROP-DEMO-0006',
            'client_name' => 'Grupo Atlas — venda quitada',
            'seller_id' => $karen->id,
            'employee_count' => 90,
            'total_final_cents' => 1_000_000,
            'commission_percent' => 10,
            'commission_cents' => 100_000,
            'closed_at' => now()->subDays(45),
            'created_at' => now()->subDays(50),
            'notes' => 'Demo: todas as parcelas pagas; comissão já repassada.',
        ], $admin);

        $s6 = $this->ensureSale($conversion, $p6, [
            'payment_method' => 'pix',
            'installments_count' => 3,
            'first_due_date' => now()->subDays(40)->toDateString(),
        ], $admin, 'VENDA-DEMO-0001');

        $this->payAllInstallments($s6, now()->subDays(35));
        $s6->recalculateStatus();
        $this->setCommission($s6, CommercialCommission::STATUS_PAGA, now()->subDays(30), 'Repasse PIX — demo quitada');
        $this->touchCommissionCreatedAt($s6, now()->subDays(45));

        // Venda parcial + comissão a pagar
        $p7 = $this->upsertClosedProposal([
            'code' => 'PROP-DEMO-0007',
            'client_name' => 'Logística Sul — venda parcial',
            'seller_id' => $luciana->id,
            'employee_count' => 150,
            'total_final_cents' => 2_400_000,
            'commission_percent' => 10,
            'commission_cents' => 240_000,
            'closed_at' => now()->subDays(25),
            'created_at' => now()->subDays(30),
            'notes' => 'Demo: 1 de 3 parcelas pagas; comissão pendente.',
        ], $admin);

        $s7 = $this->ensureSale($conversion, $p7, [
            'payment_method' => 'boleto',
            'installments_count' => 3,
            'first_due_date' => now()->subDays(20)->toDateString(),
        ], $admin, 'VENDA-DEMO-0002');

        $this->payInstallmentNumber($s7, 1, now()->subDays(18));
        $s7->recalculateStatus();
        $this->touchCommissionCreatedAt($s7, now()->subDays(25));

        // Venda aberta com parcela vencida
        $p8 = $this->upsertClosedProposal([
            'code' => 'PROP-DEMO-0008',
            'client_name' => 'Construtora Alpha — parcela vencida',
            'seller_id' => $karen->id,
            'employee_count' => 75,
            'total_final_cents' => 1_800_000,
            'commission_percent' => 10,
            'commission_cents' => 180_000,
            'closed_at' => now()->subDays(35),
            'created_at' => now()->subDays(40),
            'notes' => 'Demo: 1ª parcela vencida; comissão pendente (mais antiga na fila de comissões).',
        ], $admin);

        $s8 = $this->ensureSale($conversion, $p8, [
            'payment_method' => 'pix',
            'installments_count' => 2,
            'first_due_date' => now()->subDays(20)->toDateString(),
        ], $admin, 'VENDA-DEMO-0003');

        $s8->installments()->orderBy('number')->get()->each(function (CommercialSaleInstallment $inst, int $index): void {
            if ($index === 1) {
                $inst->update(['due_date' => now()->addDays(15)]);
            }
        });
        $this->touchCommissionCreatedAt($s8, now()->subDays(38));

        // Venda quitada, comissão ainda a pagar
        $p9 = $this->upsertClosedProposal([
            'code' => 'PROP-DEMO-0009',
            'client_name' => 'Educação Mais — quitada, comissão aberta',
            'seller_id' => $luciana->id,
            'employee_count' => 40,
            'total_final_cents' => 950_000,
            'commission_percent' => 10,
            'commission_cents' => 95_000,
            'closed_at' => now()->subDays(15),
            'created_at' => now()->subDays(18),
            'notes' => 'Demo: cliente quitou, repasse ao vendedor ainda não feito.',
        ], $admin);

        $s9 = $this->ensureSale($conversion, $p9, [
            'payment_method' => 'cartao',
            'installments_count' => 1,
            'first_due_date' => now()->subDays(10)->toDateString(),
        ], $admin, 'VENDA-DEMO-0004');

        $this->payAllInstallments($s9, now()->subDays(8));
        $s9->recalculateStatus();
        $this->touchCommissionCreatedAt($s9, now()->subDays(15));

        // Venda aberta — parcelas futuras
        $p10 = $this->upsertClosedProposal([
            'code' => 'PROP-DEMO-0010',
            'client_name' => 'Farmácia Central — cobranças futuras',
            'seller_id' => $karen->id,
            'employee_count' => 30,
            'total_final_cents' => 1_500_000,
            'commission_percent' => 10,
            'commission_cents' => 150_000,
            'closed_at' => now()->subDays(5),
            'created_at' => now()->subDays(8),
            'notes' => 'Demo: 4 parcelas pendentes, vencimentos futuros.',
        ], $admin);

        $this->ensureSale($conversion, $p10, [
            'payment_method' => 'pix',
            'installments_count' => 4,
            'first_due_date' => now()->addDays(7)->toDateString(),
        ], $admin, 'VENDA-DEMO-0005');

        $sale10 = CommercialSale::query()->where('code', 'VENDA-DEMO-0005')->first();
        if ($sale10) {
            $this->touchCommissionCreatedAt($sale10, now()->subDays(5));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upsertOpenProposal(array $data, ?User $admin): CommercialProposal
    {
        $createdAt = $data['created_at'] ?? now();
        unset($data['created_at']);

        $proposal = CommercialProposal::query()->updateOrCreate(
            ['code' => $data['code']],
            array_merge($this->proposalDefaults($admin), $data, [
                'is_closed' => false,
                'closed_at' => null,
            ]),
        );

        $this->touchTimestamps($proposal, $createdAt);

        return $proposal;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upsertClosedProposal(array $data, ?User $admin): CommercialProposal
    {
        $createdAt = $data['created_at'] ?? now();
        $closedAt = $data['closed_at'] ?? now();
        unset($data['created_at'], $data['closed_at']);

        $proposal = CommercialProposal::query()->updateOrCreate(
            ['code' => $data['code']],
            array_merge($this->proposalDefaults($admin), $data, [
                'is_closed' => true,
                'closed_at' => $closedAt,
            ]),
        );

        $this->touchTimestamps($proposal, $createdAt);

        return $proposal;
    }

    /**
     * @return array<string, mixed>
     */
    private function proposalDefaults(?User $admin): array
    {
        return [
            'created_by' => $admin?->id,
            'svc_pesquisas' => false,
            'svc_profiler' => false,
            'svc_devolutiva' => null,
            'svc_nr1' => false,
            'svc_nr1_implantacao_modo' => null,
            'svc_contratacao' => false,
            'svc_direcionamento' => false,
            'svc_palestras' => false,
            'total_pesquisas_cents' => 0,
            'total_profiler_cents' => 0,
            'total_devolutiva_cents' => 0,
            'total_nr1_cents' => 0,
            'total_nr1_implantacao_cents' => 0,
            'total_contratacao_cents' => 0,
            'total_direcionamento_cents' => 0,
            'total_palestras_cents' => 0,
            'total_catalog_products_cents' => 0,
        ];
    }

    private function touchTimestamps(CommercialProposal $proposal, Carbon $createdAt): void
    {
        $proposal->created_at = $createdAt;
        $proposal->updated_at = now();
        $proposal->saveQuietly();
    }

    /**
     * @param  array{payment_method: string, installments_count: int, first_due_date: string}  $convertData
     */
    private function ensureSale(
        ProposalSaleConversionService $conversion,
        CommercialProposal $proposal,
        array $convertData,
        ?User $admin,
        string $saleCode,
    ): CommercialSale {
        $existing = $proposal->sale;
        if ($existing) {
            if ($existing->code !== $saleCode) {
                $existing->update(['code' => $saleCode]);
            }

            return $existing->fresh(['installments', 'commission']);
        }

        $sale = $conversion->convert($proposal, $convertData, $admin?->id);
        $sale->update(['code' => $saleCode]);

        return $sale->fresh(['installments', 'commission']);
    }

    private function payAllInstallments(CommercialSale $sale, Carbon $paidAt): void
    {
        foreach ($sale->installments as $installment) {
            $this->markInstallmentPaid($installment, $paidAt);
        }
    }

    private function payInstallmentNumber(CommercialSale $sale, int $number, Carbon $paidAt): void
    {
        $installment = $sale->installments()->where('number', $number)->first();
        if ($installment) {
            $this->markInstallmentPaid($installment, $paidAt);
        }
    }

    private function markInstallmentPaid(CommercialSaleInstallment $installment, Carbon $paidAt): void
    {
        $installment->update([
            'status' => CommercialSaleInstallment::STATUS_PAGO,
            'paid_at' => $paidAt,
            'paid_amount_cents' => $installment->amount_cents,
        ]);
    }

    private function setCommission(
        CommercialSale $sale,
        string $status,
        ?Carbon $paidAt = null,
        ?string $notes = null,
    ): void {
        $commission = $sale->commission;
        if (! $commission) {
            return;
        }

        $commission->update([
            'status' => $status,
            'paid_at' => $status === CommercialCommission::STATUS_PAGA ? ($paidAt ?? now()) : null,
            'notes' => $notes ?? $commission->notes,
        ]);

        if ($status === CommercialCommission::STATUS_A_PAGAR) {
            $commission->update(['paid_at' => null]);
        }
    }

    private function touchCommissionCreatedAt(CommercialSale $sale, Carbon $createdAt): void
    {
        $commission = $sale->commission;
        if (! $commission) {
            return;
        }

        $commission->created_at = $createdAt;
        $commission->updated_at = now();
        $commission->saveQuietly();
    }
}
