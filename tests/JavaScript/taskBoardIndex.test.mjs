import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';

/**
 * Listagem de quadros (Admin e portal): card inteiro abre o quadro por <Link> (sem botão «Abrir»)
 * e a descrição vem oculta, com botão próprio que não navega. Verificação estática da estrutura.
 */
const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

const pages = {
    admin: { file: 'resources/js/Pages/Admin/Tasks/Boards/Index.vue', route: "route('admin.tarefas.quadros.show', board.id)", sections: 2 },
    client: { file: 'resources/js/Pages/Client/Tasks/Index.vue', route: "route('client.tarefas.show', b.id)", sections: 1 },
};

describe('Tarefas: listagem de quadros', () => {
    for (const [name, { file, route, sections }] of Object.entries(pages)) {
        it(`${name}: sem botão Abrir; o nome é um <Link> que cobre o card`, () => {
            const src = read(file);
            assert.doesNotMatch(src, />\s*Abrir\s*</);
            const links = [...src.matchAll(/<Link\s+:href="([^"]+)"\s+class="([^"]+)"/g)].filter(([, href]) => href === route);
            assert.equal(links.length, sections);
            for (const [, , cls] of links) {
                assert.match(cls, /after:absolute after:inset-0/);
                assert.match(cls, /focus-visible:after:ring-2/);
            }
            assert.doesNotMatch(src, /router\.visit/);
        });

        it(`${name}: controles internos ficam acima do link e não propagam o clique`, () => {
            const src = read(file);
            assert.equal((src.match(/<BoardDescriptionToggle :description="/g) || []).length, sections);
            assert.equal((src.match(/class="relative z-10 rounded p-1[^"]*"/g) || []).length, sections);
            assert.equal((src.match(/@click\.stop="toggleCollapsed/g) || []).length, sections);
            assert.doesNotMatch(src, /@click="toggleCollapsed/);
        });
    }

    it('admin: alça de arrastar e prévia das listas ficam acima do link', () => {
        assert.equal((read(pages.admin.file).match(/board-index-drag-handle relative z-10/g) || []).length, 2);
        assert.match(read('resources/js/Components/Tasks/BoardIndexPreview.vue'), /class="relative z-10 flex gap-3 overflow-x-auto/);
    });

    it('descrição oculta por padrão, botão acessível que não navega e some sem descrição', () => {
        const src = read('resources/js/Components/Tasks/BoardDescriptionToggle.vue');
        assert.match(src, /const expanded = ref\(false\)/);
        assert.match(src, /<div v-if="text" class="relative z-10/);
        assert.match(src, /<button\s+type="button"/);
        assert.match(src, /:aria-expanded=/);
        assert.match(src, /:aria-controls="panelId"/);
        assert.match(src, /@click\.stop\.prevent="expanded = !expanded"/);
        assert.match(src, /'Ocultar descrição' : 'Ver descrição'/);
    });
});
