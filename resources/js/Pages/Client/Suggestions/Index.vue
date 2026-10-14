<script setup>
import ComplaintCompanyPicker from '@/Components/Complaints/ComplaintCompanyPicker.vue';
import ComplaintsPublicLinkPanel from '@/Components/Complaints/ComplaintsPublicLinkPanel.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import SuggestionsLayout from '@/Components/Suggestions/SuggestionsLayout.vue';
import { suggestionRoute } from '@/composables/useSuggestionRoutes';
import { formatDateTime } from '@/utils/dateOnly';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    suggestions: Object,
    companyPicker: { type: Array, default: null },
    activeCompany: { type: Object, default: null },
    isAdminContext: { type: Boolean, default: false },
    publicUrl: { type: String, default: null },
});

const needsPicker = computed(() => props.isAdminContext && !props.activeCompany);
</script>

<template>
    <Head title="Canal de sugestões e dúvidas" />

    <SuggestionsLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-talents-900">Sugestões e dúvidas</h2>
            <p v-if="activeCompany" class="mt-1 text-sm text-slate-600">Empresa: {{ activeCompany.name }}</p>
            <p v-else class="mt-1 text-sm text-slate-500">
                Mensagens enviadas pelo link público, sem login.
            </p>
        </template>

        <div v-if="needsPicker" class="mx-auto max-w-xl">
            <ComplaintCompanyPicker
                v-if="companyPicker?.length"
                :companies="companyPicker"
                :submit-route="route('admin.suggestions.company.store')"
                description="Escolha o cliente para gerenciar o canal de sugestões e dúvidas."
            />
            <p v-else class="surface-card p-6 text-sm text-slate-500">
                Nenhuma empresa com o canal de sugestões ativo no plano.
            </p>
        </div>

        <template v-else>
            <ComplaintCompanyPicker
                v-if="isAdminContext && companyPicker?.length"
                class="mb-6"
                compact
                :companies="companyPicker"
                :active-company-id="activeCompany?.id"
                :submit-route="route('admin.suggestions.company.store')"
            />

            <ComplaintsPublicLinkPanel v-if="publicUrl" :url="publicUrl" variant="card" title="Link público do canal">
                <template #description>
                    Os colaboradores acessam <span class="font-medium text-slate-800">sem login</span>,
                    pelo link abaixo. Partilhe este URL (e-mail, intranet ou QR).
                </template>
                <template #footnote>
                    Canal anônimo de sugestões e dúvidas. A leitura das mensagens no painel continua exigindo permissão.
                </template>
            </ComplaintsPublicLinkPanel>

            <div class="surface-card mt-6 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-700">Data</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-700">Assunto</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-700">Área</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-700">Retorno</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-700">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="item in suggestions.data" :key="item.id">
                                <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ formatDateTime(item.created_at) }}</td>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ item.topic_label }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ item.area_label }}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ item.response_label }}
                                    <span v-if="item.has_contact" class="mt-1 block text-xs text-talents-700">Com contato</span>
                                </td>
                                <td class="px-4 py-3">{{ item.status_label }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="suggestionRoute('show', item.id)" class="font-medium text-talents-700 hover:underline">
                                        Abrir
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="!suggestions.data?.length" class="px-4 py-8 text-center text-sm text-gray-500">
                    Nenhuma mensagem recebida ainda.
                </p>
                <PaginationBar :paginator="suggestions" />
            </div>
        </template>
    </SuggestionsLayout>
</template>
