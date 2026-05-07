<?php

namespace App\Support\Tasks;

use App\Enums\TaskCardVisibility as TaskCardVisibilityEnum;
use App\Enums\TaskListVisibility;
use App\Models\TaskCard;
use App\Models\TaskList;

final class TaskCardVisibility
{
    public static function isVisibleToCompany(TaskCard $card, ?TaskList $list = null): bool
    {
        $list ??= $card->list;
        if (! $list) {
            return false;
        }

        if ($list->visibility !== TaskListVisibility::Company->value) {
            return false;
        }

        return match ($card->visibility) {
            TaskCardVisibilityEnum::Internal->value => false,
            TaskCardVisibilityEnum::Company->value,
            TaskCardVisibilityEnum::Inherit->value => true,
            default => false,
        };
    }

    public static function companyMayMoveBetween(TaskList $from, TaskList $to): bool
    {
        if ($from->visibility !== TaskListVisibility::Company->value) {
            return false;
        }

        if ($to->visibility !== TaskListVisibility::Company->value) {
            return false;
        }

        return (bool) $to->allow_company_drop_in;
    }
}
