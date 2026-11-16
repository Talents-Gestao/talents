import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';

/*
 * Causa raiz do bug «Ir para não fica fixo»: abaixo de 1280px quem rola é o documento, mas ancestrais da barra no
 * layout padrão (painel, linha principal e <main>) têm overflow hidden/auto e viram contêineres de rolagem que
 * nunca rolam; o sticky gruda neles. Este teste percorre a cadeia REAL de ancestrais no SidebarLayout, acha cada
 * um que cria contêiner de rolagem e exige que o CSS do NR-1 o neutralize (overflow: clip) nessa faixa de largura.
 */
const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const read = (path) => readFileSync(join(root, path), 'utf8');

const layout = read('resources/js/Components/SidebarLayout.vue');
const frameCss = read('resources/css/nr1-frame-scroll.css');
const panel = read('resources/js/Components/Nr1SurveyResultsPanel.vue');

/** Abertura de tag que contém o marcador, com a lista de classes estáticas. */
function openingTag(marker) {
    const index = layout.indexOf(marker);
    assert.ok(index > 0, `marcador ${marker} não encontrado no SidebarLayout`);
    const start = layout.lastIndexOf('<', index);
    const end = layout.indexOf('>', index);
    const tag = layout.slice(start, end + 1);
    const classes = (tag.match(/\sclass="([^"]*)"/)?.[1] ?? '').split(/\s+/).filter(Boolean);

    return { tag, classes };
}

/** Classes sem prefixo responsivo que criam contêiner de rolagem (hidden, auto, scroll em algum eixo). */
const scrollContainerClasses = (classes) => classes.filter((c) => /^overflow(-[xy])?-(hidden|auto|scroll)$/.test(c));

/** Bloco CSS de uma media query (conteúdo entre as chaves de mesmo nível). */
function mediaBlock(css, query) {
    const start = css.indexOf(`@media ${query}`);
    assert.ok(start >= 0, `media ${query} não encontrada`);
    let depth = 0;
    for (let i = css.indexOf('{', start); i < css.length; i++) {
        if (css[i] === '{') depth++;
        if (css[i] === '}' && --depth === 0) return css.slice(start, i + 1);
    }
    throw new Error('media sem fechamento');
}

const below1280 = mediaBlock(frameCss, 'screen and (max-width: 1279.98px)');

// Cadeia do conteúdo do painel até a raiz da coluna (de dentro para fora), com o seletor usado no CSS do NR-1.
const CHAIN = [
    { marker: 'id="conteudo-principal"', selector: '.app-shell-main-scroll' },
    { marker: 'data-shell-main-row', selector: '[data-shell-main-row]' },
    { marker: 'class="app-shell-panel', selector: '.app-shell-panel' },
    { marker: 'data-shell-column', selector: '[data-shell-column]' },
];

describe('«Ir para» dos Resultados: nenhum ancestral prende o sticky abaixo de 1280px', () => {
    it('a cadeia real tem contêineres de rolagem (o bug) e cada um é solto com overflow: clip', () => {
        const trapping = CHAIN.filter(({ marker }) => scrollContainerClasses(openingTag(marker).classes).length > 0);
        // Hoje: <main> (overflow-y-auto), linha principal e painel (overflow-hidden). Se o layout mudar, o teste acompanha.
        assert.ok(trapping.length >= 1, 'esperado ao menos um ancestral com overflow no layout');

        for (const { selector, marker } of trapping) {
            const rule = new RegExp(
                `\\.app-shell:has\\(\\.nr1-frame:not\\(\\.nr1-frame--public\\)\\) ${selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}[^{]*\\{[^}]*overflow: clip;`,
            );
            const grouped = new RegExp(`${selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}[,\\s][\\s\\S]*?\\{\\s*overflow: clip;`);
            assert.ok(rule.test(below1280) || grouped.test(below1280), `${marker} (${scrollContainerClasses(openingTag(marker).classes).join(', ')}) não é solto abaixo de 1280px`);
        }
    });

    it('clip não cria contêiner de rolagem: nenhum hidden/auto/scroll sobra nas regras do NR-1 abaixo de 1280px', () => {
        assert.doesNotMatch(below1280, /overflow(-[xy])?:\s*(hidden|auto|scroll)/);
    });

    it('o cabeçalho e a barra do celular continuam rolando com a página (só o «Ir para» passa a fixar)', () => {
        assert.match(frameCss, /^\.nr1-frame:not\(\.nr1-frame--public\) \.nr1-shell \.n1-header \{\s*position: relative;/m);
        assert.match(below1280, /\[data-shell-mobilebar\] \{\s*position: relative;/);
    });

    it('a barra gruda no topo da área que rola, com fundo opaco e acima dos gráficos; âncoras param abaixo dela', () => {
        assert.match(panel, /class="nr1-jump-nav sticky z-20 [^"]*\bbg-white\b/);
        assert.doesNotMatch(panel, /class="nr1-jump-nav[^"]*bg-white\//);
        assert.match(panel, /\.nr1-jump-nav \{\s*top: 0;/);
        // Sem variável publicada por JS (que podia chegar vazia ou tarde): o recuo usa a altura medida da própria barra.
        assert.doesNotMatch(panel, /--n1-sticky-offset/);
        assert.match(panel, /scroll-margin-top: calc\(var\(--nr1-jump-h, 3\.25rem\) \+ 12px\)/);
        assert.match(panel, /'--nr1-jump-h': `\$\{jumpHeight\}px`/);
        // A barra é filha direta do contêiner que engloba todas as seções (o pai não tem só a altura da barra).
        assert.match(panel, /<div class="space-y-6"[^>]*>\s*<nav\s+ref="jumpNav"/);
        assert.match(panel, /id="nr1-insights"/);
    });

    it('sem publicação de altura do cabeçalho por JS', () => {
        assert.doesNotMatch(read('resources/js/Components/Nr1Shell/ShellHeader.vue'), /--n1-sticky-offset|ResizeObserver/);
    });
});
