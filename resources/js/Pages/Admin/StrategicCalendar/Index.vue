<script setup>
import DayModal from '@/Components/StrategicCalendar/DayModal.vue';
import StrategicCalendar from '@/Components/StrategicCalendar.vue';
import StrategicKindBadge from '@/Components/StrategicKindBadge.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    items: Object,
    monthItems: Array,
    agendaItems: Array,
    calendarYear: Number,
    calendarMonth: Number,
    filters: Object,
    companies: Array,
    kindLabels: Object,
    recurrenceLabels: { type: Object, default: () => ({}) },
    kinds: { type: Array, default: () => [] },
    recurrences: { type: Array, default: () => [] },
    maxAttachmentMb: { type: Number, default: 512 },
});

const companyFilter = ref(props.filters?.company_id ? String(props.filters.company_id) : '');
const kindFilter = ref(props.filters?.kind ?? '');
const companySearch = ref('');
const dayModalOpen = ref(false);
const dayModalIso = ref(null);

const filteredCompanies = computed(() => {
    const list = props.companies ?? [];
    const q = companySearch.value.trim().toLowerCase();
    if (!q) return list;
    return list.filter((c) => c.name.toLowerCase().includes(q));
});

const showCompanySearch = computed(() => (props.companies?.length ?? 0) > 12);

const navigateMonth = (delta) => {
    let y = props.calendarYear;
    let m = props.calendarMonth + delta;
    if (m < 1) {
        m = 12;
        y -= 1;
    } else if (m > 12) {
        m = 1;
        y += 1;
    }
    goToMonth(y, m);
};

const goToMonth = (year, month) => {
    router.get(
        route('admin.strategic-calendar.index'),
        {
            year,
            month,
            company_id: companyFilter.value || undefined,
            kind: kindFilter.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const goToday = () => {
    const t = new Date();
    router.get(
        route('admin.strategic-calendar.index'),
        {
            year: t.getFullYear(),
            month: t.getMonth() + 1,
            company_id: companyFilter.value || undefined,
            kind: kindFilter.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

watch(companyFilter, (v) => {
    router.get(
        route('admin.strategic-calendar.index'),
        {
            year: props.calendarYear,
            month: props.calendarMonth,
            company_id: v || undefined,
            kind: kindFilter.value || undefined,
        },
        { preserveState: true, replace: true },
    );
});

watch(kindFilter, (v) => {
    router.get(
        route('admin.strategic-calendar.index'),
        {
            year: props.calendarYear,
            month: props.calendarMonth,
            company_id: companyFilter.value || undefined,
            kind: v || undefined,
        },
        { preserveState: true, replace: true },
    );
});

function setKind(v) {
    kindFilter.value = v;
}

function updateRowDate(row, newDate) {
    if (!newDate || newDate === row.occurs_on) return;
    router.patch(route('admin.strategic-calendar.update-date', row.id), { occurs_on: newDate }, {
        preserveScroll: true,
        preserveState: true,
    });
}

const dayModalItems = computed(() => {
    if (!dayModalIso.value) return [];
    return (props.monthItems ?? []).filter((item) => {
        const iso = item.occurs_on?.slice?.(0, 10) ?? String(item.occurs_on);
        return iso === dayModalIso.value;
    });
});

function openDayModal(iso) {
    dayModalIso.value = iso;
    dayModalOpen.value = true;
}

function closeDayModal() {
    dayModalOpen.value = false;
}
</script>

<template>
    <Head title="Calendário estratégico" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-xl font-semibold leading-tight text-slate-900">Calendário estratégico</h2>
                <Link :href="route('admin.strategic-calendar.create')" class="btn-primary !py-2.5 text-sm">
                    Novo evento ou rito
                </Link>
            </div>
        </template>

        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-end lg:justify-between">
            <div class="flex flex-col gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo</span>
                <div class="inline-flex rounded-full border border-slate-200 bg-slate-50/90 p-0.5">
                    <button
                        type="button"
                        class="rounded-full px-3 py-1.5 text-xs font-semibold transition sm:text-sm"
                        :class="kindFilter === '' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                        @click="setKind('')"
                    >
                        Todos
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-3 py-1.5 text-xs font-semibold transition sm:text-sm"
                        :class="
                            kindFilter === 'event'
                                ? 'bg-white text-slate-900 shadow-sm'
                                : 'text-slate-600 hover:text-slate-900'
                        "
                        @click="setKind('event')"
                    >
                        Evento
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-3 py-1.5 text-xs font-semibold transition sm:text-sm"
                        :class="
                            kindFilter === 'rito'
                                ? 'bg-white text-slate-900 shadow-sm'
                                : 'text-slate-600 hover:text-slate-900'
                        "
                        @click="setKind('rito')"
                    >
                        Rito
                    </button>
                </div>
            </div>

            <div class="flex min-w-[min(100%,20rem)] flex-col gap-2 sm:max-w-md">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Empresa</label>
                <input
                    v-if="showCompanySearch"
                    v-model="companySearch"
                    type="search"
                    placeholder="Buscar empresa…"
                    class="field-input mb-1 text-sm"
                />
                <select
                    v-model="companyFilter"
                    class="field-input rounded-xl border-slate-200 bg-white text-sm text-slate-900 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                >
                    <option value="">Todas</option>
                    <option v-for="c in filteredCompanies" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                </select>
                <p v-if="showCompanySearch && !filteredCompanies.length" class="text-xs text-slate-500">
                    Nenhuma empresa encontrada.
                </p>
            </div>
        </div>

        <div class="mb-10">
            <StrategicCalendar
                :year="calendarYear"
                :month="calendarMonth"
                :items="monthItems"
                :agenda-items="agendaItems"
                :kind-labels="kindLabels"
                editable
                update-date-route="admin.strategic-calendar.update-date"
                edit-item-route="admin.strategic-calendar.edit"
                @navigate-month="navigateMonth"
                @pick-month="({ year, month }) => goToMonth(year, month)"
                @go-today="goToday"
                @edit-day="openDayModal"
            />
        </div>

        <DayModal
            :show="dayModalOpen"
            :iso="dayModalIso"
            :items="dayModalItems"
            :companies="companies"
            :kinds="kinds"
            :recurrences="recurrences"
            :kind-labels="kindLabels"
            :recurrence-labels="recurrenceLabels"
            :max-attachment-mb="maxAttachmentMb"
            @close="closeDayModal"
        />

        <div class="surface-card overflow-hidden">
            <div class="border-b border-slate-200/80 px-4 py-3 sm:px-6">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Itens cadastrados</p>
            </div>

            <ul class="divide-y divide-slate-100">
                <li
                    v-for="row in items.data"
                    :key="row.id"
                    class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6"
                >
                    <div class="min-w-0 flex-1 space-y-2">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <input
                                type="date"
                                :value="row.occurs_on || ''"
                                class="rounded-md border border-slate-200 px-2 py-1 text-sm tabular-nums text-slate-700"
                                @change="updateRowDate(row, $event.target.value)"
                            />
                            <StrategicKindBadge :kind="row.kind" :label="kindLabels[row.kind] ?? row.kind" compact />
                            <span
                                v-if="row.recurrence && recurrenceLabels?.[row.recurrence]"
                                class="text-xs font-medium text-violet-600"
                            >
                                ↻ {{ recurrenceLabels[row.recurrence] }}
                            </span>
                        </div>
                        <p class="font-medium text-slate-900">{{ row.title }}</p>
                        <p class="text-sm text-slate-500">{{ row.company?.name ?? 'Todas as empresas' }}</p>
                        <p v-if="row.attachments_count" class="text-xs text-talents-700">
                            {{ row.attachments_count === 1 ? '1 anexo' : `${row.attachments_count} anexos` }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2 sm:flex-col sm:items-end">
                        <Link
                            :href="route('admin.strategic-calendar.edit', row.id)"
                            class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-800"
                            :title="'Editar'"
                        >
                            <span class="sr-only">Editar</span>
                            <PencilSquareIcon class="h-5 w-5" aria-hidden="true" />
                        </Link>
                        <Link
                            :href="route('admin.strategic-calendar.destroy', row.id)"
                            method="delete"
                            as="button"
                            class="rounded-lg p-2 text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                            preserve-scroll
                            :title="'Excluir'"
                        >
                            <span class="sr-only">Excluir</span>
                            <TrashIcon class="h-5 w-5" aria-hidden="true" />
                        </Link>
                    </div>
                </li>
                <li v-if="!items.data?.length" class="px-4 py-10 text-center text-sm text-slate-500 sm:px-6">
                    Nenhum item encontrado.
                </li>
            </ul>

            <div v-if="items.links?.length > 3" class="border-t border-slate-100 px-4 py-3 sm:px-6">
                <div class="flex flex-wrap gap-1">
                    <template v-for="(l, i) in items.links" :key="i">
                        <Link
                            v-if="l.url"
                            :href="l.url"
                            class="rounded-full px-3 py-1.5 text-sm font-medium transition"
                            :class="l.active ? 'bg-talents-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                            preserve-scroll
                            v-html="l.label"
                        />
                        <span v-else class="rounded-full px-3 py-1.5 text-sm text-slate-400" v-html="l.label" />
                    </template>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
