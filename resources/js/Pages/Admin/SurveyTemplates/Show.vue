<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ template: Object });

const numberOrOne = (w) => (w != null && w !== '' ? Number(w) : 1);
</script>

<template>
    <Head :title="template.title" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.survey-templates.index')"
                back-label="Mapeamentos"
                :title="template.title"
                :prefer-history="true"
            >
                <template #trailing>
                    <Link
                        :href="route('admin.survey-templates.edit', template.id)"
                        class="font-medium text-talents-700 hover:underline"
                    >
                        Editar
                    </Link>
                </template>
            </FormPageHeader>
        </template>

        <p class="text-gray-600">{{ template.description }}</p>

        <div class="mt-6 space-y-6">
            <div
                v-for="section in template.sections"
                :key="section.id"
                class="surface-card p-6 text-slate-900"
            >
                <h3 class="font-semibold text-talents-700">{{ section.title }}</h3>
                <p v-if="section.description" class="mt-2 text-sm text-gray-600">{{ section.description }}</p>
                <ol class="mt-4 list-decimal space-y-2 pl-5 text-sm">
                    <li v-for="q in section.questions" :key="q.id">
                        {{ q.body }}
                        <span class="text-xs text-gray-500">(peso: {{ numberOrOne(q.weight) }})</span>
                        <span v-if="q.reverse_score" class="text-xs text-gray-500">(inverso)</span>
                        <span class="text-xs text-gray-500">({{ (q.response_scale || 'frequency') === 'agreement' ? 'concordância' : 'frequência' }})</span>
                    </li>
                </ol>
            </div>
        </div>
    </AdminLayout>
</template>
