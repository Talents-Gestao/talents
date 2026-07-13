<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DateRangeFields from '@/Components/StrategicCalendar/DateRangeFields.vue';
import CompanyAudienceMultiSelect from '@/Components/StrategicCalendar/CompanyAudienceMultiSelect.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    companies: Array,
    kinds: Array,
    recurrences: Array,
});

const form = useForm({
    title: '',
    description: '',
    kind: props.kinds[0]?.value ?? 'event',
    occurs_on: new Date().toISOString().slice(0, 10),
    ends_on: '',
    recurrence: '',
    recurrence_ends_on: '',
    company_ids: [],
});

const showRecurrenceEnd = computed(() => Boolean(form.recurrence));

watch(
    () => form.recurrence,
    (value) => {
        if (value) {
            form.ends_on = '';
        }
    },
);

watch(
    () => form.ends_on,
    (value) => {
        if (value) {
            form.recurrence = '';
            form.recurrence_ends_on = '';
        }
    },
);

const submit = () => {
    form.transform((data) => ({
        ...data,
        company_ids: data.company_ids ?? [],
        recurrence: data.recurrence || null,
        recurrence_ends_on: data.recurrence ? data.recurrence_ends_on || null : null,
        ends_on: data.recurrence ? null : data.ends_on || null,
    })).post(route('admin.strategic-calendar.store'));
};
</script>

<template>
    <Head title="Novo item — Calendário estratégico" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.strategic-calendar.index')"
                title="Novo evento ou Ritual"
            />
        </template>

        <form class="surface-card max-w-2xl space-y-4 p-6 text-slate-900" @submit.prevent="submit">
            <div>
                <InputLabel for="title" value="Nome" />
                <TextInput id="title" v-model="form.title" class="mt-1 block w-full" required />
            </div>
            <div>
                <InputLabel for="kind" value="Tipo" />
                <select
                    id="kind"
                    v-model="form.kind"
                    class="mt-1 block w-full rounded-md border border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                >
                    <option v-for="k in kinds" :key="k.value" :value="k.value">{{ k.label }}</option>
                </select>
            </div>
            <DateRangeFields
                v-model:occurs-on="form.occurs_on"
                v-model:ends-on="form.ends_on"
                :disable-ends-on="showRecurrenceEnd"
            />
            <div>
                <InputLabel for="recurrence" value="Repetição" />
                <select
                    id="recurrence"
                    v-model="form.recurrence"
                    class="mt-1 block w-full rounded-md border border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                >
                    <option value="">Não se repete</option>
                    <option v-for="r in recurrences" :key="r.value" :value="r.value">{{ r.label }}</option>
                </select>
            </div>
            <div v-if="showRecurrenceEnd">
                <InputLabel for="recurrence_ends_on" value="Repetir até (opcional)" />
                <TextInput
                    id="recurrence_ends_on"
                    v-model="form.recurrence_ends_on"
                    type="date"
                    class="mt-1 block w-full max-w-[12rem]"
                />
                <p class="mt-0.5 text-xs text-gray-500">Em branco = repete indefinidamente no calendário.</p>
            </div>
            <div>
                <InputLabel for="description" value="Como fazer (orientações)" />
                <textarea
                    id="description"
                    v-model="form.description"
                    rows="6"
                    class="mt-1 block w-full rounded-md border border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    placeholder="Descreva como esta ação pode ou deve ser realizada."
                />
            </div>
            <p class="text-xs text-gray-500">
                Os anexos podem ser adicionados após criar o item, no calendário ou na página de edição.
            </p>
            <CompanyAudienceMultiSelect v-model="form.company_ids" :companies="companies" />
            <PrimaryButton :disabled="form.processing">Salvar</PrimaryButton>
        </form>
    </AdminLayout>
</template>
