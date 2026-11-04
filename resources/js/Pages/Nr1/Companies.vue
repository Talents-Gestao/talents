<script setup>
import '../../../css/nr1-shell.css';
import '../../../css/nr1-shell-header.css';
import CompanyPickerCard from '@/Components/Nr1Shell/CompanyPickerCard.vue';
import { FILTERS, countLabel, filterCompanies, splitRecent } from '@/Components/Nr1Shell/nr1CompanyPicker';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { BuildingOffice2Icon, ExclamationTriangleIcon, MagnifyingGlassIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Escolha de empresa do painel NR-1 (Talents). Os cartões chegam como prop adiada (skeleton enquanto carrega). Só
 * identificação das campanhas: nome e marca da empresa, estado, método, quantidade e período; nenhum resultado.
 */
const props = defineProps({
    companies: { type: Array, default: undefined },
    recentIds: { type: Array, default: () => [] },
});

const term = ref('');
const filter = ref('todas');
const searchInput = ref(null);
const failed = ref(false);

const filtered = computed(() => filterCompanies(props.companies ?? [], { term: term.value, filter: filter.value }));
const groups = computed(() => splitRecent(filtered.value, props.recentIds));
const total = computed(() => filtered.value.length);
const searching = computed(() => term.value.trim() !== '' || filter.value !== 'todas');

const clear = () => {
    term.value = '';
    filter.value = 'todas';
    searchInput.value?.focus();
};

// «/» foca a busca (fora de campos de texto).
const onSlash = (event) => {
    const tag = event.target?.tagName;
    if (event.key === '/' && !event.ctrlKey && !event.metaKey && !['INPUT', 'TEXTAREA', 'SELECT'].includes(tag) && !event.target?.isContentEditable) {
        event.preventDefault();
        searchInput.value?.focus();
    }
};

// Falha ao carregar os cartões adiados: estado de erro com «Tentar de novo» (em vez da tela de erro padrão).
const offInvalid = router.on('invalid', (event) => {
    if (props.companies === undefined) {
        event.preventDefault();
        failed.value = true;
    }
});
const offException = router.on('exception', (event) => {
    if (props.companies === undefined) {
        event.preventDefault();
        failed.value = true;
    }
});
const retry = () => {
    failed.value = false;
    router.reload({ only: ['companies'] });
};

onMounted(() => document.addEventListener('keydown', onSlash));
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onSlash);
    offInvalid();
    offException();
});
</script>

<template>
    <Head title="Escolha a empresa · NR-1" />

    <AdminLayout>
        <div class="mx-auto max-w-6xl">
            <!-- Faixa de destaque no estilo do banner do painel -->
            <section
                class="relative overflow-hidden rounded-3xl px-6 py-7 text-white shadow-sm sm:px-8 sm:py-8"
                style="background: linear-gradient(115deg, #51256b, #7d3b94 62%, #9c599f)"
                aria-labelledby="nr1-picker-title"
            >
                <span class="pointer-events-none absolute -right-10 -top-16 h-56 w-56 rounded-full bg-white/[0.07]" aria-hidden="true" />
                <span class="pointer-events-none absolute -bottom-20 right-24 h-44 w-44 rounded-full bg-white/[0.06]" aria-hidden="true" />
                <span class="pointer-events-none absolute right-10 top-8 hidden h-16 w-16 rotate-45 rounded-2xl border border-white/15 sm:block" aria-hidden="true" />
                <p class="relative text-[11px] font-bold uppercase tracking-[0.16em] text-[#ecd6f5]">Inteligência de pessoas · NR-1</p>
                <h1 id="nr1-picker-title" class="relative mt-2 text-2xl font-extrabold tracking-tight sm:text-[28px]">NR-1 · Escolha a empresa</h1>
                <p class="relative mt-1.5 max-w-xl text-sm text-white/90">Abra o painel na campanha mais recente ou escolha outra.</p>
            </section>

            <!-- Busca e filtros -->
            <div class="mt-6 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="relative w-full lg:max-w-md">
                    <label for="nr1-company-search" class="sr-only">Buscar empresa pelo nome</label>
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                    <input
                        id="nr1-company-search"
                        ref="searchInput"
                        v-model="term"
                        type="search"
                        autocomplete="off"
                        placeholder="Buscar empresa pelo nome"
                        aria-describedby="nr1-company-count nr1-search-hint"
                        class="h-12 w-full rounded-2xl border border-slate-200 bg-white pl-11 pr-12 text-[15px] text-slate-900 shadow-sm placeholder:text-slate-500 focus:border-primary-border focus:outline-none focus:ring-2 focus:ring-primary-ring"
                    />
                    <kbd id="nr1-search-hint" class="pointer-events-none absolute right-3.5 top-1/2 hidden -translate-y-1/2 rounded-md border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[11px] font-semibold text-slate-500 sm:block" aria-label="Atalho: barra para buscar">/</kbd>
                </div>
                <p id="nr1-company-count" class="text-sm font-medium text-slate-600" aria-live="polite" aria-atomic="true">
                    <template v-if="companies !== undefined">{{ countLabel(total) }}</template>
                </p>
            </div>

            <div class="mt-3 flex flex-wrap gap-2" role="group" aria-label="Filtrar empresas">
                <button
                    v-for="item in FILTERS"
                    :key="item.key"
                    type="button"
                    class="rounded-full border px-3.5 py-1.5 text-[13px] font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-ring focus-visible:ring-offset-1 motion-reduce:transition-none"
                    :class="filter === item.key ? 'border-primary bg-primary text-primary-on' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'"
                    :aria-pressed="filter === item.key"
                    @click="filter = item.key"
                >
                    {{ item.label }}
                </button>
            </div>

            <Deferred data="companies">
                <template #fallback>
                    <div v-if="failed" class="mt-8 flex flex-col items-center rounded-3xl border border-rose-200 bg-rose-50/60 px-6 py-10 text-center" role="alert">
                        <ExclamationTriangleIcon class="h-8 w-8 text-rose-600" aria-hidden="true" />
                        <p class="mt-3 font-semibold text-slate-900">Não foi possível carregar as empresas.</p>
                        <p class="mt-1 text-sm text-slate-600">Verifique a conexão e tente de novo.</p>
                        <button type="button" class="mt-4 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-on hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-ring focus-visible:ring-offset-2" @click="retry">Tentar de novo</button>
                    </div>
                    <div v-else class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-busy="true" aria-label="Carregando empresas">
                        <div v-for="n in 6" :key="n" class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm">
                            <div class="flex items-center gap-3.5">
                                <span class="h-12 w-12 animate-pulse rounded-2xl bg-slate-100 motion-reduce:animate-none" />
                                <span class="flex-1 space-y-2">
                                    <span class="block h-3.5 w-3/4 animate-pulse rounded bg-slate-100 motion-reduce:animate-none" />
                                    <span class="block h-3 w-1/3 animate-pulse rounded bg-slate-100 motion-reduce:animate-none" />
                                </span>
                            </div>
                            <span class="mt-5 block h-6 w-1/2 animate-pulse rounded-full bg-slate-100 motion-reduce:animate-none" />
                            <span class="mt-4 block h-3 w-2/3 animate-pulse rounded bg-slate-100 motion-reduce:animate-none" />
                        </div>
                    </div>
                </template>

                <!-- Vazio: nenhuma empresa com campanha NR-1 -->
                <div v-if="!companies?.length" class="mt-8 flex flex-col items-center rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                    <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-[#f3eaf9] text-[#7a3f94]" aria-hidden="true">
                        <BuildingOffice2Icon class="h-7 w-7" />
                    </span>
                    <p class="mt-4 font-semibold text-slate-900">Nenhuma empresa tem campanha NR-1 ainda.</p>
                    <p class="mt-1 max-w-md text-sm text-slate-600">Crie a primeira campanha na página da empresa, em «Pesquisas e parecer». Ela aparece aqui assim que for criada.</p>
                    <Link :href="route('admin.companies.index')" class="mt-4 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-ring">Ir para Clientes</Link>
                </div>

                <!-- Sem resultado na busca -->
                <div v-else-if="!total" class="mt-8 flex flex-col items-center rounded-3xl border border-slate-200/80 bg-white px-6 py-10 text-center">
                    <MagnifyingGlassIcon class="h-8 w-8 text-slate-400" aria-hidden="true" />
                    <p class="mt-3 font-semibold text-slate-900">Nenhuma empresa encontrada<template v-if="term.trim()"> para «{{ term.trim() }}»</template>.</p>
                    <p class="mt-1 text-sm text-slate-600">Confira o nome ou limpe a busca e os filtros.</p>
                    <button type="button" class="mt-4 inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-ring" @click="clear">
                        <XMarkIcon class="h-4 w-4" aria-hidden="true" />
                        Limpar busca e filtros
                    </button>
                </div>

                <template v-else>
                    <section v-if="groups.recent.length" class="mt-8" aria-labelledby="nr1-recent-title">
                        <h2 id="nr1-recent-title" class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Abertas recentemente</h2>
                        <ul class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <li v-for="company in groups.recent" :key="company.id">
                                <CompanyPickerCard :company="company" :term="term" />
                            </li>
                        </ul>
                    </section>

                    <section v-if="groups.rest.length" class="mt-8" aria-labelledby="nr1-all-title">
                        <h2 id="nr1-all-title" class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
                            {{ groups.recent.length ? 'Outras empresas' : searching ? 'Resultado' : 'Todas as empresas' }}
                        </h2>
                        <ul class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <li v-for="company in groups.rest" :key="company.id">
                                <CompanyPickerCard :company="company" :term="term" />
                            </li>
                        </ul>
                    </section>
                </template>
            </Deferred>
        </div>
    </AdminLayout>
</template>
