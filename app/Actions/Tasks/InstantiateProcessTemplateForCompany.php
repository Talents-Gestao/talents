<?php

namespace App\Actions\Tasks;

use App\Models\Company;
use App\Models\TaskBoard;
use App\Models\TaskCard;
use App\Models\TaskList;
use App\Models\TaskProcessTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class InstantiateProcessTemplateForCompany
{
    public function __construct(
        private LogTaskActivity $logTaskActivity,
    ) {}

    public function handle(
        TaskProcessTemplate $template,
        Company $company,
        User $actor,
        ?string $boardName = null,
    ): TaskBoard {
        return DB::transaction(function () use ($template, $company, $actor, $boardName) {
            $template->load(['lists.cards']);

            $board = TaskBoard::query()->create([
                'company_id' => $company->id,
                'process_template_id' => $template->id,
                'name' => $boardName ?: $template->name,
                'description' => $template->description,
                'cover_color' => $template->cover_color,
                'is_archived' => false,
                'created_by_user_id' => $actor->id,
            ]);

            foreach ($template->lists as $tList) {
                $list = TaskList::query()->create([
                    'board_id' => $board->id,
                    'name' => $tList->name,
                    'position' => $tList->position,
                    'visibility' => $tList->default_visibility,
                    'allow_company_drop_in' => $tList->allow_company_drop_in,
                    'is_archived' => false,
                ]);

                foreach ($tList->cards as $tCard) {
                    $due = null;
                    if ($tCard->default_due_offset_days !== null) {
                        $due = now()->addDays($tCard->default_due_offset_days)->toDateString();
                    }

                    TaskCard::query()->create([
                        'list_id' => $list->id,
                        'title' => $tCard->title,
                        'description' => $tCard->description,
                        'position' => $tCard->position,
                        'visibility' => $tCard->default_visibility,
                        'start_date' => null,
                        'due_date' => $due,
                        'completed_at' => null,
                        'is_archived' => false,
                        'created_by_user_id' => $actor->id,
                    ]);
                }
            }

            $this->logTaskActivity->handle($board, null, 'board.instantiated_from_template', $actor, [
                'template_id' => $template->id,
                'company_id' => $company->id,
            ]);

            return $board->load(['lists.cards']);
        });
    }
}
