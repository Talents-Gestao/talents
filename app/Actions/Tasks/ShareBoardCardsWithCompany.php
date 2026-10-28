<?php

declare(strict_types=1);

namespace App\Actions\Tasks;

use App\Models\TaskBoard;
use App\Models\TaskCard;
use Illuminate\Support\Facades\DB;

final class ShareBoardCardsWithCompany
{
    /**
     * Vincula ao portal da empresa os cartões do quadro ainda sem company_id.
     *
     * @return array{updated: int, skipped: int}
     */
    public function handle(TaskBoard $board, int $companyId, bool $onlyWithoutCompany = true): array
    {
        $listIds = $board->lists()->pluck('id');
        if ($listIds->isEmpty()) {
            return ['updated' => 0, 'skipped' => 0];
        }

        $query = TaskCard::query()
            ->whereIn('list_id', $listIds)
            ->where('is_archived', false);

        if ($onlyWithoutCompany) {
            $query->whereNull('company_id');
        } else {
            $query->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')
                    ->orWhere('company_id', $companyId);
            });
        }

        $cards = $query->get(['id', 'company_id', 'visibility']);
        if ($cards->isEmpty()) {
            $totalOnBoard = TaskCard::query()
                ->whereIn('list_id', $listIds)
                ->where('is_archived', false)
                ->count();

            return ['updated' => 0, 'skipped' => $totalOnBoard];
        }

        $updated = 0;

        DB::transaction(function () use ($cards, $companyId, &$updated) {
            foreach ($cards as $card) {
                $payload = ['company_id' => $companyId];
                if ($card->visibility === 'internal') {
                    $payload['visibility'] = 'company';
                }
                $card->update($payload);
                $updated++;
            }
        });

        $totalOnBoard = TaskCard::query()
            ->whereIn('list_id', $listIds)
            ->where('is_archived', false)
            ->count();

        return [
            'updated' => $updated,
            'skipped' => max(0, $totalOnBoard - $updated),
        ];
    }
}
