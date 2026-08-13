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

function convertSaleButtonBlock(source) {
    const match = source.match(
        /<button\s+v-if="canConvert\(p\)"[\s\S]*?<\/button>/,
    );
    assert.ok(match, 'Botão canConvert(p) não encontrado em Proposals/Index.vue');

    return match[0];
}

describe('Proposals Index — botão converter em venda', () => {
    it('mostra só o ícone (sem rótulo «Venda») e mantém título/aria-label', () => {
        const button = convertSaleButtonBlock(proposalsIndex);

        assert.match(button, /title="Converter em venda"/);
        assert.match(button, /aria-label="Converter em venda"/);
        assert.match(button, /<BanknotesIcon\b/);

        const withoutIconTag = button.replace(/<BanknotesIcon\b[^>]*\/>/, '');
        assert.doesNotMatch(
            withoutIconTag,
            />\s*Venda\s*</,
            'O botão não deve exibir o texto «Venda» ao lado do ícone',
        );
    });
});
