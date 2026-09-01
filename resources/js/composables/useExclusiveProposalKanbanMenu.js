import { ref } from 'vue';

/** Só um menu de card do Kanban de propostas fica aberto de cada vez. */
export const activeProposalKanbanMenuId = ref(null);

export function getActiveProposalKanbanMenuId() {
    return activeProposalKanbanMenuId.value;
}

export function claimProposalKanbanMenu(proposalId) {
    activeProposalKanbanMenuId.value = proposalId;
}

export function releaseProposalKanbanMenu(proposalId) {
    if (activeProposalKanbanMenuId.value === proposalId) {
        activeProposalKanbanMenuId.value = null;
    }
}

export function resetProposalKanbanMenu() {
    activeProposalKanbanMenuId.value = null;
}
