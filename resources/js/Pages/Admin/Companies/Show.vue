<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    company: Object,
    complaintsPublicUrl: { type: String, default: null },
    plans: Array,
    templates: Array,
});

const attach = (templateId) => {
    router.post(route('admin.companies.templates.attach', [props.company.id, templateId]));
};

const detach = (templateId) => {
    router.delete(route('admin.companies.templates.detach', [props.company.id, templateId]));
};
</script>

<template>
    <Head :title="company.name" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-900">{{ company.name }}</h2>
                <Link :href="route('admin.companies.edit', company.id)" class="font-medium text-talents-700 hover:underline">Editar</Link>
            </div>
        </template>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-gray-900 shadow-sm">
                <h3 class="font-semibold text-talents-700">Dados</h3>
                <dl class="mt-4 space-y-2 text-sm">
                    <div><dt class="text-gray-500">E-mail (contato / administrador)</dt><dd>{{ company.contact_email || '—' }}</dd></div>
                    <div><dt class="text-gray-500">Razão social</dt><dd>{{ company.legal_name || '—' }}</dd></div>
                    <div><dt class="text-gray-500">CNPJ</dt><dd>{{ company.cnpj || '—' }}</dd></div>
                    <div><dt class="text-gray-500">Logradouro</dt><dd>{{ company.address_street || '—' }}</dd></div>
                    <div><dt class="text-gray-500">Bairro</dt><dd>{{ company.address_neighborhood || '—' }}</dd></div>
                    <div><dt class="text-gray-500">Município</dt><dd>{{ company.address_city || '—' }}</dd></div>
                    <div><dt class="text-gray-500">UF</dt><dd>{{ company.address_state || '—' }}</dd></div>
                    <div><dt class="text-gray-500">CEP</dt><dd>{{ company.address_zip || '—' }}</dd></div>
                    <div><dt class="text-gray-500">Segmento</dt><dd>{{ company.segment || '—' }}</dd></div>
                    <div><dt class="text-gray-500">Regime de tributação</dt><dd>{{ company.tax_regime || '—' }}</dd></div>
                </dl>
                <div v-if="complaintsPublicUrl" class="mt-4 border-t border-gray-100 pt-4">
                    <h4 class="text-xs font-semibold uppercase text-gray-500">Canal de denúncias (público)</h4>
                    <p class="mt-1 break-all font-mono text-xs text-gray-800">{{ complaintsPublicUrl }}</p>
                </div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-gray-900 shadow-sm">
                <h3 class="font-semibold text-talents-700">Assinaturas</h3>
                <ul class="mt-4 space-y-2 text-sm">
                    <li v-for="sub in company.subscriptions" :key="sub.id">
                        {{ sub.plan?.name }} — {{ sub.status }}
                    </li>
                    <li v-if="!company.subscriptions?.length">Nenhuma assinatura.</li>
                </ul>
            </div>
        </div>

        <div class="mt-8 rounded-xl border border-gray-200 bg-white p-6 text-gray-900 shadow-sm">
            <h3 class="font-semibold text-talents-700">Templates disponíveis para a empresa</h3>
            <ul class="mt-4 space-y-2 text-sm">
                <li v-for="t in templates" :key="t.id" class="flex items-center justify-between">
                    <span>{{ t.title }}</span>
                    <span>
                        <button
                            v-if="!(company.survey_templates || []).some((x) => x.id === t.id)"
                            type="button"
                            class="font-medium text-talents-700 hover:underline"
                            @click="attach(t.id)"
                        >
                            Vincular
                        </button>
                        <button v-else type="button" class="font-medium text-red-600 hover:underline" @click="detach(t.id)">Remover</button>
                    </span>
                </li>
            </ul>
        </div>

        <div class="mt-8 rounded-xl border border-gray-200 bg-white p-6 text-gray-900 shadow-sm">
            <h3 class="font-semibold text-talents-700">Usuários</h3>
            <ul class="mt-4 space-y-1 text-sm">
                <li v-for="u in company.users" :key="u.id">{{ u.name }} — {{ u.email }} ({{ u.role }})</li>
            </ul>
        </div>
    </AdminLayout>
</template>
