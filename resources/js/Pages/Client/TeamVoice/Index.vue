<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ClipboardDocumentListIcon,
    LinkIcon,
    MegaphoneIcon,
    ShieldExclamationIcon,
} from '@heroicons/vue/24/outline';

defineProps({
    surveysCount: { type: Number, default: 0 },
    complaintsCount: { type: Number, default: 0 },
    openComplaintsCount: { type: Number, default: 0 },
    canSurveys: { type: Boolean, default: false },
    canComplaints: { type: Boolean, default: false },
    complaintsPublicUrl: { type: String, default: null },
});
</script>

<template>
    <Head title="Voz do Time" />

    <ClientLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <h2 class="text-xl font-semibold leading-tight text-slate-900">Voz do Time</h2>
                <p class="text-sm text-slate-500">
                    Escuta estruturada: pesquisas e canal de denúncias em um só lugar.
                </p>
            </div>
        </template>

        <div class="grid gap-4 sm:grid-cols-2">
            <Link
                v-if="canSurveys"
                :href="route('client.surveys.index')"
                class="surface-card group flex flex-col gap-3 p-5 transition hover:border-talents-200 hover:shadow-md"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="rounded-xl bg-sky-50 p-2.5 text-sky-700 ring-1 ring-sky-100">
                        <ClipboardDocumentListIcon class="h-6 w-6" aria-hidden="true" />
                    </div>
                    <span class="text-2xl font-bold tabular-nums text-slate-900">{{ surveysCount }}</span>
                </div>
                <div>
                    <h3 class="font-semibold text-slate-900 group-hover:text-talents-700">Pesquisas NR1</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Mapeamento de riscos psicossociais, resultados e planos de ação.
                    </p>
                </div>
            </Link>

            <Link
                v-if="canComplaints"
                :href="route('client.complaints.index')"
                class="surface-card group flex flex-col gap-3 p-5 transition hover:border-talents-200 hover:shadow-md"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="rounded-xl bg-rose-50 p-2.5 text-rose-700 ring-1 ring-rose-100">
                        <ShieldExclamationIcon class="h-6 w-6" aria-hidden="true" />
                    </div>
                    <div class="text-right">
                        <span class="text-2xl font-bold tabular-nums text-slate-900">{{ complaintsCount }}</span>
                        <p v-if="openComplaintsCount" class="text-xs font-medium text-amber-700">
                            {{ openComplaintsCount }} em aberto
                        </p>
                    </div>
                </div>
                <div>
                    <h3 class="font-semibold text-slate-900 group-hover:text-talents-700">Canal de denúncias</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Gestão de relatos, protocolos e acompanhamento conforme Lei 14.457/2022.
                    </p>
                </div>
            </Link>
        </div>

        <div
            v-if="canComplaints && complaintsPublicUrl"
            class="surface-card mt-6 flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-start gap-3">
                <div class="rounded-xl bg-violet-50 p-2.5 text-violet-700 ring-1 ring-violet-100">
                    <LinkIcon class="h-5 w-5" aria-hidden="true" />
                </div>
                <div>
                    <p class="font-medium text-slate-900">Link público do canal</p>
                    <p class="mt-0.5 break-all text-sm text-slate-500">{{ complaintsPublicUrl }}</p>
                </div>
            </div>
            <a
                :href="complaintsPublicUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="btn-ghost shrink-0 !px-4 text-sm"
            >
                Abrir formulário
            </a>
        </div>

        <div
            v-if="!canSurveys && !canComplaints"
            class="surface-card mt-4 flex items-center gap-3 p-6 text-sm text-slate-500"
        >
            <MegaphoneIcon class="h-8 w-8 shrink-0 text-slate-300" aria-hidden="true" />
            <p>Nenhum módulo de escuta disponível no seu plano. Contacte a equipe Talents.</p>
        </div>
    </ClientLayout>
</template>
