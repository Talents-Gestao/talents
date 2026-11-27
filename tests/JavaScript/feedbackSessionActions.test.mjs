import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const read = (path) => readFileSync(join(root, path), 'utf8');

describe('detalhe do feedback: cabeçalho, kebab e abas', () => {
    const show = read('resources/js/Pages/Client/Feedbacks/Sessions/Show.vue');
    const header = read('resources/js/Components/Feedback/FeedbackSessionHeader.vue');
    const tabs = read('resources/js/Components/Feedback/FeedbackTabs.vue');
    const section = read('resources/js/Components/Feedback/FeedbackRecordingSection.vue');

    it('uma ação principal e um kebab com as ações secundárias; nenhum menu se chama «Ações»', () => {
        assert.match(header, /data-feedback-primary-action/);
        assert.match(header, /<FeedbackActionsMenu v-if="menuItems\.length" :items="menuItems" label="Mais ações do feedback" \/>/);
        assert.doesNotMatch(show + header + section, /label="Ações"/);
        assert.match(section, /label="Opções da gravação"/);
    });

    it('kebab: Editar, Exportar PDF, Gerar relatório novamente e, por último, Excluir (destrutivo)', () => {
        const order = ['edit', 'pdf', 'report', 'delete'].map((k) => show.indexOf(`key: '${k}'`));
        assert.ok(order.every((i, n) => i > -1 && (n === 0 || i > order[n - 1])));
        assert.match(show, /key: 'delete'[^\n]*danger: true/);
        // Concluído, cancelado ou com convite de assinatura: sem «Editar»; aguardando assinatura oferece «Reabrir para edição».
        assert.match(show, /\['completed', 'cancelled', 'awaiting_signatures'\]\.includes\(props\.session\.status\)/);
        assert.match(show, /key: 'reopen', label: 'Reabrir para edição'/);
        assert.match(show, /href: feedbackRoute\('sessions\.edit', props\.session\.id\)/);
    });

    it('o «Resumo» e o stepper do «Fluxo de finalização» saíram; nome do colaborador com link no título', () => {
        assert.doesNotMatch(show, /title="Resumo"|Fluxo de finalização/);
        assert.doesNotMatch(read('resources/js/Components/Feedback/FeedbackSessionSignaturesPanel.vue'), /Fluxo de finalização|currentStep/);
        assert.match(header, /Feedback ·/);
        assert.match(header, /feedbackRoute\('employees\.show', session\.employee_profile_id\)/);
    });

    it('abas WAI-ARIA com setas, Home e End, e a aba na URL', () => {
        assert.match(tabs, /role="tablist"/);
        assert.match(tabs, /role="tab"/);
        assert.match(tabs, /ArrowRight[\s\S]*ArrowLeft[\s\S]*Home[\s\S]*End/);
        assert.match(show, /role="tabpanel"/g);
        assert.match(show, /router\.push\(\{ url: urlWithTab\(page\.url, key\)/);
        assert.match(show, /tabFromUrl\(page\.url\)/);
    });

    it('a gravação vive na aba, com o layout interno de antes (embedded), e avisos ficam dentro da aba', () => {
        assert.match(show, /<FeedbackRecordingPanel[\s\S]*?embedded/);
        assert.match(show, /<FeedbackFlash v-if="tab === 'gravacao'" \/>/);
        assert.doesNotMatch(show, /<FeedbackFlash class="mb-6" \/>/);
    });

    it('sem elemento fixo (sticky) na página', () => {
        for (const file of [show, header, tabs, read('resources/js/Components/Feedback/FeedbackAnswersList.vue')]) {
            assert.doesNotMatch(file, /\bsticky\b/);
        }
    });

    it('o menu fecha com Esc e clique fora e navega pelas setas', () => {
        const menu = read('resources/js/Components/Feedback/FeedbackActionsMenu.vue');
        assert.match(menu, /event\.key === 'Escape'/);
        assert.match(menu, /ArrowDown/);
        assert.match(menu, /document\.addEventListener\('click', onDocumentClick\)/);
        assert.match(menu, /aria-haspopup="menu"/);
        assert.match(menu, /EllipsisVerticalIcon/);
    });
});
