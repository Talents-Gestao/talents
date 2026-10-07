<script setup>
import { formatDateTime } from '@/utils/dateOnly';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    token: String,
    companyName: String,
    complaint: Object,
});

const page = usePage();

const msgForm = useForm({
    content: '',
});

const submitMsg = () => {
    msgForm.post(route('denuncia.reporter.message', { token: props.token, protocol: props.complaint.protocol }), {
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

const authorLabel = (t) => {
    if (t === 'reporter') return 'Você';
    if (t === 'company') return 'Empresa';
    return 'Sistema';
};

const flashSuccess = computed(() => page.props.flash?.success);
</script>

<template>
    <Head title="Acompanhamento de denúncia" />

    <div class="app-shell min-h-screen px-4 py-8 text-slate-900">
        <div class="mx-auto max-w-2xl">
            <p class="text-xs uppercase text-talents-600">{{ companyName }}</p>
            <h1 class="text-xl font-semibold text-slate-900">Protocolo {{ complaint.protocol }}</h1>
            <p class="mt-2 text-sm">
                Status:
                <span class="font-semibold text-talents-800">{{ statusLabel(complaint.status) }}</span>
            </p>
            <p class="mt-1 text-sm text-slate-600">
                Setor:
                <span class="text-slate-900">{{ complaint.department_name || 'Não informado' }}</span>
            </p>

            <div
                v-if="flashSuccess"
                class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900"
            >
                {{ flashSuccess }}
            </div>

            <div class="surface-card mt-6 p-6">
                <h2 class="text-sm font-semibold text-slate-800">Relato</h2>
                <p class="mt-2 whitespace-pre-wrap text-sm text-slate-700">{{ complaint.description }}</p>
            </div>

            <div class="mt-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-800">Mensagens</h2>
                <div
                    v-for="m in complaint.messages"
                    :key="m.id"
                    class="surface-card border-slate-100/80 p-4 text-sm"
                >
                    <p class="text-xs font-medium text-talents-700">{{ authorLabel(m.author_type) }}</p>
                    <p class="mt-2 whitespace-pre-wrap text-slate-700">{{ m.content }}</p>
                    <p class="mt-2 text-xs text-slate-400">{{ formatDateTime(m.created_at) }}</p>
                </div>
            </div>

            <form v-if="complaint.status !== 'archived'" class="surface-card mt-6 p-6" @submit.prevent="submitMsg">
                <label class="block text-sm font-medium text-slate-700">Enviar mensagem</label>
                <textarea
                    v-model="msgForm.content"
                    rows="4"
                    class="mt-1 w-full rounded-lg border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    required
                />
                <p v-if="msgForm.errors.content" class="mt-1 text-sm text-red-600">{{ msgForm.errors.content }}</p>
                <button
                    type="submit"
                    class="mt-3 rounded-full bg-talents-700 px-4 py-2 text-sm font-semibold text-white shadow-md hover:bg-talents-800"
                    :disabled="msgForm.processing"
                >
                    Enviar
                </button>
            </form>

            <p class="mt-8 text-center text-sm">
                <Link :href="route('denuncia.track', token)" class="text-talents-700 hover:underline">Outro protocolo</Link>
            </p>
        </div>
    </div>
</template>
