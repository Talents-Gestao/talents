import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';

/*
 * Barra «Ir para» dos Resultados do NR-1: ESTÁTICA, parte do fluxo da página, rola com o conteúdo (não pode grudar no
 * topo e cobrir os gráficos). Os atalhos rolam suave até a seção, que para com um respiro (scroll-margin).
 */
const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const read = (path) => readFileSync(join(root, path), 'utf8');

const panel = read('resources/js/Components/Nr1SurveyResultsPanel.vue');
const frameCss = read('resources/css/nr1-frame-scroll.css');
const header = read('resources/js/Components/Nr1Shell/ShellHeader.vue');

const navTag = () => {
    const match = panel.match(/<nav\b[^>]*aria-label="Navegação dos resultados"[^>]*>/);
    assert.ok(match, 'barra «Ir para» não encontrada');
    return match[0];
};

describe('«Ir para» dos Resultados é estática', () => {
    it('a barra não é sticky nem fixed, sem top/z-index de barra grudada', () => {
        const nav = navTag();
        assert.doesNotMatch(nav, /\b(sticky|fixed)\b/);
        assert.doesNotMatch(nav, /\b(top-\S+|z-\S+|backdrop-blur\S*)/);
        assert.doesNotMatch(nav, /\bref="jumpNav"/);
        assert.doesNotMatch(panel, /\.nr1-jump-nav\s*\{/, 'sem regra CSS própria para posicionar a barra');
    });

    it('no celular continua em uma linha com rolagem lateral', () => {
        assert.match(navTag(), /flex-nowrap[^"]*overflow-x-auto[^"]*sm:flex-wrap[^"]*sm:overflow-visible/);
    });

    it('sem medição de altura nem variáveis que só existiam para o fixo', () => {
        assert.doesNotMatch(panel, /--nr1-jump-h|--n1-sticky-offset|jumpObserver|measureJump|ResizeObserver/);
        assert.doesNotMatch(header, /--n1-sticky-offset|ResizeObserver/);
    });

    it('nenhuma regra do NR-1 solta os ancestrais do layout para o sticky (overflow: clip) nem mexe na barra do celular', () => {
        assert.doesNotMatch(frameCss, /overflow:\s*clip/);
        assert.doesNotMatch(frameCss, /data-shell-mobilebar/);
        assert.doesNotMatch(frameCss, /max-width:\s*1279/);
    });
});

describe('atalhos do «Ir para»', () => {
    it('cada atalho rola suave até a seção', () => {
        for (const id of ['nr1-indicador', 'nr1-dimensoes', 'nr1-setores', 'nr1-perguntas', 'nr1-insights']) {
            assert.match(panel, new RegExp(`href="#${id}"[\\s\\S]*?@click="scrollToSection\\(\\$event, '${id}'\\)"`), id);
        }
        assert.match(panel, /scrollIntoView\(\{ behavior: 'smooth', block: 'start' \}\)/);
    });

    it('as seções de destino usam scroll-margin (param com respiro, sem ficar escondidas)', () => {
        assert.match(panel, /\.nr1-jump-target \{\s*scroll-margin-top: 1rem;\s*\}/);
        const targets = panel.match(/class="nr1-jump-target\b/g) ?? [];
        assert.ok(targets.length >= 5, `esperado ao menos 5 destinos, achados ${targets.length}`);
        for (const id of ['nr1-indicador', 'nr1-dimensoes', 'nr1-perguntas', 'nr1-insights']) {
            assert.match(panel, new RegExp(`id="${id}"[^>]*class="nr1-jump-target|class="nr1-jump-target[^"]*"[^>]*id="${id}"|id="${id}"[\\s\\S]{0,200}?nr1-jump-target`), id);
        }
    });
});
