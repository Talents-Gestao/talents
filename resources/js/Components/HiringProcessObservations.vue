<script setup>
import { useForm } from '@inertiajs/vue3';
import { ChatBubbleLeftRightIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

const props = defineProps({
    processId: { type: Number, required: true },
    comments: { type: Array, default: () => [] },
    storeUrl: { type: String, required: true },
    defaultOpen: { type: Boolean, default: false },
});

const panel = ref(null);
const form = useForm({ body: '' });

const countLabel = computed(() => {
    const n = props.comments.length;
    if (n === 0) {
        return 'Mensagens (nenhuma)';
    }
    return n === 1 ? 'Mensagens · 1' : `Mensagens · ${n}`;
});

const formatDate = (iso) => {
    if (!iso) {
        return '';
    }
    try {
        return new Date(iso).toLocaleString('pt-BR', {
            dateStyle: 'short',
            timeStyle: 'short',
        });
    } catch {
        return '';
    }
};

const roleLabel = (role) => (role === 'talents' ? 'Talents' : 'Cliente');

const submit = () => {
    form.post(props.storeUrl, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('body');
            if (panel.value) {
                panel.value.open = true;
            }
        },
    });
};
</script>

<template>
    <details
        ref="panel"
        class="group mt-4 overflow-hidden rounded-xl border border-slate-200/90 bg-white open:border-talents-200 open:shadow-sm"
        :open="defaultOpen || undefined"
    >
        <summary
            class="flex cursor-pointer list-none items-center justify-between gap-3 px-3.5 py-3 transition hover:bg-talents-50/50 marker:content-none [&::-webkit-details-marker]:hidden"
        >
            <span class="inline-flex min-w-0 items-center gap-2 text-sm font-semibold text-talents-700">
                <ChatBubbleLeftRightIcon class="h-4 w-4 shrink-0" aria-hidden="true" />
                <span class="truncate">{{ countLabel }}</span>
            </span>
            <ChevronDownIcon
                class="h-4 w-4 shrink-0 text-talents-500 transition-transform duration-200 group-open:rotate-180"
                aria-hidden="true"
            />
        </summary>

        <div class="space-y-3 border-t border-slate-100 px-3.5 py-3">
            <p class="text-xs text-slate-500">
                Comentários livres do processo (não substituem a ficha por etapa nem o histórico acima).
            </p>
            <ul v-if="comments.length" class="max-h-56 space-y-2 overflow-y-auto pr-1">
                <li
                    v-for="c in comments"
                    :key="c.id"
                    class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2.5"
                >
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-slate-500">
                        <span class="font-semibold text-slate-700">{{ c.author_name }}</span>
                        <span
                            class="rounded-full px-1.5 py-0.5 text-[0.65rem] font-bold uppercase tracking-wide"
                            :class="
                                c.author_role === 'talents'
                                    ? 'bg-talents-100 text-talents-800'
                                    : 'bg-slate-200/80 text-slate-700'
                            "
                        >
                            {{ roleLabel(c.author_role) }}
                        </span>
                        <span>{{ formatDate(c.created_at) }}</span>
                    </div>
                    <p class="mt-1 whitespace-pre-wrap text-sm text-slate-800">{{ c.body }}</p>
                </li>
            </ul>
            <p v-else class="text-sm text-slate-500">Ainda não há mensagens publicadas.</p>

            <form class="space-y-2" @submit.prevent="submit">
                <label class="sr-only" :for="'obs-' + processId">Nova mensagem</label>
                <textarea
                    :id="'obs-' + processId"
                    v-model="form.body"
                    rows="2"
                    required
                    maxlength="2000"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70"
                    placeholder="Adicionar mensagem…"
                />
                <p v-if="form.errors.body" class="text-sm text-red-600">{{ form.errors.body }}</p>
                <button
                    type="submit"
                    class="inline-flex items-center rounded-xl bg-talents-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-talents-700 disabled:opacity-50"
                    :disabled="form.processing || !form.body.trim()"
                >
                    Publicar mensagem
                </button>
            </form>
        </div>
    </details>
</template>
