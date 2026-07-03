<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import SurveyStatusBadge from '@/Components/SurveyStatusBadge.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    survey: Object,
    publicUrl: String,
});

const copied = ref(false);

const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(props.publicUrl);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        copied.value = false;
    }
};

const qrSrc = `https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=${encodeURIComponent(props.publicUrl)}`;

const deleteSurvey = () => {
    if (confirm('Remover esta pesquisa e todas as respostas?')) {
        router.delete(route('client.metodologia.pesquisa-satisfacao.destroy', props.survey.id));
    }
};
</script>

<template>
    <Head :title="survey.title" />

    <ClientLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-talents-900">{{ survey.title }}</h2>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="route('client.metodologia.pesquisa-satisfacao.results', survey.id)"
                        class="rounded-md bg-talents-600 px-3 py-2 text-sm font-semibold text-white hover:bg-talents-700"
                    >
                        Resultados
                    </Link>
                    <Link
                        :href="route('client.metodologia.pesquisa-satisfacao.edit', survey.id)"
                        class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Editar
                    </Link>
                </div>
            </div>
        </template>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="surface-card p-6">
                <h3 class="font-semibold text-talents-800">Link público</h3>
                <p class="mt-2 break-all font-mono text-xs text-gray-700">{{ publicUrl }}</p>
                <button
                    type="button"
                    class="mt-3 rounded-md bg-talents-100 px-3 py-2 text-sm font-medium text-talents-800 hover:bg-talents-200"
                    @click="copyLink"
                >
                    {{ copied ? 'Copiado!' : 'Copiar link' }}
                </button>
                <p class="mt-4 text-sm text-gray-600">Respostas concluídas: <strong>{{ survey.completed_responses_count }}</strong></p>
                <p class="mt-2 flex flex-wrap items-center gap-2 text-sm text-gray-600">
                    <span>Status:</span>
                    <SurveyStatusBadge :status="survey.status" />
                </p>
                <p class="mt-2 text-sm text-gray-600">Coleta de e-mail: {{ survey.collect_email ? 'Sim' : 'Não' }}</p>
            </div>
            <div class="surface-card p-6 text-center">
                <h3 class="font-semibold text-talents-800">QR Code</h3>
                <img :src="qrSrc" alt="QR Code" class="mx-auto mt-4 h-40 w-40 rounded-lg border border-gray-100 bg-white p-1" />
                <p class="mt-2 text-xs text-gray-500">Leitura direta para o formulário público</p>
            </div>
        </div>

        <div class="mt-8 rounded-xl border border-red-100 bg-red-50/50 p-6">
            <DangerButton type="button" @click="deleteSurvey">Excluir pesquisa</DangerButton>
        </div>

        <p class="mt-6">
            <Link :href="route('client.metodologia.pesquisa-satisfacao.index')" class="text-sm text-talents-700 hover:underline">← Lista</Link>
        </p>
    </ClientLayout>
</template>
