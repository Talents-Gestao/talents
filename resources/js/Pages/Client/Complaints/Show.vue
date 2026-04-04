<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ complaint: Object });

const statusForm = useForm({ status: props.complaint.status });
const msgForm = useForm({ content: '' });

const updateStatus = () => {
    statusForm.patch(route('client.complaints.status', props.complaint.id));
};

const sendMessage = () => {
    msgForm.post(route('client.complaints.messages.store', props.complaint.id), {
        preserveScroll: true,
        onSuccess: () => msgForm.reset('content'),
    });
};

const statusLabel = (s) => {
    const map = {
        new: 'Nova',
        under_review: 'Em análise',
        resolved: 'Resolvida',
        archived: 'Arquivada',
    };
    return map[s] || s;
};
</script>

<template>
    <Head :title="`Denúncia ${complaint.protocol}`" />

    <ClientLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <Link :href="route('client.complaints.index')" class="text-sm text-talents-700 hover:underline">← Voltar</Link>
                    <h2 class="mt-1 text-xl font-semibold text-talents-900">Denúncia</h2>
                    <p class="font-mono text-sm text-gray-600">{{ complaint.protocol }}</p>
                </div>
                <form class="flex flex-wrap items-end gap-2" @submit.prevent="updateStatus">
                    <div>
                        <label class="text-xs text-gray-600">Status</label>
                        <select
                            v-model="statusForm.status"
                            class="block rounded-md border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        >
                            <option value="new">Nova</option>
                            <option value="under_review">Em análise</option>
                            <option value="resolved">Resolvida</option>
                            <option value="archived">Arquivada</option>
                        </select>
                    </div>
                    <button
                        type="submit"
                        class="rounded-md bg-talents-700 px-3 py-2 text-sm font-semibold text-white"
                        :disabled="statusForm.processing"
                    >
                        Salvar status
                    </button>
                </form>
            </div>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Relato</h3>
                    <p class="mt-2 text-sm text-gray-600">
                        Tipo: {{ complaint.category }} · {{ statusLabel(complaint.status) }}
                        <span v-if="complaint.department_name"> · Setor: {{ complaint.department_name }}</span>
                        <span v-else> · Setor: não informado</span>
                    </p>
                    <p v-if="!complaint.is_anonymous" class="mt-2 text-sm text-gray-600">
                        Contato: {{ complaint.reporter_name }} — {{ complaint.reporter_email }}
                    </p>
                    <p v-else class="mt-2 text-sm text-amber-800">Denúncia anônima</p>
                    <p class="mt-4 whitespace-pre-wrap text-sm text-gray-800">{{ complaint.description }}</p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Mensagens</h3>
                    <div v-for="m in complaint.messages" :key="m.id" class="mt-4 border-t border-gray-100 pt-4 first:border-t-0 first:pt-0">
                        <p class="text-xs font-medium text-talents-700">
                            {{ m.author_type === 'company' ? 'Empresa' : m.author_type === 'reporter' ? 'Denunciante' : 'Sistema' }}
                            <span v-if="m.user">({{ m.user.name }})</span>
                        </p>
                        <p class="mt-1 whitespace-pre-wrap text-sm text-gray-700">{{ m.content }}</p>
                        <p class="mt-1 text-xs text-gray-400">{{ m.created_at }}</p>
                    </div>

                    <form class="mt-6 border-t border-gray-200 pt-6" @submit.prevent="sendMessage">
                        <label class="text-sm font-medium text-gray-700">Resposta da empresa</label>
                        <textarea
                            v-model="msgForm.content"
                            rows="4"
                            class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            required
                        />
                        <button
                            type="submit"
                            class="mt-2 rounded-lg bg-talents-700 px-4 py-2 text-sm font-semibold text-white"
                            :disabled="msgForm.processing"
                        >
                            Enviar resposta
                        </button>
                    </form>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="font-semibold text-gray-900">Trilha de auditoria</h3>
                <ul class="mt-4 space-y-3 text-xs text-gray-600">
                    <li v-for="log in complaint.audit_logs" :key="log.id" class="border-b border-gray-100 pb-3">
                        <p class="font-medium text-gray-800">{{ log.action }}</p>
                        <p v-if="log.user">{{ log.user.name }}</p>
                        <p v-if="log.ip_address">IP: {{ log.ip_address }}</p>
                        <p class="text-gray-400">{{ log.created_at }}</p>
                    </li>
                </ul>
            </div>
        </div>
    </ClientLayout>
</template>
