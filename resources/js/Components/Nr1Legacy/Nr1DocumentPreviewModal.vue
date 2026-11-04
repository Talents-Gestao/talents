<script setup>
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    document: { type: Object, default: null },
    downloadUrl: { type: String, default: null },
    releaseUrl: { type: String, default: null },
    validateUrl: { type: String, default: null },
    canValidate: { type: Boolean, default: false },
    /** Chave em sessionStorage para reabrir o modal após o redirect da liberação. */
    persistKey: { type: String, default: null },
    declarationText: {
        type: String,
        default:
            'Declaro que revisei este documento e que a validação se refere ao conteúdo congelado desta versão.',
    },
});

const emit = defineEmits(['close', 'released', 'validated']);

const releasing = ref(false);
const declarationAccepted = ref(false);

const validateForm = useForm({
    declaration_accepted: false,
});

const hasVersion = computed(() => Boolean(props.document?.version_number));

const versionNumber = computed(() => props.document?.version_number ?? null);

const statusLabel = computed(() => {
    if (!hasVersion.value) {
        return 'Ainda não liberado';
    }
    if (props.document.validated) {
        return `Validado · versão ${versionNumber.value}`;
    }
    if (props.document.validatable) {
        return `Liberado · pendente de validação · versão ${versionNumber.value}`;
    }
    return `Versão ${versionNumber.value}`;
});

const statusToneClass = computed(() => {
    if (!hasVersion.value) {
        return 'bg-amber-50 text-amber-950 ring-amber-200';
    }
    if (props.document?.validated) {
        return 'bg-emerald-50 text-emerald-900 ring-emerald-200';
    }
    return 'bg-sky-50 text-sky-950 ring-sky-200';
});

const pdfReady = computed(() => props.document?.ready === true || props.document?.pdf_status === 'ready');

const canRelease = computed(() => Boolean(props.releaseUrl));

const releaseLabel = computed(() => {
    if (releasing.value) {
        return 'Liberando…';
    }
    return hasVersion.value ? 'Liberar nova versão' : 'Liberar documento';
});

watch(
    () => props.show,
    (open) => {
        if (!open) {
            declarationAccepted.value = false;
            validateForm.reset();
            validateForm.clearErrors();
        }
    },
);

const close = () => emit('close');

const rememberOpen = () => {
    if (props.persistKey && typeof sessionStorage !== 'undefined') {
        sessionStorage.setItem('nr1-legacy-doc-modal', props.persistKey);
    }
};

const release = () => {
    if (!props.releaseUrl || releasing.value) {
        return;
    }
    rememberOpen();
    releasing.value = true;
    router.post(
        props.releaseUrl,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                releasing.value = false;
            },
            onSuccess: () => emit('released'),
            onError: () => {
                if (props.persistKey && typeof sessionStorage !== 'undefined') {
                    sessionStorage.removeItem('nr1-legacy-doc-modal');
                }
            },
        },
    );
};

const validate = () => {
    if (!props.validateUrl || !props.canValidate) {
        return;
    }
    rememberOpen();
    validateForm.declaration_accepted = declarationAccepted.value;
    validateForm.post(props.validateUrl, {
        preserveScroll: true,
        onSuccess: () => {
            declarationAccepted.value = false;
            validateForm.reset();
            emit('validated');
        },
        onError: () => {
            if (props.persistKey && typeof sessionStorage !== 'undefined') {
                sessionStorage.removeItem('nr1-legacy-doc-modal');
            }
        },
    });
};
</script>

<template>
    <Modal :show="show" max-width="5xl" @close="close">
        <div class="flex max-h-[min(90vh,52rem)] flex-col">
            <div class="border-b border-slate-100 px-6 py-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-gray-900">{{ title }}</h2>
                        <p v-if="description" class="mt-1 text-sm text-gray-600">{{ description }}</p>
                    </div>
                    <span
                        class="inline-flex shrink-0 items-center rounded-full px-3 py-1 text-xs font-semibold ring-1"
                        :class="statusToneClass"
                    >
                        {{ statusLabel }}
                    </span>
                </div>

                <dl class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2">
                        <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Versão</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-slate-900">
                            {{ hasVersion ? `v${versionNumber}` : '—' }}
                        </dd>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2">
                        <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-500">PDF</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-slate-900">
                            {{
                                !hasVersion
                                    ? 'Não gerado'
                                    : pdfReady
                                      ? 'Pronto'
                                      : document?.pdf_status === 'failed'
                                        ? 'Falhou'
                                        : 'Em preparação'
                            }}
                        </dd>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2">
                        <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Validação</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-slate-900">
                            {{
                                !hasVersion
                                    ? '—'
                                    : document?.validated
                                      ? 'Validado'
                                      : document?.validatable
                                        ? 'Pendente'
                                        : '—'
                            }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="min-h-0 flex-1 overflow-auto px-6 py-4">
                <iframe
                    v-if="pdfReady && downloadUrl"
                    :src="downloadUrl"
                    :title="title"
                    class="h-[min(55vh,32rem)] w-full rounded-xl border border-slate-200 bg-white"
                />
                <div
                    v-else
                    class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center"
                >
                    <template v-if="!hasVersion">
                        <p class="max-w-md text-sm text-slate-600">
                            Ainda não há versão liberada. Gere o documento aqui neste modal — não é preciso sair desta
                            tela.
                        </p>
                        <PrimaryButton
                            v-if="canRelease"
                            type="button"
                            :disabled="releasing"
                            @click="release"
                        >
                            {{ releaseLabel }}
                        </PrimaryButton>
                        <p v-else class="text-sm text-amber-800">
                            Não foi possível obter o link de liberação. Recarregue a página e tente de novo.
                        </p>
                    </template>
                    <template v-else>
                        <p class="max-w-md text-sm text-slate-600">
                            A versão {{ versionNumber }} foi registrada, mas o PDF ainda não está pronto. Você pode
                            tentar liberar novamente.
                        </p>
                        <PrimaryButton
                            v-if="canRelease"
                            type="button"
                            :disabled="releasing"
                            @click="release"
                        >
                            {{ releasing ? 'Liberando…' : 'Tentar liberar de novo' }}
                        </PrimaryButton>
                    </template>
                </div>

                <div
                    v-if="canValidate && validateUrl"
                    class="mt-4 rounded-xl border border-talents-200 bg-talents-50/60 p-4"
                >
                    <p class="text-sm font-semibold text-talents-950">Validar (Talents)</p>
                    <p class="mt-1 text-xs text-talents-900/80">
                        Confirma a revisão da versão {{ versionNumber }} congelada neste documento.
                    </p>
                    <label class="mt-3 flex items-start gap-2 text-sm text-talents-950">
                        <input
                            v-model="declarationAccepted"
                            type="checkbox"
                            class="mt-1 rounded border-slate-300 text-talents-700 focus:ring-talents-600"
                        />
                        <span>{{ declarationText }}</span>
                    </label>
                    <p v-if="validateForm.errors.declaration_accepted" class="mt-2 text-sm text-red-600">
                        {{ validateForm.errors.declaration_accepted }}
                    </p>
                </div>
            </div>

            <div
                class="sticky bottom-0 flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-4"
            >
                <SecondaryButton type="button" @click="close">Fechar</SecondaryButton>
                <a
                    v-if="pdfReady && downloadUrl"
                    :href="downloadUrl"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                    Baixar PDF
                </a>
                <SecondaryButton
                    v-if="canRelease && hasVersion && pdfReady"
                    type="button"
                    :disabled="releasing"
                    @click="release"
                >
                    {{ releasing ? 'Liberando…' : 'Liberar nova versão' }}
                </SecondaryButton>
                <PrimaryButton
                    v-if="canRelease && !hasVersion"
                    type="button"
                    :disabled="releasing"
                    @click="release"
                >
                    {{ releaseLabel }}
                </PrimaryButton>
                <PrimaryButton
                    v-if="canValidate && validateUrl"
                    type="button"
                    :disabled="!declarationAccepted || validateForm.processing"
                    @click="validate"
                >
                    {{ validateForm.processing ? 'Validando…' : 'Validar' }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
