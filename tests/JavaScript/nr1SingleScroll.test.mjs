import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, it } from 'node:test';

/**
 * Painel NR-1: um único scroll vertical (o <main> do layout padrão) e a sub-sidebar parada ao lado do conteúdo.
 * Verificação estática da estrutura: o comportamento real é conferido no navegador (1440×900 e 1366×700).
 */
const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

const vueFiles = (dir) =>
    readdirSync(new URL(`../../${dir}`, import.meta.url), { withFileTypes: true }).flatMap((entry) =>
        entry.isDirectory() ? vueFiles(join(dir, entry.name)) : entry.name.endsWith('.vue') ? [join(dir, entry.name).replaceAll('\\', '/')] : [],
    );

describe('NR-1: scroll único', () => {
    it('o <main> do layout padrão é o container de scroll e volta ao topo na navegação (scroll-region)', () => {
        const layout = read('resources/js/Components/SidebarLayout.vue');
        assert.match(layout, /id="conteudo-principal"[\s\S]*?scroll-region[\s\S]*?class="app-shell-main-scroll[^"]*overflow-y-auto/);
    });

    it('a moldura carrega o CSS do scroll único, limitado às páginas com a moldura de gestão e só na tela', () => {
        assert.match(read('resources/js/Components/Nr1Shell/Nr1Frame.vue'), /import '\.\.\/\.\.\/\.\.\/css\/nr1-frame-scroll\.css';/);
        const css = read('resources/css/nr1-frame-scroll.css');
        assert.match(css, /@media screen and \(min-width: 1280px\)/);
        assert.match(css, /\.app-shell:has\(\.nr1-frame:not\(\.nr1-frame--public\)\) \[data-shell-column\] \{[^}]*height: 100dvh/);
        // Cabeçalho do NR-1 continua rolando com o conteúdo.
        assert.match(css, /\.nr1-frame:not\(\.nr1-frame--public\) \.nr1-shell \.n1-header \{[^}]*position: relative/);
    });

    it('a sub-sidebar é sticky (sem position: fixed) e não tem scroll vertical próprio fora das alturas extremas', () => {
        const subNav = read('resources/js/Components/Nr1Shell/Nr1SubNav.vue');
        assert.match(subNav, /xl:sticky xl:top-8 xl:self-start/);
        assert.doesNotMatch(subNav, /\b(xl:)?fixed\b|overflow-y-(auto|scroll)|xl:overflow-auto|max-h-/);
        const css = read('resources/css/nr1-frame-scroll.css');
        const overflowRules = [...css.matchAll(/@media \(max-height: (\d+)px\) \{[^@]*?overflow-y: auto/g)].map((m) => Number(m[1]));
        assert.deepEqual(overflowRules, [540]);
    });

    it('entre o <main> e a sub-sidebar nenhum ancestral corta o overflow (o sticky deixaria de funcionar)', () => {
        const frame = read('resources/js/Components/Nr1Shell/Nr1Frame.vue');
        const root = frame.match(/<template>\s*<div\s+class="nr1-frame"[\s\S]*?>/)[0];
        assert.doesNotMatch(root, /overflow-(hidden|auto|clip|scroll|y-)/);
        const section = read('resources/js/Pages/Nr1/Section.vue');
        assert.doesNotMatch(section.split('<component :is="layout"')[1], /overflow-(y-)?(auto|scroll|hidden)/);
    });

    it('nenhum painel do NR-1 cria uma segunda barra vertical (só modais podem rolar por dentro)', () => {
        const files = [
            ...vueFiles('resources/js/Components/Nr1Shell'),
            ...vueFiles('resources/js/Components/Nr1Legacy'),
            ...vueFiles('resources/js/Components/Nr1Campaign'),
            ...vueFiles('resources/js/Components/Nr1'),
            'resources/js/Components/Nr1SurveyResultsPanel.vue',
            'resources/js/Pages/Nr1/Section.vue',
        ];
        const allowedModals = new Set(['resources/js/Components/Nr1Legacy/LaudoBody.vue', 'resources/js/Components/Nr1Legacy/Nr1DocumentPreviewModal.vue']);
        const offenders = files.filter((file) => !allowedModals.has(file) && /overflow-y-(auto|scroll)|\boverflow-(auto|scroll)\b|max-h-\[/.test(read(file)));
        assert.deepEqual(offenders, []);
    });
});
