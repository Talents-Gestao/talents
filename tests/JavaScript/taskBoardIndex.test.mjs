import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import {
    boardSections,
    chunkIds,
    completionPercent,
    descriptionPlainText,
    filterBoards,
    groupBoards,
    isBoardExpanded,
    kanbanNeedsFixedHeight,
    normalizeSearch,
} from '../../resources/js/utils/taskBoards.js';

/**
 * Tarefas: tela única (Admin e portal). Lógica pura do seletor e verificação estática da estrutura.
 */
const root = new URL('../../', import.meta.url);
const read = (path) => readFileSync(new URL(path, root), 'utf8');

const boards = [
    { id: 1, name: 'Operação', is_internal: true, is_starred: false, description: '<p>Rotina <strong>diária</strong></p>' },
    { id: 2, name: 'Admissões', is_internal: false, is_starred: true, company: { name: 'Padaria São João' } },
    { id: 3, name: 'Férias', is_internal: true, is_starred: true },
];

describe('Tarefas: seletor de quadros (lógica pura)', () => {
    it('busca ignora maiúsculas e acentos e procura em nome, empresa e descrição', () => {
        assert.equal(normalizeSearch('  FÉRIAS '), 'ferias');
        assert.deepEqual(filterBoards(boards, 'ferias').map((b) => b.id), [3]);
        assert.deepEqual(filterBoards(boards, 'sao joao').map((b) => b.id), [2]);
        assert.deepEqual(filterBoards(boards, 'diaria').map((b) => b.id), [1]);
        assert.equal(filterBoards(boards, '').length, 3);
        assert.deepEqual(filterBoards(null, 'x'), []);
    });

    it('favoritos primeiro, e cada quadro continua no seu grupo (interno / por empresa) na ordem do servidor', () => {
        const groups = groupBoards(boards);
        assert.deepEqual(groups.favorites.map((b) => b.id), [2, 3]);
        assert.deepEqual(groups.internal.map((b) => b.id), [1, 3]);
        assert.deepEqual(groups.company.map((b) => b.id), [2]);
    });

    it('descrição em HTML vira texto; vazio quando não há descrição', () => {
        assert.equal(descriptionPlainText('<p>Linha 1</p><p>Linha&nbsp;2 &amp; 3</p>'), 'Linha 1\nLinha 2 & 3');
        assert.equal(descriptionPlainText('Texto<br>simples'), 'Texto\nsimples');
        assert.equal(descriptionPlainText('<p></p>'), '');
        assert.equal(descriptionPlainText(null), '');
    });
});

describe('Tarefas: quadros empilhados (lógica pura)', () => {
    it('seções: favoritos primeiro e cada quadro aparece uma só vez; a busca vale para todas', () => {
        const sections = boardSections(boards);
        assert.deepEqual(sections.favorites.map((b) => b.id), [2, 3]);
        assert.deepEqual(sections.internal.map((b) => b.id), [1]);
        assert.deepEqual(sections.company.map((b) => b.id), []);
        assert.deepEqual(boardSections(boards, 'operacao').internal.map((b) => b.id), [1]);
        assert.deepEqual(boardSections(boards, 'operacao').favorites, []);
    });

    it('expandido: escolha guardada vale; sem escolha, favoritos e o último usado abrem', () => {
        assert.equal(isBoardExpanded(boards[0], {}, null), false);
        assert.equal(isBoardExpanded(boards[0], {}, 1), true);
        assert.equal(isBoardExpanded(boards[1], {}, null), true);
        assert.equal(isBoardExpanded(boards[1], { 2: false }, null), false);
        assert.equal(isBoardExpanded(boards[0], { 1: true }, null), true);
        assert.equal(isBoardExpanded(boards[0], null, '1'), true);
    });

    it('progresso, lotes de ids e altura fixa do Kanban', () => {
        assert.equal(completionPercent(0, 0), 0);
        assert.equal(completionPercent(3, 1), 33);
        assert.equal(completionPercent(2, 5), 100);
        assert.deepEqual(chunkIds([1, 2, 2, '3', 4, 'x'], 2), [[1, 2], [3, 4]]);
        assert.deepEqual(chunkIds(null), []);
        assert.equal(kanbanNeedsFixedHeight({ lists: [{ cards: [1, 2, 3, 4] }] }), false);
        assert.equal(kanbanNeedsFixedHeight({ lists: [{ cards: [] }, { cards: [1, 2, 3, 4, 5] }] }), true);
        assert.equal(kanbanNeedsFixedHeight(null), false);
    });
});

describe('Tarefas: estrutura da tela única', () => {
    it('não existem mais a tela de detalhe separada, a prévia da listagem nem o seletor de quadro único', () => {
        for (const path of [
            'resources/js/Pages/Admin/Tasks/Boards/Show.vue',
            'resources/js/Pages/Client/Tasks/Show.vue',
            'resources/js/Components/Tasks/BoardIndexPreview.vue',
            'resources/js/Components/Tasks/BoardSwitcher.vue',
            'resources/js/Components/Tasks/BoardSwitcherItem.vue',
        ]) {
            assert.equal(existsSync(new URL(path, root)), false, path);
        }
    });

    it('Admin e portal usam a mesma tela, cada um com a sua rota show', () => {
        const admin = read('resources/js/Pages/Admin/Tasks/Boards/Index.vue');
        const client = read('resources/js/Pages/Client/Tasks/Index.vue');
        assert.match(admin, /<TaskBoardScreen[\s\S]*show-route-name="admin\.tarefas\.quadros\.show"/);
        assert.match(admin, /:can-edit="canAdmin\('tarefas', 'edit'\)"/);
        assert.match(client, /<TaskBoardScreen[\s\S]*:is-admin="false"[\s\S]*show-route-name="client\.tarefas\.show"/);
        for (const src of [admin, client]) {
            assert.doesNotMatch(src, /FormPageHeader|>\s*Abrir\s*</);
        }
    });

    it('todos os quadros empilhados: seções por grupo, conteúdo pedido só ao expandir, em lotes', () => {
        const screen = read('resources/js/Components/Tasks/TaskBoardScreen.vue');
        assert.match(screen, /<BoardSection\s[\s\S]*v-for="board in group\.boards"/);
        assert.match(screen, /boardSections\(props\.boards, search\.value\)/);
        assert.match(screen, /const BOARDS_HEADER = 'X-Task-Boards'/);
        assert.match(screen, /router\.reload\(\{\s*only: withBoards \? \['boardPayloads', 'boards'\] : \['boardPayloads'\]/);
        assert.match(screen, /chunkIds\(ids, BATCH_SIZE\)/);
        assert.match(screen, /localStorage\.setItem\(STORAGE_KEY/);
        assert.match(screen, /auth\?\.user\?\.id/);
        assert.doesNotMatch(screen, /BoardSwitcher/);

        const section = read('resources/js/Components/Tasks/BoardSection.vue');
        assert.match(section, /new IntersectionObserver/);
        assert.match(section, /props\.expanded && !props\.payload && !props\.loading && !props\.failed && nearViewport\.value/);
        assert.match(section, /data-board-skeleton/);
        assert.match(section, /<BoardSummary :board="board" \/>/);
        assert.match(section, /Tentar de novo/);
    });

    it('rota /{board}: expande e rola até o quadro; os atalhos do topo também abrem o quadro', () => {
        const screen = read('resources/js/Components/Tasks/TaskBoardScreen.vue');
        assert.match(screen, /if \(props\.scrollToFocus && props\.focusBoardId\)/);
        assert.match(screen, /scrollIntoView/);
        const nav = read('resources/js/Components/Tasks/TaskBoardsNav.vue');
        for (const text of ['Buscar quadro', 'Expandir todos', 'Recolher todos', 'Novo quadro interno', 'Ativar processo para empresa']) {
            assert.ok(nav.includes(text), text);
        }
        assert.match(nav, /:href="route\(showRouteName, board\.id\)"/);
        assert.match(nav, /<BoardOrderMenu v-if="isAdmin && canEdit/);
        assert.match(read('resources/js/Components/Tasks/BoardOrderMenu.vue'), /route\('admin\.tarefas\.quadros\.reordenar'\)/);
    });

    it('o cabeçalho de cada quadro tem chevron e concentra as ações, com o excluir no menu ⋯ e confirmação', () => {
        const bar = read('resources/js/Components/Tasks/BoardHeader.vue');
        for (const text of [
            'Renomear quadro',
            'Adicionar aos favoritos',
            'Convidar',
            'Partilhar com empresa',
            'Ver arquivados',
            'Cor de capa do quadro',
            'Excluir quadro',
            'Mais ações do quadro',
            'Expandir quadro',
            'Recolher quadro',
        ]) {
            assert.ok(bar.includes(text), text);
        }
        assert.match(bar, /role="toolbar"/);
        assert.match(bar, /overflow-x-auto/);
        assert.match(bar, /:aria-expanded="expanded \? 'true' : 'false'"/);
        assert.match(bar, /async function deleteBoard\(\)[\s\S]*confirmDialog/);
        assert.match(bar, /only: \['boards'\]/);
        assert.doesNotMatch(bar, /BoardSwitcher|Novo quadro interno/);
    });

    it('Kanban ocupa a altura da tela e as colunas rolam por dentro', () => {
        const kanban = read('resources/js/Components/Tasks/KanbanBoard.vue');
        assert.match(kanban, /fillHeight: \{ type: Boolean, default: false \}/);
        assert.match(kanban, /props\.fillHeight \|\| listCardCount\(list\) > LIST_SCROLL_AFTER_CARDS/);
        assert.match(kanban, /fillHeight \? 'max-h-full' : ''/);
        const section = read('resources/js/Components/Tasks/BoardSection.vue');
        assert.match(section, /:fill-height="fillHeight"/);
        assert.match(section, /height: 'min\(70vh, 46rem\)'/);
        assert.match(kanban, /:group="`kanban-cards-\$\{boardPayload\.id\}`"/);
        assert.match(kanban, /const scope = \(\) => rootEl\.value \?\? document;/);
    });

    it('cartão mostra a descrição recolhível e o nome das etiquetas', () => {
        const kanban = read('resources/js/Components/Tasks/KanbanBoard.vue');
        assert.match(kanban, /<BoardDescriptionToggle :description="descriptionPlainText\(card\.description\)" \/>/);
        assert.match(kanban, /<span class="truncate">\{\{ lb\.name \}\}<\/span>/);
    });

    it('descrição oculta por padrão, botão acessível que não propaga o clique e some sem descrição', () => {
        const src = read('resources/js/Components/Tasks/BoardDescriptionToggle.vue');
        assert.match(src, /const expanded = ref\(false\)/);
        assert.match(src, /<div v-if="text" class="relative z-10/);
        assert.match(src, /:aria-expanded=/);
        assert.match(src, /@click\.stop\.prevent="expanded = !expanded"/);
        assert.match(src, /'Ocultar descrição' : 'Ver descrição'/);
    });

    it('menus da barra: botão com aria-expanded, painel no <body> e fecha com Esc', () => {
        const menu = read('resources/js/Components/Tasks/TaskToolbarMenu.vue');
        assert.match(menu, /:aria-expanded="open \? 'true' : 'false'"/);
        assert.match(menu, /<Teleport to="body">/);
        assert.match(menu, /event\.key === 'Escape'/);
    });
});
