<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Notices\PublishCommercialNotice;
use App\Enums\CompanyNoticeEventKind;
use App\Models\CommercialSaleInstallment;
use App\Models\CompanyNotice;
use Illuminate\Console\Command;

class NotifyOverdueInstallments extends Command
{
    protected $signature = 'commercial:notify-overdue-installments';

    protected $description = 'Cria avisos internos para parcelas de vendas vencidas (uma vez por parcela).';

    public function handle(PublishCommercialNotice $notices): int
    {
        $overdue = CommercialSaleInstallment::query()
            ->where('status', CommercialSaleInstallment::STATUS_PENDENTE)
            ->whereDate('due_date', '<', now()->toDateString())
            ->with('sale')
            ->get();

        $created = 0;

        foreach ($overdue as $installment) {
            if ($this->alreadyNotified((int) $installment->id)) {
                continue;
            }

            $notices->installmentOverdue($installment);
            $created++;
        }

        $this->info("Parcelas vencidas notificadas: {$created} (verificadas: {$overdue->count()}).");

        return self::SUCCESS;
    }

    private function alreadyNotified(int $installmentId): bool
    {
        return CompanyNotice::query()
            ->where('source_type', 'commercial_sale_installment')
            ->where('source_id', $installmentId)
            ->where('event_kind', CompanyNoticeEventKind::InstallmentOverdue->value)
            ->exists();
    }
}
