import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    claimProposalKanbanMenu,
    getActiveProposalKanbanMenuId,
    releaseProposalKanbanMenu,
    resetProposalKanbanMenu,
} from '../../resources/js/composables/useExclusiveProposalKanbanMenu.js';

describe('useExclusiveProposalKanbanMenu', () => {
    it('claim de outro card substitui o menu ativo (só um de cada vez)', () => {
        resetProposalKanbanMenu();
        claimProposalKanbanMenu(1);
        assert.equal(getActiveProposalKanbanMenuId(), 1);

        claimProposalKanbanMenu(2);
        assert.equal(getActiveProposalKanbanMenuId(), 2);

        releaseProposalKanbanMenu(1);
        assert.equal(getActiveProposalKanbanMenuId(), 2, 'fechar o menu antigo não liberta o novo');

        releaseProposalKanbanMenu(2);
        assert.equal(getActiveProposalKanbanMenuId(), null);
    });
});
