<script setup>
import MethodologyStepper from '@/Components/MethodologyStepper.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    stats: Object,
    recentTemplates: Array,
});

const selectedStep = ref(2);
</script>

<template>
    <Head title="Metodologia Talents" />

    <AdminLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-talents-900">Metodologia Talents</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Templates editáveis e empresas cujo <strong>plano</strong> inclui o módulo Metodologia Talents.
                </p>
            </div>
        </template>

        <div class="rounded-2xl border border-talents-200 bg-gradient-to-b from-talents-50/80 to-white p-6 shadow-sm sm:p-8">
            <MethodologyStepper v-model="selectedStep" />
            <div class="mt-10 border-t border-talents-100 pt-8">
                <div v-if="selectedStep === 2" class="space-y-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-talents-200 bg-white p-5 shadow-sm">
                            <p class="text-sm text-gray-500">Empresas com plano + módulo Metodologia</p>
                            <p class="mt-1 text-3xl font-bold text-talents-700">{{ stats.companies_with_methodology }}</p>
                        </div>
                        <div class="rounded-xl border border-talents-200 bg-white p-5 shadow-sm">
                            <p class="text-sm text-gray-500">Templates de formulário</p>
                            <p class="mt-1 text-3xl font-bold text-talents-700">{{ stats.templates_count }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <Link
                            :href="route('admin.methodology-templates.index')"
                            class="inline-flex items-center rounded-lg bg-talents-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700"
                        >
                            Gerenciar templates (etapa 02)
                        </Link>
                        <Link
                            :href="route('admin.plans.index')"
                            class="inline-flex items-center rounded-lg border border-talents-300 bg-white px-4 py-2.5 text-sm font-semibold text-talents-800 hover:bg-talents-50"
                        >
                            Planos — incluir módulo
                        </Link>
                        <Link
                            :href="route('admin.companies.index')"
                            class="inline-flex items-center rounded-lg border border-talents-300 bg-white px-4 py-2.5 text-sm font-semibold text-talents-800 hover:bg-talents-50"
                        >
                            Empresas — vincular templates
                        </Link>
                    </div>
                    <div v-if="recentTemplates?.length" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                        <h3 class="font-semibold text-talents-800">Templates recentes</h3>
                        <ul class="mt-3 divide-y divide-gray-100 text-sm">
                            <li v-for="t in recentTemplates" :key="t.id" class="flex flex-wrap items-center justify-between gap-2 py-2">
                                <span class="font-medium text-gray-900">{{ t.title }}</span>
                                <span class="text-gray-500">{{ t.sections_count }} seções · {{ t.companies_count }} empresas</span>
                                <Link :href="route('admin.methodology-templates.edit', t.id)" class="text-talents-700 hover:underline">Editar</Link>
                            </li>
                        </ul>
                    </div>
                </div>
                <div v-else class="rounded-xl border border-dashed border-talents-200 bg-white/60 p-10 text-center">
                    <p class="text-lg font-medium text-talents-900">Etapa em desenvolvimento</p>
                    <p class="mt-2 text-sm text-gray-600">Em breve você poderá configurar e acompanhar esta fase da metodologia aqui.</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
