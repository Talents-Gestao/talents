<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    interview: Object,
    sections: Array,
});

const showTranscript = ref(false);
let pollTimer = null;

const statusClass = (value) => {
    const map = {
        queued: 'bg-slate-100 text-slate-800',
        transcribing: 'bg-amber-100 text-amber-900',
        extracting: 'bg-blue-100 text-blue-900',
        completed: 'bg-emerald-100 text-emerald-900',
        failed: 'bg-red-100 text-red-900',
    };
    return map[value] ?? 'bg-slate-100 text-slate-800';
};

const reprocess = () => {
    if (!confirm('Reprocessar esta entrevista? As respostas atuais serão substituídas.')) {
        return;
    }
    router.post(route('admin.entrevistas.reprocess', props.interview.id), {}, { preserveScroll: true });
};

const destroyInterview = () => {
    if (!confirm('Excluir esta entrevista permanentemente?')) {
        return;
    }
    router.delete(route('admin.entrevistas.destroy', props.interview.id));
};

onMounted(() => {
    if (!props.interview.is_processing) {
        return;
    }
    pollTimer = setInterval(() => {
        router.reload({ only: ['interview', 'sections'], preserveScroll: true });
    }, 5000);
});

onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }
});
</script>

<template>
    <Head :title="`Entrevista — ${interview.candidate_name}`" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-gray-500">
                        <Link :href="route('admin.entrevistas.index')" class="text-talents-700 hover:underline">
                            Entrevistas
                        </Link>
                        / {{ interview.candidate_name }}
                    </p>
                    <h2 class="text-xl font-semibold leading-tight text-gray-900">{{ interview.candidate_name }}</h2>
                    <p v-if="interview.position_title" class="mt-1 text-sm text-gray-600">Vaga: {{ interview.position_title }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a
                        v-if="interview.status === 'completed'"
                        :href="route('admin.entrevistas.report.pdf', interview.id)"
                        class="inline-flex items-center rounded-md bg-talents-600 px-3 py-2 text-sm font-medium text-white hover:bg-talents-700"
                    >
                        PDF
                    </a>
                    <a
                        v-if="interview.status === 'completed'"
                        :href="route('admin.entrevistas.report.docx', interview.id)"
                        class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-medium text-talents-800 ring-1 ring-talents-200 hover:bg-talents-50"
                    >
                        DOCX
                    </a>
                    <SecondaryButton v-if="interview.status === 'failed'" type="button" @click="reprocess">
                        Reprocessar
                    </SecondaryButton>
                    <SecondaryButton type="button" class="!text-red-700 ring-red-200" @click="destroyInterview">
                        Excluir
                    </SecondaryButton>
                </div>
            </div>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>

        <div class="mb-6 surface-card p-4">
            <div class="flex flex-wrap items-center gap-3">
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(interview.status)">
                    {{ interview.status_label }}
                </span>
                <span class="text-sm text-gray-600">Roteiro: {{ interview.questionnaire?.name }}</span>
                <span v-if="interview.company" class="text-sm text-gray-600">Empresa: {{ interview.company.name }}</span>
            </div>
            <p v-if="interview.is_processing" class="mt-3 text-sm text-amber-800">
                Processamento em andamento… esta página atualiza automaticamente a cada 5 segundos.
            </p>
            <p v-if="interview.failure_reason" class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900">
                {{ interview.failure_reason }}
            </p>
        </div>

        <div v-if="sections?.length" class="space-y-6">
            <p
                v-if="interview.is_processing"
                class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700"
            >
                Roteiro de perguntas abaixo. As respostas serão preenchidas automaticamente quando o processamento terminar.
            </p>

            <section v-for="section in sections" :key="section.id" class="surface-card p-5">
                <h3 class="text-base font-semibold text-gray-900">{{ section.title }}</h3>
                <div class="mt-4 space-y-4">
                    <article v-for="question in section.questions" :key="question.id" class="border-b border-slate-100 pb-4 last:border-0">
                        <p class="text-sm font-medium text-slate-800">{{ question.text }}</p>
                        <p
                            v-if="interview.status === 'completed'"
                            class="mt-2 whitespace-pre-wrap text-sm text-slate-700"
                        >
                            {{ question.answer || 'Não mencionado' }}
                        </p>
                        <p v-else-if="interview.is_processing" class="mt-2 text-sm italic text-slate-500">
                            Aguardando processamento…
                        </p>
                        <p v-else class="mt-2 text-sm italic text-slate-500">—</p>
                        <p v-if="question.raw_quote" class="mt-2 text-xs italic text-slate-500">“{{ question.raw_quote }}”</p>
                    </article>
                </div>
            </section>

            <section v-if="interview.status === 'completed'" class="surface-card p-5">
                <button
                    type="button"
                    class="flex w-full items-center justify-between text-left text-sm font-semibold text-gray-900"
                    @click="showTranscript = !showTranscript"
                >
                    Transcrição completa
                    <span>{{ showTranscript ? '▲' : '▼' }}</span>
                </button>
                <pre
                    v-if="showTranscript && interview.transcript_text"
                    class="mt-4 max-h-[28rem] overflow-auto whitespace-pre-wrap rounded-lg bg-slate-50 p-4 text-xs text-slate-800"
                    >{{ interview.transcript_text }}</pre
                >
            </section>
        </div>

        <div v-else-if="!interview.is_processing && interview.status !== 'completed'" class="surface-card p-8 text-center text-sm text-gray-600">
            Nenhum relatório disponível.
        </div>
    </AdminLayout>
</template>
