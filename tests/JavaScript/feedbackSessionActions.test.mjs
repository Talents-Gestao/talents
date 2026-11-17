import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const read = (path) => readFileSync(join(root, path), 'utf8');

describe('detalhe do feedback: menu «Ações»', () => {
    const show = read('resources/js/Pages/Client/Feedbacks/Sessions/Show.vue');

    it('o cabeçalho tem só o botão «Ações», sem «Editar» solto', () => {
        assert.match(show, /<FeedbackActionsMenu :items="headerMenuItems" show-label label="Ações" variant="primary" \/>/);
        assert.match(show, /<template v-if="headerMenuItems\.length" #trailing>/);
        assert.doesNotMatch(show, /Mais ações/);
        assert.doesNotMatch(show, /<Link[\s\S]*?sessions\.edit/);
    });

    it('ordem: Editar, Exportar PDF e, por último, Excluir (destrutivo)', () => {
        const edit = show.indexOf("key: 'edit'");
        const pdf = show.indexOf("key: 'pdf'");
        const del = show.indexOf("key: 'delete'");
        assert.ok(edit > -1 && edit < pdf && pdf < del);
        assert.match(show, /key: 'delete'[^\n]*danger: true/);
    });

    it('Editar mantém a regra do antigo botão (não concluído nem cancelado) e a mesma rota', () => {
        assert.match(show, /status !== 'completed' && props\.session\.status !== 'cancelled'/);
        assert.match(show, /href: feedbackRoute\('sessions\.edit', props\.session\.id\)/);
    });

    it('o menu fecha com Esc e clique fora e navega pelas setas', () => {
        const menu = read('resources/js/Components/Feedback/FeedbackActionsMenu.vue');
        assert.match(menu, /event\.key === 'Escape'/);
        assert.match(menu, /ArrowDown/);
        assert.match(menu, /document\.addEventListener\('click', onDocumentClick\)/);
        assert.match(menu, /aria-haspopup="menu"/);
    });
});
