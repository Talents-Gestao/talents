<?php

declare(strict_types=1);

use App\Models\TaskBoard;
use App\Models\TaskCard;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$board = TaskBoard::query()->with(['lists' => fn ($q) => $q->orderBy('position')])->first();

if (! $board) {
    fwrite(STDERR, "Nenhum quadro encontrado.\n");
    exit(1);
}

$creatorId = User::query()->where('role', 'super_admin')->value('id')
    ?? User::query()->value('id');

$lists = $board->lists;
if ($lists->isEmpty()) {
    fwrite(STDERR, "Quadro #{$board->id} sem listas.\n");
    exit(1);
}

/** Prefer a "Concluído"/"Done" list for bulk completed cards; otherwise last list. */
$doneList = $lists->first(function (TaskList $list): bool {
    $name = mb_strtolower($list->name);

    return str_contains($name, 'conclu')
        || str_contains($name, 'done')
        || str_contains($name, 'finaliz');
}) ?? $lists->last();

$otherLists = $lists->where('id', '!=', $doneList->id)->values();

$titles = [
    'TAREFAS',
    'CALENDÁRIO ESTRATÉGICO',
    'CLIENTES',
    'FEEDBACKS INTERNOS',
    'PESQUISA DE DESLIGAMENTO',
    'RHID / PONTO',
    'COMERCIAL',
    'FINANCEIRO',
    'REUNIÕES',
    'NOTIFICADORES',
    'CAPACITAÇÃO',
    'METODOLOGIA',
    'ACOMPANHAMENTO',
    'FÉRIAS',
    'DENÚNCIAS',
    'ONBOARDING',
    'RELATÓRIOS NR-1',
    'UNIFORMES',
    'REGULAMENTO INTERNO',
    'DESTAQUES DO MÊS',
];

$created = 0;

DB::transaction(function () use ($doneList, $otherLists, $titles, $creatorId, &$created): void {
    $maxPos = (float) TaskCard::query()->where('list_id', $doneList->id)->max('position');
    $position = $maxPos > 0 ? $maxPos : 0;

    // Many completed cards in the done column (scroll test).
    for ($i = 1; $i <= 40; $i++) {
        $position += 1000;
        $base = $titles[($i - 1) % count($titles)];
        TaskCard::query()->create([
            'list_id' => $doneList->id,
            'title' => sprintf('%s — teste scroll %02d', $base, $i),
            'description' => 'Cartão gerado para testar scroll da coluna Kanban.',
            'position' => $position,
            'visibility' => 'internal',
            'completed_at' => now()->subHours($i),
            'is_archived' => false,
            'created_by_user_id' => $creatorId,
        ]);
        $created++;
    }

    // A few cards in other lists so the board looks alive.
    foreach ($otherLists->take(3) as $list) {
        $listPos = (float) TaskCard::query()->where('list_id', $list->id)->max('position');
        for ($i = 1; $i <= 5; $i++) {
            $listPos += 1000;
            TaskCard::query()->create([
                'list_id' => $list->id,
                'title' => sprintf('Em curso — %s #%d', $list->name, $i),
                'description' => 'Cartão de apoio para testes do Kanban.',
                'position' => $listPos,
                'visibility' => 'internal',
                'completed_at' => null,
                'is_archived' => false,
                'created_by_user_id' => $creatorId,
            ]);
            $created++;
        }
    }
});

echo json_encode([
    'board_id' => $board->id,
    'board_name' => $board->name,
    'done_list_id' => $doneList->id,
    'done_list_name' => $doneList->name,
    'cards_created' => $created,
    'done_list_total' => TaskCard::query()->where('list_id', $doneList->id)->where('is_archived', false)->count(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
