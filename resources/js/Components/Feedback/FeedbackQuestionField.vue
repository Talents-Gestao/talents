<script setup>
import { feedbackFieldClass } from '@/utils/feedbackStatus';

const props = defineProps({
    question: { type: Object, required: true },
    modelValue: { type: [String, Array, Object], default: '' },
});

const emit = defineEmits(['update:modelValue']);

const update = (value) => emit('update:modelValue', value);

const bulletItems = () => {
    if (Array.isArray(props.modelValue)) return props.modelValue;
    return [''];
};

const setBullet = (index, value) => {
    const items = [...bulletItems()];
    items[index] = value;
    update(items.filter((_, i) => i < items.length - 1 || value !== ''));
};

const addBullet = () => update([...bulletItems(), '']);

const ccmValue = (key) => {
    const v = props.modelValue;
    return v && typeof v === 'object' ? v[key] ?? '' : '';
};

const setCcm = (key, value) => {
    update({ ...(typeof props.modelValue === 'object' ? props.modelValue : {}), [key]: value });
};

const ccmLabels = {
    start: { title: 'Começar', tone: 'border-l-emerald-400 bg-emerald-50/50' },
    continue: { title: 'Continuar', tone: 'border-l-sky-400 bg-sky-50/50' },
    improve: { title: 'Melhorar', tone: 'border-l-amber-400 bg-amber-50/50' },
    stop: { title: 'Cessar', tone: 'border-l-rose-400 bg-rose-50/50' },
};

const actionRows = () => {
    if (Array.isArray(props.modelValue) && props.modelValue.length) return props.modelValue;
    return [{ action: '', responsible: '', deadline: '' }];
};

const setActionRow = (index, field, value) => {
    const rows = actionRows().map((r) => ({ ...r }));
    rows[index] = { ...rows[index], [field]: value };
    update(rows);
};

const addActionRow = () => update([...actionRows(), { action: '', responsible: '', deadline: '' }]);
</script>

<template>
    <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-4">
        <label class="block text-sm font-medium text-slate-800">{{ question.body }}</label>

        <input
            v-if="question.question_type === 'text'"
            type="text"
            :class="feedbackFieldClass"
            :value="modelValue"
            @input="update($event.target.value)"
        />

        <textarea
            v-else-if="question.question_type === 'textarea'"
            rows="4"
            :class="feedbackFieldClass"
            :value="modelValue"
            @input="update($event.target.value)"
        />

        <div v-else-if="question.question_type === 'single_choice'" class="mt-3 space-y-2">
            <label
                v-for="opt in question.options || []"
                :key="opt.value"
                class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm transition hover:border-talents-200"
                :class="modelValue === opt.value ? 'border-talents-300 ring-2 ring-talents-100' : ''"
            >
                <input
                    type="radio"
                    class="text-talents-700 focus:ring-talents-400"
                    :name="'q-' + question.id"
                    :value="opt.value"
                    :checked="modelValue === opt.value"
                    @change="update(opt.value)"
                />
                {{ opt.label }}
            </label>
        </div>

        <div v-else-if="question.question_type === 'bullet_list'" class="mt-3 space-y-2">
            <input
                v-for="(item, idx) in bulletItems()"
                :key="idx"
                type="text"
                :class="feedbackFieldClass"
                :value="item"
                placeholder="Descreva um ponto"
                @input="setBullet(idx, $event.target.value)"
            />
            <button type="button" class="text-sm font-medium text-talents-700 hover:underline" @click="addBullet">
                + Adicionar item
            </button>
        </div>

        <div v-else-if="question.question_type === 'ccm_block'" class="mt-3 grid gap-3 md:grid-cols-2">
            <div
                v-for="key in ['start', 'continue', 'improve', 'stop']"
                :key="key"
                class="rounded-xl border-l-4 p-3"
                :class="ccmLabels[key].tone"
            >
                <label class="text-xs font-bold uppercase tracking-wide text-slate-600">{{ ccmLabels[key].title }}</label>
                <textarea
                    rows="3"
                    :class="feedbackFieldClass + ' mt-2 bg-white'"
                    :value="ccmValue(key)"
                    @input="setCcm(key, $event.target.value)"
                />
            </div>
        </div>

        <div v-else-if="question.question_type === 'action_table'" class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="p-3">Ação</th>
                        <th class="p-3">Responsável</th>
                        <th class="p-3">Prazo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, idx) in actionRows()" :key="idx" class="border-t border-slate-100">
                        <td class="p-2">
                            <input :class="feedbackFieldClass + ' mt-0'" :value="row.action" @input="setActionRow(idx, 'action', $event.target.value)" />
                        </td>
                        <td class="p-2">
                            <input :class="feedbackFieldClass + ' mt-0'" :value="row.responsible" @input="setActionRow(idx, 'responsible', $event.target.value)" />
                        </td>
                        <td class="p-2">
                            <input :class="feedbackFieldClass + ' mt-0'" :value="row.deadline" @input="setActionRow(idx, 'deadline', $event.target.value)" />
                        </td>
                    </tr>
                </tbody>
            </table>
            <button type="button" class="px-3 py-2 text-sm font-medium text-talents-700 hover:underline" @click="addActionRow">
                + Adicionar linha
            </button>
        </div>
    </div>
</template>
