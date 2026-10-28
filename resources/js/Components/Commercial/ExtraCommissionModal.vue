<script setup>
import FullScreenOverlay from '@/Components/FullScreenOverlay.vue';
import { formatBRL } from '@/composables/useCommercialPricing';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    proposal: { type: Object, default: null },
    users: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const form = useForm({
    user_id: '',
    notes: '',
});

const takenUserIds = computed(() => {
    const ids = new Set();
    const sellerId = Number(props.proposal?.seller_id ?? 0);
    if (sellerId > 0) {
        ids.add(sellerId);
    }
    for (const extra of props.proposal?.extra_commissions ?? []) {
        const id = Number(extra.user_id ?? 0);
        if (id > 0) {
            ids.add(id);
        }
    }
    return ids;
});

const availableUsers = computed(() =>
    (props.users ?? []).filter((user) => !takenUserIds.value.has(Number(user.id))),
);

const previewBaseCents = computed(() => {
    const saleTotal = Number(props.proposal?.sale?.total_cents ?? 0);
    if (saleTotal > 0) {
        return saleTotal;
    }

    const months = Number(props.proposal?.recurring_months ?? 0);
    const monthly = Number(props.proposal?.recurring_monthly_cents ?? 0);
    if (props.proposal?.is_recurring && months > 0 && monthly > 0) {
        return months * monthly;
    }

    return Number(props.proposal?.total_final_cents ?? 0);
});

watch(
    () => props.show,
    (open) => {
        if (open) {
            form.defaults({ user_id: '', notes: '' });
            form.reset();
            form.clearErrors();
        }
    },
);

const close = () => {
    emit('close');
};

const submit = () => {
    if (!props.proposal?.id) {
        return;
    }

    form.post(route('admin.comercial.propostas.comissoes-extra', props.proposal.id), {
        preserveScroll: true,
        onSuccess: () => close(),
    });
};
</script>

<template>
    <FullScreenOverlay :show="show" @close="close">
        <div class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="border-b border-slate-100 px-6 pb-4 pt-6">
                <h3 class="text-lg font-semibold text-slate-900">Incluir comissão</h3>
                <p v-if="proposal" class="mt-1 text-sm text-slate-600">
                    {{ proposal.client_name }}
                    · {{ formatBRL(previewBaseCents) }}
                </p>
                <p class="mt-2 text-sm text-slate-600">
                    Usa o percentual já cadastrado na Equipe. A comissão de quem originou a proposta permanece.
                </p>
            </div>

            <form class="space-y-4 px-6 py-4" @submit.prevent="submit">
                <div
                    v-if="Object.keys(form.errors).length"
                    class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800"
                    role="alert"
                >
                    <p v-for="(msg, key) in form.errors" :key="key">{{ msg }}</p>
                </div>

                <div v-if="!availableUsers.length" class="rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-950">
                    Não há outra pessoa com percentual de comissão na Equipe, ou todas já estão nesta proposta.
                </div>

                <div v-else>
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500" for="extra-commission-user">
                        Pessoa
                    </label>
                    <select
                        id="extra-commission-user"
                        v-model="form.user_id"
                        required
                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option value="" disabled>Selecione</option>
                        <option v-for="user in availableUsers" :key="user.id" :value="String(user.id)">
                            {{ user.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500" for="extra-commission-notes">
                        Observação (opcional)
                    </label>
                    <textarea
                        id="extra-commission-notes"
                        v-model="form.notes"
                        rows="2"
                        maxlength="500"
                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        placeholder="Ex.: seguiu o fechamento após a proposta"
                    />
                </div>

                <div class="flex justify-end gap-2 pb-2">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="close"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        class="rounded-xl bg-talents-600 px-4 py-2 text-sm font-semibold text-white hover:bg-talents-700 disabled:opacity-50"
                        :disabled="form.processing || !availableUsers.length"
                    >
                        {{ form.processing ? 'Salvando…' : 'Incluir comissão' }}
                    </button>
                </div>
            </form>
        </div>
    </FullScreenOverlay>
</template>
