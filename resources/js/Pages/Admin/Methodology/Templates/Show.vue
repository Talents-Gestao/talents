<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ template: Object });
</script>

<template>
    <Head :title="template.title" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.methodology-templates.index')"
                back-label="Templates"
                :title="template.title"
                :prefer-history="true"
            >
                <template #trailing>
                    <Link
                        :href="route('admin.methodology-templates.edit', template.id)"
                        class="font-medium text-talents-700 hover:underline"
                    >
                        Editar
                    </Link>
                </template>
            </FormPageHeader>
        </template>

        <div class="surface-card p-6 text-slate-900">
            <p v-if="template.description" class="text-sm text-gray-600">{{ template.description }}</p>
            <p class="mt-2 text-xs text-gray-500">Etapa {{ template.step_number }} · {{ template.is_active ? 'Ativo' : 'Inativo' }}</p>
        </div>

        <div v-for="(sec, si) in template.sections" :key="sec.id" class="mt-6 rounded-xl border border-talents-100 bg-white p-6 shadow-sm">
            <h3 class="font-semibold text-talents-800">{{ si + 1 }}. {{ sec.title }}</h3>
            <p v-if="sec.description" class="mt-1 text-sm text-gray-600">{{ sec.description }}</p>
            <ol class="mt-4 list-decimal space-y-3 pl-5 text-sm">
                <li v-for="q in sec.questions" :key="q.id" class="text-gray-800">
                    <span>{{ q.body }}</span>
                    <span class="ml-2 text-xs text-gray-500">
                        ({{ q.type === 'text' ? 'texto' : `escala ${q.scale_min}–${q.scale_max}` }}{{ q.is_required ? ', obrigatória' : '' }})
                    </span>
                </li>
            </ol>
        </div>

        <div class="mt-6">
            <Link :href="route('admin.methodology-templates.index')" class="text-sm font-medium text-talents-700 hover:underline">← Voltar à lista</Link>
        </div>
    </AdminLayout>
</template>
