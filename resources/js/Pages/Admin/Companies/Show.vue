<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    company: Object,
    complaintsPublicUrl: { type: String, default: null },
    plans: Array,
    templates: Array,
});

const showDeleteModal = ref(false);

const attach = (templateId) => {
    router.post(route('admin.companies.templates.attach', [props.company.id, templateId]));
};

const detach = (templateId) => {
    router.delete(route('admin.companies.templates.detach', [props.company.id, templateId]));
};

const deleteCompany = () => {
    router.delete(route('admin.companies.destroy', props.company.id), {
        onFinish: () => {
            showDeleteModal.value = false;
        },
    });
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
            <h3 class="font-semibold text-talents-700">Pesquisas e plano de ação</h3>
            <p class="mt-2 text-sm text-gray-600">
                Edite o plano de ação de cada pesquisa; ele só aparece para a empresa após você preencher e salvar os itens.
            </p>
            <ul class="mt-4 space-y-2 text-sm">
                <li
                    v-for="s in company.surveys || []"
                    :key="s.id"
                    class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 py-2 last:border-0"
                >
                    <span class="font-medium text-gray-900">{{ s.title }}</span>
                    <Link
                        :href="route('admin.companies.surveys.action-plan.edit', [company.id, s.id])"
                        class="font-medium text-talents-700 hover:underline"
                    >
                        Plano de ação
                    </Link>
                </li>
                <li v-if="!(company.surveys || []).length" class="text-gray-500">Nenhuma pesquisa cadastrada.</li>
            </ul>
        </div>

        <div class="mt-8 rounded-xl border border-gray-200 bg-white p-6 text-gray-900 shadow-sm">
            <h3 class="font-semibold text-talents-700">Usuários</h3>
            <ul class="mt-4 space-y-1 text-sm">
                <li v-for="u in company.users" :key="u.id">{{ u.name }} — {{ u.email }} ({{ u.role }})</li>
            </ul>
        </div>

        <div class="mt-8 rounded-xl border border-red-200 bg-red-50/50 p-6">
            <h3 class="font-semibold text-red-800">Excluir empresa</h3>
            <p class="mt-2 text-sm text-red-900/90">
                Remove a empresa, usuários vinculados a ela e os dados associados (pesquisas, assinaturas, etc.). Esta ação não pode ser desfeita.
            </p>
            <DangerButton class="mt-4" type="button" @click="showDeleteModal = true">Excluir empresa</DangerButton>
        </div>

        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Confirmar exclusão</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Tem certeza que deseja excluir <strong>{{ company.name }}</strong>? Todos os dados desta empresa serão apagados permanentemente.
                </p>
                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showDeleteModal = false">Cancelar</SecondaryButton>
                    <DangerButton type="button" @click="deleteCompany">Sim, excluir</DangerButton>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>
