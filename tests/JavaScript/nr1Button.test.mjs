import assert from 'node:assert/strict';
import test from 'node:test';
import { SIZES, VARIANTS, buttonClasses } from '../../resources/js/Components/Nr1Shell/nr1Button.js';

test('cada variante gera a classe base, a da variante e a do tamanho', () => {
    for (const variant of VARIANTS) {
        const classes = buttonClasses({ variant });
        assert.deepEqual(classes.slice(0, 3), ['n1b', `n1b--${variant}`, 'n1b--md']);
    }
});

test('variantes do contrato: primary, secondary, inverse, ghost-on-dark, ghost-on-light, link, icon e chip', () => {
    assert.deepEqual(VARIANTS, ['primary', 'secondary', 'inverse', 'ghost-on-dark', 'ghost-on-light', 'link', 'icon', 'chip']);
    assert.deepEqual(SIZES, ['md', 'sm']);
});

test('estados e modificadores viram classes (block, loading, selected)', () => {
    const classes = buttonClasses({ variant: 'chip', size: 'sm', block: true, loading: true, selected: true });
    for (const expected of ['n1b--sm', 'n1b--block', 'is-loading', 'is-selected']) {
        assert.ok(classes.includes(expected), `falta ${expected}`);
    }
    assert.ok(!buttonClasses({ variant: 'primary' }).includes('is-loading'));
});

test('variante ou tamanho desconhecidos caem no padrão seguro (secondary, md)', () => {
    assert.deepEqual(buttonClasses({ variant: 'neon', size: 'xl' }).slice(0, 3), ['n1b', 'n1b--secondary', 'n1b--md']);
    assert.deepEqual(buttonClasses().slice(0, 3), ['n1b', 'n1b--secondary', 'n1b--md']);
});
