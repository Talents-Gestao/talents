<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import { computed } from 'vue';

const props = defineProps({
    companies: { type: Array, default: () => [] },
    modelValue: { type: Array, default: () => [] },
    compact: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const isUniversal = computed(() => props.modelValue.length === 0);

const selectedIds = computed(() => new Set(props.modelValue.map((id) => String(id))));

const summary = computed(() => {
    if (isUniversal.value) {
        return 'Universal — todas as empresas';
    }

    const names = props.companies
        .filter((company) => selectedIds.value.has(String(company.id)))
        .map((company) => company.name);

    if (names.length === 0) {
        return `${props.modelValue.length} empresa(s) selecionada(s)`;
    }

    return names.join(', ');
});

function selectUniversal(checked) {
    if (checked) {
        emit('update:modelValue', []);
    }
}

function toggleCompany(companyId, checked) {
    const id = Number(companyId);

    if (checked) {
        const next = isUniversal.value
            ? [id]
            : [...new Set([...props.modelValue.map(Number), id])].sort((a, b) => a - b);
        emit('update:modelValue', next);

        return;
    }

    const next = props.modelValue.map(Number).filter((value) => value !== id);
    emit('update:modelValue', next);
}
</script>

<template>
    <div>
        <InputLabel value="Empresas" />
        <p class="mt-0.5 text-xs text-gray-500">
            Selecione uma ou mais empresas, ou marque <strong class="font-medium text-slate-700">Todas (universal)</strong>
            para o evento aparecer em todo o portfólio.
        </p>

        <div
            class="mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
            :class="compact ? 'text-sm' : ''"
            role="listbox"
            aria-multiselectable="true"
            aria-label="Seleção de empresas"
        >
            <div class="max-h-56 overflow-y-auto p-2">
                <label
                    class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 transition"
                    :class="isUniversal ? 'bg-talents-50 ring-1 ring-talents-200' : 'hover:bg-slate-50'"
                >
                    <input
                        type="checkbox"
                        class="rounded border-slate-300 text-talents-600 focus:ring-talents-500"
                        :checked="isUniversal"
                        @change="selectUniversal($event.target.checked)"
                    />
                    <span class="min-w-0">
                        <span class="block font-medium text-slate-900">Todas as empresas (universal)</span>
                        <span class="block text-xs text-slate-500">Visível para qualquer cliente com calendário estratégico.</span>
                    </span>
                </label>

                <div class="my-2 border-t border-slate-100" role="separator" />

                <label
                    v-for="company in companies"
                    :key="company.id"
                    class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 transition"
                    :class="[
                        selectedIds.has(String(company.id)) ? 'bg-slate-50' : 'hover:bg-slate-50',
                    ]"
                >
                    <input
                        type="checkbox"
                        class="rounded border-slate-300 text-talents-600 focus:ring-talents-500"
                        :checked="selectedIds.has(String(company.id))"
                        @change="toggleCompany(company.id, $event.target.checked)"
                    />
                    <span class="text-sm text-slate-800">{{ company.name }}</span>
                </label>

                <p v-if="!companies.length" class="px-2.5 py-2 text-sm text-slate-500">
                    Nenhuma empresa cadastrada.
                </p>
            </div>
        </div>

        <p class="mt-2 text-xs text-slate-600">
            Seleção atual: <span class="font-medium text-slate-800">{{ summary }}</span>
        </p>
    </div>
</template>
