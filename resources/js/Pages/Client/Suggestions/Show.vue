<script setup>
import SuggestionsLayout from '@/Components/Suggestions/SuggestionsLayout.vue';
import { suggestionRoute } from '@/composables/useSuggestionRoutes';
import { formatDateTime } from '@/utils/dateOnly';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    suggestion: Object,
    statuses: { type: Object, default: () => ({}) },
    isAdminContext: { type: Boolean, default: false },
});

const form = useForm({
    status: props.suggestion.status,
});

const saveStatus = () => {
    form.patch(suggestionRoute('status', props.suggestion.id));
};
</script>

<template>
    <Head title="Mensagem do canal" />

    <SuggestionsLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <Link :href="suggestionRoute('index')" class="text-sm font-medium text-talents-700 hover:underline">
                    Voltar para sugestões e dúvidas
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-talents-900">{{ suggestion.topic_label }}</h2>
                <p class="text-sm text-slate-500">{{ formatDateTime(suggestion.created_at) }}</p>
            </div>
        </template>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <article class="surface-card space-y-5 p-6">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Área</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ suggestion.area_label }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Retorno</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ suggestion.response_label }}</dd>
                    </div>
                    <div v-if="suggestion.reporter_name">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ suggestion.reporter_name }}</dd>
                    </div>
                    <div v-if="suggestion.contact">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contato para retorno</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ suggestion.contact }}</dd>
                    </div>
                </dl>

                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mensagem</h3>
                    <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-800">{{ suggestion.message }}</p>
                </div>
            </article>

            <form class="surface-card h-fit space-y-4 p-5" @submit.prevent="saveStatus">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="suggestion-status">
                        Status
                    </label>
                    <select
                        id="suggestion-status"
                        v-model="form.status"
                        class="mt-1 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <p v-if="form.errors.status" class="mt-1 text-sm text-red-600">{{ form.errors.status }}</p>
                </div>
                <button
                    type="submit"
                    class="w-full rounded-xl bg-talents-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-talents-800 disabled:opacity-50"
                    :disabled="form.processing"
                >
                    Salvar status
                </button>
                <p class="text-xs text-slate-500">
                    {{ isAdminContext ? 'Alteração registrada neste painel.' : 'Quem enviou não recebe aviso automático.' }}
                </p>
            </form>
        </div>
    </SuggestionsLayout>
</template>
