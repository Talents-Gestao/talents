<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tasks\ShareBoardCardsWithCompany;
use App\Models\Company;
use App\Models\TaskBoard;
use Illuminate\Console\Command;

class ShareTaskBoardCardsCommand extends Command
{
    protected $signature = 'tasks:share-board-cards
        {board : ID ou nome do quadro}
        {company : ID ou nome da empresa}
        {--dry-run : Apenas mostra quantos cartões seriam atualizados}';

    protected $description = 'Partilha com o portal da empresa os cartões de um quadro interno ainda sem company_id';

    public function handle(ShareBoardCardsWithCompany $share): int
    {
        $boardArg = (string) $this->argument('board');
        $companyArg = (string) $this->argument('company');

        $board = ctype_digit($boardArg)
            ? TaskBoard::query()->find((int) $boardArg)
            : TaskBoard::query()->where('name', 'ilike', $boardArg)->first();

        if (! $board) {
            $this->error('Quadro não encontrado.');

            return self::FAILURE;
        }

        $company = ctype_digit($companyArg)
            ? Company::query()->find((int) $companyArg)
            : Company::query()->where('name', 'ilike', '%'.$companyArg.'%')->orderBy('id')->first();

        if (! $company) {
            $this->error('Empresa não encontrada.');

            return self::FAILURE;
        }

        $this->info("Quadro: #{$board->id} {$board->name}");
        $this->info("Empresa: #{$company->id} {$company->name}");

        if ($this->option('dry-run')) {
            $pending = $board->lists()
                ->withCount(['cards as pending_cards_count' => function ($q) {
                    $q->where('is_archived', false)->whereNull('company_id');
                }])
                ->get()
                ->sum('pending_cards_count');
            $this->warn("Dry-run: {$pending} cartão(ões) seriam partilhados.");

            return self::SUCCESS;
        }

        $result = $share->handle($board, (int) $company->id, onlyWithoutCompany: true);
        $this->info("Atualizados: {$result['updated']} | Mantidos: {$result['skipped']}");

        return self::SUCCESS;
    }
}
