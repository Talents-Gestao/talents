<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    survey: Object,
    plan: { type: Object, default: null },
    actionPlanLocked: { type: Boolean, default: true },
});

const patchItem = (item) => {
    router.patch(
        route('client.action-plan-items.update', item.id),
        {
            responsible_name: item.responsible_name,
            due_date: item.due_date || null,
            status: item.status,
        },
        { preserveScroll: true }
    );
};
</script>

<template>
    <Head title="Plano de ação" />

    <ClientLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-talents-900">Plano de ação</h2>
                <div class="flex gap-2">
                    <a
                        v-if="!actionPlanLocked"
                        :href="route('client.surveys.reports.action-plan', survey.id)"
                        class="rounded-md border border-talents-300 px-3 py-1 text-sm font-semibold text-talents-900"
                        target="_blank"
                    >
                        PDF do plano
                    </a>
                    <Link :href="route('client.surveys.results', survey.id)" class="text-sm text-talents-700 hover:underline">Resultados</Link>
                    <Link :href="route('client.surveys.show', survey.id)" class="text-sm text-talents-700 hover:underline">Voltar</Link>
                </div>
            </div>
        </template>

        <div
            v-if="actionPlanLocked"
            class="relative overflow-hidden rounded-2xl border-2 border-talents-200 bg-gradient-to-br from-talents-50/90 via-white to-amber-50/40 p-8 shadow-md"
        >
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-talents-100/30 via-transparent to-transparent" />
            <div class="relative mx-auto max-w-lg text-center">
                <div
                    class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border border-talents-200 bg-white/90 text-talents-800 shadow-sm"
                    aria-hidden="true"
                >
                    <svg class="h-9 w-9" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.75"
                            d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"
                        />
                    </svg>
                </div>
                <h3 class="mt-6 text-lg font-semibold text-talents-900">Plano de ação disponível com a Talents</h3>
                <p class="mt-3 text-sm leading-relaxed text-gray-600">
                    O plano de ação personalizado para esta pesquisa é elaborado pela equipe Talents e liberado aqui quando estiver pronto.
                    Entre em contato para contratar ou fazer upgrade do plano.
                </p>
                <a
                    href="https://wa.me/5511952512752"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-6 inline-flex items-center justify-center gap-2 rounded-full bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                >
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path
                            d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.123 1.035 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"
                        />
                    </svg>
                    Falar com a Talents
                </a>
            </div>
        </div>

        <div v-else class="space-y-6">
            <div
                v-if="plan.technical_opinion || plan.technical_opinion_file_url"
                class="surface-card overflow-hidden p-6"
            >
                <h3 class="text-lg font-semibold text-talents-900">Parecer técnico</h3>
                <p class="mt-1 text-xs text-gray-500">Elaborado pela equipe Talents com base nos resultados desta pesquisa.</p>
                <div
                    v-if="plan.technical_opinion"
                    class="parecer-prose prose prose-sm mt-4 max-w-none text-gray-800 prose-headings:text-talents-900 prose-strong:text-talents-900"
                    v-html="plan.technical_opinion"
                />
                <a
                    v-if="plan.technical_opinion_file_url"
                    :href="plan.technical_opinion_file_url"
                    class="mt-4 inline-flex items-center gap-2 rounded-lg border border-talents-200 bg-talents-50 px-4 py-2.5 text-sm font-medium text-talents-800 transition hover:bg-talents-100"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.75"
                            d="M19.5 14.25v2.625a2.625 2.625 0 01-2.625 2.625H7.125A2.625 2.625 0 014.5 16.875V14.25M12 3v12m0 0l-3.75-3.75M12 15l3.75-3.75"
                        />
                    </svg>
                    Baixar parecer técnico
                    <span v-if="plan.technical_opinion_file_name" class="text-xs font-normal text-talents-600">
                        ({{ plan.technical_opinion_file_name }})
                    </span>
                </a>
            </div>

            <div
                v-for="item in plan.items"
                :key="item.id"
                class="surface-card p-6"
            >
                <h3 class="font-semibold text-talents-900">{{ item.title }}</h3>
                <p class="mt-2 text-sm text-gray-600">{{ item.description }}</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="text-xs text-gray-500">Responsável</label>
                        <input
                            v-model="item.responsible_name"
                            class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1 text-sm"
                            @change="patchItem(item)"
                        />
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Prazo</label>
                        <input
                            v-model="item.due_date"
                            type="date"
                            class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1 text-sm"
                            @change="patchItem(item)"
                        />
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Status</label>
                        <select
                            v-model="item.status"
                            class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1 text-sm"
                            @change="patchItem(item)"
                        >
                            <option value="pending">Pendente</option>
                            <option value="in_progress">Em andamento</option>
                            <option value="done">Concluído</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </ClientLayout>
</template>

<style scoped>
.parecer-prose :deep(h2) {
    margin-top: 1.25em;
    margin-bottom: 0.5em;
    font-weight: 700;
}
.parecer-prose :deep(h2:first-child) {
    margin-top: 0;
}
.parecer-prose :deep(h3) {
    margin-top: 1em;
    margin-bottom: 0.4em;
    font-weight: 700;
}
.parecer-prose :deep(p + p) {
    margin-top: 0.75em;
}
.parecer-prose :deep(ul),
.parecer-prose :deep(ol) {
    margin-top: 0.5em;
    margin-bottom: 0.5em;
}
</style>
