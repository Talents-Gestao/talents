import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';
import { helpTipId } from '../../resources/js/Components/Nr1Shell/nr1HelpTip.js';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const read = (path) => readFileSync(join(root, path), 'utf8');
const shellDir = 'resources/js/Components/Nr1Shell';
const shellFiles = readdirSync(join(root, shellDir)).filter((f) => f.endsWith('.vue')).map((f) => `${shellDir}/${f}`);
const panelFiles = [
    ...shellFiles,
    'resources/js/Components/Nr1Legacy/LaudoBody.vue',
    'resources/js/Components/Nr1SurveyResultsPanel.vue',
    'resources/js/Pages/Nr1/Companies.vue',
];

describe('ícone de ajuda do painel NR-1 (tooltip)', () => {
    const tip = read(`${shellDir}/Nr1HelpTip.vue`);

    it('ids únicos para ligar botão e balão', () => {
        const a = helpTipId();
        const b = helpTipId();
        assert.notEqual(a, b);
        assert.match(a, /^n1-ajuda-\d+$/);
    });

    it('acessível: role="tooltip", aria-describedby, hover, foco, toque e Esc; botão pelo Nr1Button', () => {
        assert.match(tip, /role="tooltip"/);
        assert.match(tip, /:aria-describedby="id"/);
        assert.match(tip, /:aria-label="ariaLabel"/);
        for (const handler of ['@mouseenter="show"', '@mouseleave="hide"', '@focus="show"', '@blur="hide"', '@click.stop.prevent="toggle"']) {
            assert.ok(tip.includes(handler), handler);
        }
        assert.match(tip, /event\.key === 'Escape'/);
        assert.match(tip, /<Nr1Button[\s\S]*?variant="icon"/);
        assert.doesNotMatch(tip, /<button/);
    });

    it('textos convertidos: o parágrafo solto sob o título virou tooltip', () => {
        const converted = {
            'LegacyOverviewView.vue': 'Panorama da campanha com os resultados já calculados no modelo anterior.',
            'DocumentsView.vue': 'Arquivos finais organizados em um só lugar, com revisão documental da PASSEG quando necessária.',
            'LegacyDocumentsView.vue': 'A publicação do parecer exige o inventário psicossocial no painel da empresa.',
            'PlanView.vue': 'A empresa confirma quem fará cada medida e acompanha os resultados.',
            'LegacyPlanView.vue': 'Medidas do plano registrado no parecer técnico desta campanha, com responsáveis e prazos.',
            'SolutionsView.vue': 'Serviços que podem apoiar o plano de ação, a partir dos fatores sinalizados na pesquisa.',
            'ServiceSuggestionList.vue': 'A Talents revisa o texto e só então a empresa vê a sugestão.',
        };
        for (const [file, text] of Object.entries(converted)) {
            const src = read(`${shellDir}/${file}`);
            assert.ok(src.includes('<Nr1HelpTip'), file);
            assert.match(src, new RegExp(`text=["'\`][^]*?${text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`), file);
            assert.doesNotMatch(src, new RegExp(`<p[^>]*>\\s*${text.slice(0, 30).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`), `${file}: texto ainda solto`);
        }
    });

    it('cenário geral: tooltip sem a palavra «executivo»', () => {
        const laudo = read('resources/js/Components/Nr1Legacy/LaudoBody.vue');
        const block = laudo.match(/label="o cenário geral"[\s\S]*?\/>/)?.[0] ?? '';
        assert.ok(block.includes('Inventário de Riscos Psicossocial'));
        assert.doesNotMatch(block, /executiv/i);
    });
});

describe('itens removidos do painel NR-1', () => {
    const sources = panelFiles.map((file) => [file, read(file)]);
    const offenders = (pattern) => sources.filter(([, src]) => pattern.test(src)).map(([file]) => file);

    it('sem os títulos «Laudo NR-1» e «Cabeçalho do laudo»', () => {
        assert.deepEqual(offenders(/>\s*Laudo NR-1\s*</), []);
        assert.deepEqual(offenders(/Cabeçalho do laudo/), []);
    });

    it('sem «Relatório executivo» e sem o nome antigo do inventário', () => {
        assert.deepEqual(offenders(/Relatório executivo|Resumo executivo|title: 'Relatório executivo'/), []);
        assert.deepEqual(offenders(/[Rr]elatório técnico \+ plano|[Rr]elatório técnico e plano/), []);
    });

    it('sem os botões «Cadastro técnico» e «Inventário de Riscos Psicossocial» no parecer', () => {
        const legacyDocs = read(`${shellDir}/LegacyDocumentsView.vue`);
        assert.doesNotMatch(legacyDocs, /registration\.edit|>\s*Cadastro técnico\s*</);
        const laudo = read('resources/js/Components/Nr1Legacy/LaudoBody.vue');
        assert.doesNotMatch(laudo, /<SecondaryButton type="button" @click="openTechnicalReportModal">/);
        // O inventário continua acessível pela publicação (link) e pelos anexos; o cadastro, pela prévia do inventário.
        assert.match(laudo, /@click="openTechnicalReportModal"[\s\S]*?Abrir inventário/);
        assert.match(laudo, /:registration-url="registrationUrl"/);
    });

    it('«Ir para» em uma linha com rolagem no celular (causa raiz do sticky em nr1StickyJumpNav.test.mjs)', () => {
        const panel = read('resources/js/Components/Nr1SurveyResultsPanel.vue');
        assert.match(panel, /class="nr1-jump-nav sticky[^"]*flex-nowrap[^"]*overflow-x-auto[^"]*sm:flex-wrap/);
        assert.doesNotMatch(panel, /scroll-mt-24/);
    });
});
