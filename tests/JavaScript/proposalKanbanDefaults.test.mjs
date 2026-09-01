import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const proposalsIndex = readFileSync(
    join(root, 'resources/js/Pages/Admin/Commercial/Proposals/Index.vue'),
    'utf8',
);
const kanbanCard = readFileSync(
    join(root, 'resources/js/Components/Commercial/ProposalKanbanCard.vue'),
    'utf8',
);
const kanbanBoard = readFileSync(
    join(root, 'resources/js/Components/Commercial/ProposalsKanbanBoard.vue'),
    'utf8',
);

describe('Propostas — Kanban como visão padrão', () => {
    it('Index.vue assume view kanban e envia view=list só na lista', () => {
        assert.match(proposalsIndex, /view:\s*\{\s*type:\s*String,\s*default:\s*'kanban'\s*\}/);
        assert.match(proposalsIndex, /params\.view = 'list'/);
        assert.doesNotMatch(
            proposalsIndex,
            /if \(isKanbanView\.value\) \{\s*params\.view = 'kanban'/,
        );
    });
});

describe('ProposalKanbanCard — clique no card abre o menu', () => {
    it('o article dispara o menu no clique e o botão dos três pontinhos continua a existir', () => {
        assert.match(kanbanCard, /@click="onCardClick"/);
        assert.match(kanbanCard, /const onCardClick = async \(event\) =>/);
        assert.match(kanbanCard, /await openMenu\(ellipsisButtonEl\.value\)/);
        assert.match(kanbanCard, /aria-label="Mais ações"/);
        assert.match(kanbanCard, /<EllipsisHorizontalIcon\b/);
    });

    it('não trata arrasto como clique de menu', () => {
        assert.match(kanbanCard, /@pointerdown="onCardPointerDown"/);
        assert.match(kanbanCard, /if \(dragMoved\.value\)/);
        assert.match(kanbanBoard, /:distance="8"/);
    });

    it('não fecha o menu só por mover o rato depois do clique', () => {
        const pointerMove = kanbanCard.match(
            /const onCardPointerMove = \(event\) => \{[\s\S]*?\n\};/,
        );
        assert.ok(pointerMove, 'onCardPointerMove não encontrado');
        assert.match(pointerMove[0], /event\.buttons !== 1/);
        assert.doesNotMatch(pointerMove[0], /closeMenu\(/);
    });

    it('claimProposalKanbanMenu garante um único menu aberto', () => {
        assert.match(kanbanCard, /claimProposalKanbanMenu\(props\.proposal\.id\)/);
        assert.match(kanbanCard, /watch\(activeProposalKanbanMenuId/);
        assert.match(
            kanbanCard,
            /if \(activeId !== props\.proposal\.id && menuOpen\.value\)/,
        );
    });
});
