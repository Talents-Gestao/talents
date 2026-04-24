<script setup>
const props = defineProps({
    permissionModules: { type: Array, default: () => [] },
    permissionActions: { type: Array, default: () => [] },
    modelValue: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const has = (mod, act) => props.modelValue.some((p) => p.module === mod && p.action === act);

const toggle = (mod, act) => {
    if (props.disabled) {
        return;
    }
    const next = [...props.modelValue];
    const i = next.findIndex((p) => p.module === mod && p.action === act);
    if (i >= 0) {
        next.splice(i, 1);
    } else {
        next.push({ module: mod, action: act });
    }
    emit('update:modelValue', next);
};
</script>

<template>
    <div v-if="!permissionModules.length" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        Nenhum módulo disponível no plano desta empresa para atribuir permissões.
    </div>
    <div v-else class="overflow-x-auto rounded-lg border border-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold text-slate-700">Módulo</th>
                    <th
                        v-for="a in permissionActions"
                        :key="a.value"
                        class="px-2 py-2 text-center font-semibold text-slate-700"
                    >
                        {{ a.label }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                <tr v-for="m in permissionModules" :key="m.value">
                    <td class="whitespace-nowrap px-3 py-2 font-medium text-slate-800">{{ m.label }}</td>
                    <td v-for="a in permissionActions" :key="m.value + '-' + a.value" class="px-2 py-2 text-center">
                        <input
                            type="checkbox"
                            class="h-4 w-4 rounded border-slate-300 text-talents-700 focus:ring-talents-500"
                            :checked="has(m.value, a.value)"
                            :disabled="disabled"
                            @change="toggle(m.value, a.value)"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
