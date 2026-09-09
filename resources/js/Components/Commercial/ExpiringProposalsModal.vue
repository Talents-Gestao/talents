<script setup>
import FullScreenOverlay from '@/Components/FullScreenOverlay.vue';
import Modal from '@/Components/Modal.vue';
import { formatDateNumeric } from '@/utils/dateOnly';
import { XMarkIcon } from '@heroicons/vue/24/outline';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    proposals: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'edit-status']);

const contactingId = ref(null);
const notesProposal = ref(null);
const notesForm = useForm({
    notes: '',
});

const notesModalOpen = computed(() => notesProposal.value !== null);

const formatValidUntil = (ymd) => {
    const formatted = formatDateNumeric(ymd);
    return formatted || '—';
};

const close = () => {
    if (notesModalOpen.value) {
        closeNotes();
        return;
    }
    emit('close');
};

const markContacted = (proposal) => {
    if (proposal.is_contacted || contactingId.value) {
        return;
    }
    contactingId.value = proposal.id;
    router.patch(route('admin.comercial.propostas.contacted', proposal.id), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['expiringProposals'],
        onFinish: () => {
            contactingId.value = null;
        },
    });
};

const openNotes = (proposal) => {
    notesProposal.value = proposal;
    notesForm.notes = proposal.notes ?? '';
    notesForm.clearErrors();
};

const closeNotes = () => {
    if (notesForm.processing) {
        return;
    }
    notesProposal.value = null;
    notesForm.reset();
    notesForm.clearErrors();
};

const saveNotes = () => {
    if (!notesProposal.value) {
        return;
    }
    notesForm.patch(route('admin.comercial.propostas.notes', notesProposal.value.id), {
        preserveScroll: true,
        preserveState: true,
        only: ['expiringProposals', 'flash'],
        onSuccess: () => {
            notesProposal.value = null;
            notesForm.reset();
            notesForm.clearErrors();
        },
    });
};

const openStatus = (proposal) => {
    emit('edit-status', proposal);
};
</script>

<template>
    <FullScreenOverlay
        :show="show"
        z-index-class="z-[90]"
        overlay-class="bg-gray-500/75 p-4"
        @close="close"
    >
        <div class="flex max-h-[min(82vh,760px)] w-full max-w-4xl flex-col overflow-hidden rounded-lg bg-white shadow-xl">
            <header class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                <div class="min-w-0">
                    <h2 class="text-xl font-semibold tracking-tight text-slate-900">
                        Propostas prestes a vencer
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Propostas com validade próxima do vencimento
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                    title="Fechar"
                    aria-label="Fechar"
                    @click="emit('close')"
                >
                    <XMarkIcon class="h-5 w-5" />
                </button>
            </header>

            <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                <ul v-if="proposals.length" class="divide-y divide-slate-200">
                    <li
                        v-for="proposal in proposals"
                        :key="proposal.id"
                        class="py-4 first:pt-1 last:pb-1"
                    >
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold uppercase tracking-wide text-slate-900">
                                    {{ proposal.contact_name }}
                                </p>
                                <p
                                    v-if="proposal.company_name"
                                    class="mt-0.5 truncate text-sm text-slate-500"
                                >
                                    {{ proposal.company_name }}
                                </p>
                                <p class="mt-0.5 text-xs text-slate-400">
                                    {{ proposal.code }}
                                </p>
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="
                                            proposal.is_expired
                                                ? 'bg-slate-100 text-slate-600'
                                                : 'bg-amber-50 text-amber-800'
                                        "
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="proposal.is_expired ? 'bg-slate-400' : 'bg-amber-500'"
                                            aria-hidden="true"
                                        />
                                        {{ proposal.is_expired ? 'Expirado' : 'A vencer' }}
                                    </span>
                                    <span class="text-xs text-slate-500">
                                        Vence em {{ formatValidUntil(proposal.valid_until) }}
                                    </span>
                                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-900">
                                        Proposta comercial
                                    </span>
                                    <span
                                        v-if="proposal.is_contacted"
                                        class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-800"
                                    >
                                        Contatado
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 lg:justify-end lg:pt-0.5">
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-md px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition"
                                    :class="
                                        proposal.is_contacted
                                            ? 'bg-emerald-700/80'
                                            : 'bg-emerald-600 hover:bg-emerald-700'
                                    "
                                    :disabled="proposal.is_contacted || contactingId === proposal.id"
                                    @click="markContacted(proposal)"
                                >
                                    {{
                                        proposal.is_contacted
                                            ? 'Contatado'
                                            : contactingId === proposal.id
                                                ? 'Salvando…'
                                                : 'Contatado'
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-blue-700"
                                    @click="openStatus(proposal)"
                                >
                                    Status
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-md bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-violet-700"
                                    @click="openNotes(proposal)"
                                >
                                    Observação
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>
                <p v-else class="py-10 text-center text-sm text-slate-500">
                    Nenhuma proposta próxima do vencimento.
                </p>
            </div>
        </div>
    </FullScreenOverlay>

    <Modal :show="notesModalOpen" max-width="md" @close="closeNotes">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-slate-900">Observação</h3>
            <p v-if="notesProposal" class="mt-1 text-sm text-slate-500">
                {{ notesProposal.contact_name }}
                <span v-if="notesProposal.code"> · {{ notesProposal.code }}</span>
            </p>
            <textarea
                v-model="notesForm.notes"
                rows="5"
                class="mt-4 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                placeholder="Registre o contato, combinados ou próximos passos…"
            />
            <p v-if="notesForm.errors.notes" class="mt-1 text-sm text-rose-600">
                {{ notesForm.errors.notes }}
            </p>
            <div class="mt-4 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    :disabled="notesForm.processing"
                    @click="closeNotes"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700 disabled:opacity-60"
                    :disabled="notesForm.processing"
                    @click="saveNotes"
                >
                    {{ notesForm.processing ? 'Salvando…' : 'Salvar observação' }}
                </button>
            </div>
        </div>
    </Modal>
</template>
