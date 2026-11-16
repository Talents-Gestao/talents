import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    descriptionToggleLabel,
    hasText,
    initialDescriptionState,
    showsFilledIndicator,
    toggleDescription,
} from '../../resources/js/Components/Tasks/taskDescriptionToggle.js';

describe('botão de descrição da etapa (só ícone)', () => {
    it('rótulo acessível conforme o estado', () => {
        assert.equal(descriptionToggleLabel({ open: false, filled: false }), 'Adicionar descrição');
        assert.equal(descriptionToggleLabel({ open: false, filled: true }), 'Ver descrição');
        assert.equal(descriptionToggleLabel({ open: true, filled: false }), 'Ocultar descrição');
        assert.equal(descriptionToggleLabel({ open: true, filled: true }), 'Ocultar descrição');
    });

    it('indicador discreto só quando há descrição e está recolhida', () => {
        assert.equal(showsFilledIndicator({ open: false, filled: true }), true);
        assert.equal(showsFilledIndicator({ open: true, filled: true }), false);
        assert.equal(showsFilledIndicator({ open: false, filled: false }), false);
    });

    it('texto só com espaços não conta como descrição', () => {
        assert.equal(hasText('  '), false);
        assert.equal(hasText(null), false);
        assert.equal(hasText(' Revisar '), true);
    });

    it('abre e fecha sem tocar nas outras etapas', () => {
        const opened = toggleDescription({ 'item-2': true }, 'item-1');
        assert.deepEqual(opened, { 'item-2': true, 'item-1': true });
        assert.deepEqual(toggleDescription(opened, 'item-1'), { 'item-2': true, 'item-1': false });
    });

    it('começa aberta só com descrição; recarga mantém o que o usuário abriu e a nova etapa recolhida', () => {
        const items = [{ id: 1, description: 'Texto' }, { id: 2, description: '' }];
        const first = initialDescriptionState({}, items);
        assert.deepEqual(first, { 'item-1': true, 'item-2': false });

        const userChanged = { ...toggleDescription(toggleDescription(first, 'item-1'), 'item-2'), 'new-9': true };
        const reloaded = initialDescriptionState(userChanged, [...items, { id: 3, description: 'Nova' }], { reopened: false });
        assert.deepEqual(reloaded, { 'item-1': false, 'item-2': true, 'new-9': true, 'item-3': true });

        // Outro cartão aberto: recomeça do zero (nova etapa recolhida por padrão).
        assert.deepEqual(initialDescriptionState(userChanged, items), { 'item-1': true, 'item-2': false });
    });
});
