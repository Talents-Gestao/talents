<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    review: { type: Object, required: true },
});

const form = useForm({
    decision: props.review.current?.decision ?? 'documental',
    note: props.review.current?.note ?? '',
    checked_items: [...(props.review.current?.checked_items ?? [])],
});

const submit = () => {
    form.post(props.review.save_url, { preserveScroll: true });
};
</script>

<template>
    <section class="rounded-xl border border-purple-200 bg-purple-50/40 p-5 shadow-sm" aria-labelledby="internal-review-title">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="internal-review-title" class="text-base font-semibold text-talents-900">Revisão interna da Talents</h2>
            <span class="rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-semibold text-gray-700">Somente consultoria</span>
        </div>
        <p class="mt-2 text-sm text-gray-700">
            Registre apenas situações que precisem de verificação antes de emitir o relatório. Não gera contratação automática nem substitui a análise profissional.
        </p>

        <form class="mt-4 grid gap-4 lg:grid-cols-2" @submit.prevent="submit">
            <fieldset>
                <legend class="text-xs font-semibold text-gray-600">Itens de verificação</legend>
                <ul class="mt-2 space-y-2">
                    <li v-for="item in review.items" :key="item.key" class="rounded-lg border border-gray-200 bg-white p-3">
                        <label class="flex items-start gap-2 text-sm">
                            <input v-model="form.checked_items" type="checkbox" :value="item.key" class="mt-1 h-4 w-4 rounded border-gray-300" />
                            <span>
                                <span class="font-semibold text-gray-900">{{ item.title }}</span>
                                <span class="block text-xs text-gray-600">{{ item.hint }}</span>
                            </span>
                        </label>
                    </li>
                </ul>
            </fieldset>

            <div>
                <label for="triage-decision" class="text-xs font-semibold text-gray-600">Encaminhamento</label>
                <select id="triage-decision" v-model="form.decision" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    <option v-for="(label, value) in review.decisions" :key="value" :value="value">{{ label }}</option>
                </select>
                <p v-if="form.errors.decision" class="mt-1 text-xs text-red-600">{{ form.errors.decision }}</p>

                <label for="triage-note" class="mt-3 block text-xs font-semibold text-gray-600">Anotações da consultoria</label>
                <textarea
                    id="triage-note"
                    v-model="form.note"
                    rows="4"
                    class="mt-1 w-full rounded-md border-gray-300 text-sm"
                    placeholder="Registre o motivo e os próximos passos."
                />
                <p v-if="form.errors.note" class="mt-1 text-xs text-red-600">{{ form.errors.note }}</p>

                <button type="submit" class="mt-3 rounded-md bg-talents-700 px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-60" :disabled="form.processing">
                    Salvar análise interna
                </button>
                <p class="mt-2 text-xs text-gray-600" role="status">
                    <template v-if="review.current">Última análise: {{ review.current.decision_label }}, por {{ review.current.author }} em {{ review.current.at }}.</template>
                    <template v-else>Sem pendências registradas.</template>
                </p>
            </div>
        </form>

        <div v-if="review.history.length" class="mt-5 border-t border-purple-100 pt-3">
            <h3 class="text-sm font-semibold text-gray-900">Histórico</h3>
            <ol class="mt-2 space-y-2">
                <li v-for="row in review.history" :key="row.id" class="rounded-lg border border-gray-200 bg-white p-3 text-sm">
                    <p class="font-medium text-gray-900">{{ row.decision_label }}</p>
                    <p v-if="row.note" class="mt-0.5 whitespace-pre-line text-gray-700">{{ row.note }}</p>
                    <p class="mt-0.5 text-xs text-gray-500">{{ row.author }} · {{ row.at }} · {{ row.checked_items.length }} {{ row.checked_items.length === 1 ? 'item verificado' : 'itens verificados' }}</p>
                </li>
            </ol>
        </div>
    </section>
</template>
