<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    boards: Array,
});

const search = ref('');

const filteredBoards = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return props.boards || [];

    return (props.boards || []).filter((board) =>
        String(board.name || '')
            .toLowerCase()
            .includes(term),
    );
});
</script>

<template>
    <Head title="Tarefas" />

    <ClientLayout>
        <template #header>
            <div class="flex w-full items-center justify-between gap-3">
                <h2 class="text-xl font-semibold text-gray-900">Tarefas</h2>
                <input
                    v-model="search"
                    type="search"
                    placeholder="Pesquisar quadro..."
                    class="w-full max-w-xs rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                />
            </div>
        </template>

        <div class="space-y-4 p-4">
            <section
                class="rounded-2xl bg-gradient-to-r from-slate-900 via-violet-900 to-slate-900 px-5 py-6 text-white shadow-sm"
            >
                <p class="text-sm font-medium text-white/80">Módulo de processos e tarefas</p>
                <h3 class="mt-1 text-2xl font-semibold">Seus quadros</h3>
                <p class="mt-2 text-sm text-white/80">
                    Acompanhe processos em colunas, mova cartões e visualize o andamento em tempo real.
                </p>
            </section>

            <section class="surface-card p-4">
                <p class="mb-3 text-sm text-slate-600">Quadros partilhados consigo pela Talents.</p>
                <ul class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <li v-for="b in filteredBoards" :key="b.id">
                        <Link
                            :href="route('client.tarefas.show', b.id)"
                            class="group block rounded-xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-talents-300 hover:shadow-md"
                        >
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Quadro
                            </p>
                            <p class="mt-1 line-clamp-2 text-base font-semibold text-slate-900">
                                {{ b.name }}
                            </p>
                            <p class="mt-3 text-xs font-medium text-talents-700 group-hover:underline">
                                Abrir quadro
                            </p>
                        </Link>
                    </li>
                </ul>

                <p v-if="!filteredBoards?.length" class="mt-2 text-sm text-slate-500">
                    Nenhum quadro encontrado.
                </p>
            </section>
        </div>
    </ClientLayout>
</template>
