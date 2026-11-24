<script setup>
import FeedbackAnswersList from '@/Components/Feedback/FeedbackAnswersList.vue';
import FeedbackFlash from '@/Components/Feedback/FeedbackFlash.vue';
import FeedbackRecordingPanel from '@/Components/Feedback/FeedbackRecordingPanel.vue';
import FeedbackSessionDeleteModal from '@/Components/Feedback/FeedbackSessionDeleteModal.vue';
import FeedbackSessionHeader from '@/Components/Feedback/FeedbackSessionHeader.vue';
import FeedbackSessionJourney from '@/Components/Feedback/FeedbackSessionJourney.vue';
import FeedbackSessionSignaturesPanel from '@/Components/Feedback/FeedbackSessionSignaturesPanel.vue';
import FeedbackTabs from '@/Components/Feedback/FeedbackTabs.vue';
import FeedbacksLayout from '@/Components/Feedback/FeedbacksLayout.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { RECORDING_STATES, recordingState } from '@/utils/feedbackRecordingView';
import { SESSION_TABS, journey, overallProgress, primaryAction, tabFromUrl, urlWithTab } from '@/utils/feedbackSessionView';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ArrowDownTrayIcon, ArrowPathIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { computed, nextTick, ref } from 'vue';

/**
 * Detalhe do feedback (portal e admin com empresa selecionada): cabeçalho com uma ação principal por estado e kebab,
 * jornada numa linha e abas («Respostas», «Gravação e relatório», «Assinaturas») com a aba na URL (?aba=).
 * Só apresentação: envio para assinatura, permissões, visibilidade e auditoria continuam no servidor como antes.
 */
const props = defineProps({
    session: Object,
    canExportPdf: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    recording: { type: Object, default: null },
    recordingAccess: { type: Object, default: () => ({}) },
    recordingAi: { type: Object, default: null },
});

const page = usePage();
const deletingSession = ref(null);

// ── Estado da página ──
const sections = computed(() => props.session.template?.sections ?? []);
const progress = computed(() => overallProgress(sections.value, props.session.answers ?? {}));
const primary = computed(() => primaryAction(props.session, progress.value));
const steps = computed(() => journey(props.session, progress.value));
const canEdit = computed(() => props.session.status !== 'completed' && props.session.status !== 'cancelled');

// ── Abas na URL: clicar cria uma entrada no histórico (Voltar volta para a aba anterior) ──
const tab = computed(() => tabFromUrl(page.url));
function selectTab(key) {
    if (key === tab.value) return;
    router.push({ url: urlWithTab(page.url, key), preserveScroll: true, preserveState: true });
}

const recState = computed(() => recordingState(props.recording, props.recordingAi, props.recordingAccess));
const recordingHintClass = {
    action: 'bg-talents-50 text-talents-900 ring-talents-200',
    warning: 'bg-amber-50 text-amber-950 ring-amber-300',
    success: 'bg-emerald-50 text-emerald-900 ring-emerald-200',
    danger: 'bg-rose-50 text-rose-900 ring-rose-200',
};
const signedCount = computed(() => (props.session.signatures ?? []).filter((s) => s.signed_at).length);
const tabs = computed(() =>
    SESSION_TABS.map((t) => {
        if (t.key === 'respostas' && progress.value.total) return { ...t, hint: `${progress.value.answered}/${progress.value.total}` };
        if (t.key === 'gravacao') {
            const state = RECORDING_STATES[recState.value] ?? RECORDING_STATES.empty;
            return { ...t, hint: state.label, hintClass: recordingHintClass[state.tone] };
        }
        if (t.key === 'assinaturas' && props.session.signatures?.length) return { ...t, hint: `${signedCount.value}/${props.session.signatures.length}` };
        return t;
    }),
);

// ── Ação principal ──
const answersList = ref(null);
const sending = ref(false);
async function onPrimary(key) {
    if (key === 'signatures') return selectTab('assinaturas');
    if (key === 'continue') {
        // Outra aba aberta: troca para «Respostas» e espera o painel aparecer antes de rolar e focar.
        if (tab.value !== 'respostas') {
            selectTab('respostas');
            for (let i = 0; i < 60 && tab.value !== 'respostas'; i += 1) await new Promise((r) => requestAnimationFrame(r));
        }
        await nextTick();
        return answersList.value?.focusFirstIncomplete();
    }
    if (key === 'send' && !sending.value) {
        // Mesmo envio da aba «Assinaturas» (mesma rota e regra).
        router.post(feedbackRoute('sessions.signatures', props.session.id), {}, {
            preserveScroll: true,
            onStart: () => (sending.value = true),
            onSuccess: () => selectTab('assinaturas'),
            onFinish: () => (sending.value = false),
        });
    }
}

// ── Menu kebab: as ações secundárias de antes ──
const canRegenerateReport = computed(
    () =>
        !!props.recordingAi?.clean
        && props.recordingAi?.gate?.allowed
        && !props.recordingAi?.limit_reached
        && ['ready', 'failed'].includes(props.recordingAi?.status)
        && props.recordingAccess?.canProcess
        && !props.recordingAccess?.locked,
);
const menuItems = computed(() => [
    ...(canEdit.value ? [{ key: 'edit', label: 'Editar', icon: PencilSquareIcon, href: feedbackRoute('sessions.edit', props.session.id) }] : []),
    ...(props.canExportPdf
        ? [{ key: 'pdf', label: 'Exportar PDF', icon: ArrowDownTrayIcon, href: feedbackRoute('sessions.pdf', props.session.id), external: true }]
        : []),
    ...(canRegenerateReport.value
        ? [{ key: 'report', label: 'Gerar relatório novamente', icon: ArrowPathIcon, onSelect: () => router.post(feedbackRoute('sessions.recording.report', props.session.id), {}, { preserveScroll: true }) }]
        : []),
    ...(props.canDelete ? [{ key: 'delete', label: 'Excluir feedback', icon: TrashIcon, danger: true, onSelect: () => (deletingSession.value = props.session) }] : []),
]);

const panelId = (key) => `feedback-session-panel-${key}`;
const tabId = (key) => `feedback-session-tab-${key}`;
</script>

<template>
    <Head :title="`Feedback · ${session.employee?.name ?? session.title}`" />

    <FeedbacksLayout>
        <template #header>
            <FeedbackSessionHeader :session="session" :primary="primary" :primary-busy="sending" :menu-items="menuItems" @primary="onPrimary" />
        </template>

        <div class="space-y-6">
            <FeedbackSessionJourney :journey="steps" />

            <FeedbackTabs :tabs="tabs" :model-value="tab" label="Conteúdo do feedback" id-prefix="feedback-session" @update:model-value="selectTab" />

            <!-- Avisos de sucesso e erro dentro da aba onde a ação aconteceu -->
            <div :id="panelId('respostas')" role="tabpanel" :aria-labelledby="tabId('respostas')" tabindex="0" class="space-y-4 focus:outline-none" v-show="tab === 'respostas'">
                <FeedbackFlash v-if="tab === 'respostas'" />
                <FeedbackAnswersList ref="answersList" :session="session" :can-edit="canEdit" />
            </div>

            <div :id="panelId('gravacao')" role="tabpanel" :aria-labelledby="tabId('gravacao')" tabindex="0" class="space-y-4 focus:outline-none" v-show="tab === 'gravacao'">
                <FeedbackFlash v-if="tab === 'gravacao'" />
                <FeedbackRecordingPanel
                    :session-id="session.id"
                    :recording="recording"
                    :ai="recordingAi"
                    :access="recordingAccess"
                    :people="{ leader: session.leader?.name, employee: session.employee?.name }"
                    embedded
                />
            </div>

            <div :id="panelId('assinaturas')" role="tabpanel" :aria-labelledby="tabId('assinaturas')" tabindex="0" class="space-y-4 focus:outline-none" v-show="tab === 'assinaturas'">
                <FeedbackFlash v-if="tab === 'assinaturas'" />
                <FeedbackSessionSignaturesPanel :session="session" :pending-answers="progress.total - progress.answered" @sent="selectTab('assinaturas')" />
            </div>
        </div>

        <FeedbackSessionDeleteModal :session="deletingSession" @close="deletingSession = null" />
    </FeedbacksLayout>
</template>
