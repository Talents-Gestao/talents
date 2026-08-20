<?php

declare(strict_types=1);

use App\Models\TaskBoard;
use App\Models\TaskCard;
use App\Models\User;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$board = TaskBoard::query()
    ->with(['lists' => fn ($q) => $q->orderBy('position')])
    ->orderBy('id')
    ->first();

if (! $board) {
    fwrite(STDERR, "Nenhum quadro encontrado.\n");
    exit(1);
}

$lists = $board->lists->values();
if ($lists->count() < 2) {
    fwrite(STDERR, "Quadro #{$board->id} precisa de pelo menos 2 colunas.\n");
    exit(1);
}

$creatorId = User::query()->where('role', 'super_admin')->value('id')
    ?? User::query()->value('id');

$perList = 25;
$created = 0;
$byList = [];

DB::transaction(function () use ($board, $lists, $creatorId, $perList, &$created, &$byList): void {
    foreach ($lists->take(3) as $index => $list) {
        $position = (float) TaskCard::query()->where('list_id', $list->id)->max('position');
        $count = 0;

        for ($i = 1; $i <= $perList; $i++) {
            $position += 1000;
            TaskCard::query()->create([
                'list_id' => $list->id,
                'company_id' => $board->company_id,
                'title' => sprintf('[TESTE] Coluna %s — card %02d', $list->name, $i),
                'description' => 'Card gerado para teste manual de arrastar entre colunas e scroll.',
                'position' => $position,
                'visibility' => 'internal',
                'completed_at' => null,
                'is_archived' => false,
                'created_by_user_id' => $creatorId,
            ]);
            $created++;
            $count++;
        }

        $byList[] = [
            'list_id' => $list->id,
            'list_name' => $list->name,
            'created' => $count,
            'total' => TaskCard::query()->where('list_id', $list->id)->where('is_archived', false)->count(),
        ];
    }
});

echo json_encode([
    'board_id' => $board->id,
    'board_name' => $board->name,
    'cards_created' => $created,
    'lists' => $byList,
    'hint' => 'Arraste um [TESTE] de uma coluna para o fim de outra e confirme se o scroll permanece.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
