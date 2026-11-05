<script setup>
import { useAdminPermissions } from '@/composables/useAdminPermissions';
import {
    BuildingOffice2Icon,
    CalendarDaysIcon,
    ChartBarIcon,
    ClipboardDocumentListIcon,
    CreditCardIcon,
    DocumentTextIcon,
    ExclamationTriangleIcon,
    FingerPrintIcon,
    MapIcon,
    PencilSquareIcon,
    SparklesIcon,
    StarIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    shortcuts: { type: Array, default: () => [] },
});

const emit = defineEmits(['open-section']);

const { canAdmin } = useAdminPermissions();

const iconByKey = {
    edit: PencilSquareIcon,
    users: UserGroupIcon,
    nr1: ClipboardDocumentListIcon,
    ponto: FingerPrintIcon,
    colaboradores: UserGroupIcon,
    regulamento: DocumentTextIcon,
    mapa: SparklesIcon,
    avaliacoes: StarIcon,
    ferias: CalendarDaysIcon,
    destaques: StarIcon,
    denuncias: ExclamationTriangleIcon,
    dados: BuildingOffice2Icon,
    assinaturas: CreditCardIcon,
    direcionamento: ChartBarIcon,
    mapeamentos: MapIcon,
    pesquisas: ClipboardDocumentListIcon,
    mapa_proposito: SparklesIcon,
    usuarios_gestao: UserGroupIcon,
    excluir: ExclamationTriangleIcon,
};

const visibleShortcuts = computed(() =>
    (props.shortcuts || []).filter((item) => {
        if (!item?.enabled) {
            return false;
        }
        if (!item.permission) {
            return true;
        }
        return canAdmin(item.permission);
    }),
);

const isSectionShortcut = (item) => Boolean(item?.section);

const onSectionClick = (item) => {
    emit('open-section', item.section);
};
</script>

<template>
    <section v-if="visibleShortcuts.length" aria-labelledby="company-shortcuts-heading">
        <h3 id="company-shortcuts-heading" class="text-sm font-semibold text-slate-900">Atalhos</h3>
        <p class="mt-1 text-sm text-slate-500">
            Acesso rápido às rotinas e à gestão desta empresa.
        </p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <template v-for="item in visibleShortcuts" :key="item.key">
                <button
                    v-if="isSectionShortcut(item)"
                    type="button"
                    class="group flex items-center gap-3 rounded-2xl border px-4 py-3.5 text-left shadow-sm transition"
                    :class="
                        item.tone === 'danger'
                            ? 'border-red-200 bg-red-50/50 hover:border-red-300 hover:bg-red-50'
                            : 'border-slate-200 bg-white hover:border-talents-300 hover:bg-talents-50/40 hover:shadow'
                    "
                    @click="onSectionClick(item)"
                >
                    <span
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl transition"
                        :class="
                            item.tone === 'danger'
                                ? 'bg-red-100 text-red-700'
                                : 'bg-slate-100 text-slate-700 group-hover:bg-talents-100 group-hover:text-talents-800'
                        "
                    >
                        <component
                            :is="iconByKey[item.key] || BuildingOffice2Icon"
                            class="h-5 w-5"
                            aria-hidden="true"
                        />
                    </span>
                    <span class="min-w-0">
                        <span
                            class="block text-sm font-semibold"
                            :class="item.tone === 'danger' ? 'text-red-900' : 'text-slate-900'"
                        >
                            {{ item.label }}
                        </span>
                        <span
                            class="mt-0.5 block text-xs"
                            :class="item.tone === 'danger' ? 'text-red-800/80' : 'text-slate-500 group-hover:text-talents-800'"
                        >
                            {{ item.hint || 'Escolher destino' }}
                        </span>
                    </span>
                </button>
                <Link
                    v-else
                    :href="item.href"
                    class="group flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-left shadow-sm transition hover:border-talents-300 hover:bg-talents-50/40 hover:shadow"
                >
                    <span
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700 transition group-hover:bg-talents-100 group-hover:text-talents-800"
                    >
                        <component
                            :is="iconByKey[item.key] || BuildingOffice2Icon"
                            class="h-5 w-5"
                            aria-hidden="true"
                        />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-slate-900">{{ item.label }}</span>
                        <span class="mt-0.5 block text-xs text-slate-500 group-hover:text-talents-800">
                            {{ item.hint || 'Abrir' }}
                        </span>
                    </span>
                </Link>
            </template>
        </div>
    </section>
</template>
