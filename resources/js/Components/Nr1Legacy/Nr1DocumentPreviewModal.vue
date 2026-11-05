<script setup>
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { computed, onUnmounted, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    document: { type: Object, default: null },
    /** Download da versão congelada (attachment). */
    downloadUrl: { type: String, default: null },
    /** Pré-visualização ao vivo (inline) — o modal sempre usa esta URL para exibir o PDF. */
    previewUrl: { type: String, default: null },
    releaseUrl: { type: String, default: null },
    validateUrl: { type: String, default: null },
    canValidate: { type: Boolean, default: false },
    /** Link opcional para completar o cadastro técnico (campos do PDF). */
    registrationUrl: { type: String, default: null },
    /** Chave em sessionStorage para reabrir o modal após o redirect da liberação. */
    persistKey: { type: String, default: null },
    /** Publicação direta no painel NR-1 da empresa, sem a etapa de validação. */
    publishMode: { type: Boolean, default: false },
    declarationText: {
        type: String,
        default:
            'Declaro que revisei este documento e que a validação se refere ao conteúdo congelado desta versão.',
    },
});

const emit = defineEmits(['close', 'released', 'validated']);

const releasing = ref(false);
const declarationAccepted = ref(false);
const loadingPdf = ref(false);
const pdfError = ref(null);
const pdfObjectUrl = ref(null);

const validateForm = useForm({
    declaration_accepted: false,
});

const hasVersion = computed(() => Boolean(props.document?.version_number));

const versionNumber = computed(() => props.document?.version_number ?? null);

const publishedToCompany = computed(() => props.publishMode && props.document?.ready === true);

const statusLabel = computed(() => {
    if (props.publishMode) {
        if (!hasVersion.value) {
            return 'Ainda não publicado';
        }
        if (publishedToCompany.value) {
            return `Publicado no painel da empresa · versão ${versionNumber.value}`;
        }
        return `Publicação em preparação · versão ${versionNumber.value}`;
    }
    if (!hasVersion.value) {
        return 'Prévia ao vivo';
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
    if (publishedToCompany.value || props.document?.validated) {
        return 'bg-emerald-50 text-emerald-900 ring-emerald-200';
    }
    if (!hasVersion.value) {
        return 'bg-slate-100 text-slate-800 ring-slate-200';
    }
    return 'bg-sky-50 text-sky-950 ring-sky-200';
});

const pdfReady = computed(() => props.document?.ready === true || props.document?.pdf_status === 'ready');

const canRelease = computed(() => Boolean(props.releaseUrl));

const releaseLabel = computed(() => {
    if (releasing.value) {
        return props.publishMode ? 'Publicando…' : 'Liberando…';
    }
    if (props.publishMode) {
        return publishedToCompany.value ? 'Substituir publicação' : 'Publicar para a empresa';
    }
    return hasVersion.value ? 'Liberar nova versão' : 'Liberar para validação';
});

const revokePdfUrl = () => {
    if (pdfObjectUrl.value) {
        URL.revokeObjectURL(pdfObjectUrl.value);
        pdfObjectUrl.value = null;
    }
};

/** Sempre prévia ao vivo no iframe — evita attachment no modal e não exige liberar para ver. */
const pdfSourceUrl = computed(() => props.previewUrl || null);

const loadPdf = async () => {
    const url = pdfSourceUrl.value;
    if (!url) {
        pdfError.value = 'Não há URL de pré-visualização disponível.';
        return;
    }

    loadingPdf.value = true;
    pdfError.value = null;
    revokePdfUrl();

    try {
        const response = await window.axios.get(url, {
            responseType: 'blob',
            headers: { Accept: 'application/pdf' },
        });
        const contentType = String(response.headers['content-type'] ?? '');
        if (!contentType.includes('pdf')) {
            throw new Error('Resposta inválida ao gerar o PDF.');
        }
        const blob = new Blob([response.data], { type: 'application/pdf' });
        pdfObjectUrl.value = URL.createObjectURL(blob);
    } catch (e) {
        let message =
            e?.message || 'Não foi possível carregar o PDF. Tente de novo.';
        try {
            const data = e?.response?.data;
            if (data instanceof Blob) {
                const text = await data.text();
                const parsed = JSON.parse(text);
                if (parsed?.message) {
                    message = parsed.message;
                }
            } else if (data?.message) {
                message = data.message;
            }
        } catch {
            // mantém message padrão
        }
        pdfError.value = message;
    } finally {
        loadingPdf.value = false;
    }
};

watch(
    () => props.show,
    async (open) => {
        if (!open) {
            declarationAccepted.value = false;
            validateForm.reset();
            validateForm.clearErrors();
            pdfError.value = null;
            revokePdfUrl();
            return;
        }
        await loadPdf();
    },
);

watch(
    () => [props.previewUrl, props.document?.version_number, props.document?.ready, props.document?.pdf_status],
    async () => {
        if (props.show) {
            await loadPdf();
        }
    },
);

onUnmounted(() => {
    revokePdfUrl();
});

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
                            {{ hasVersion ? `v${versionNumber}` : 'Prévia' }}
                        </dd>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2">
                        <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-500">PDF</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-slate-900">
                            {{
                                loadingPdf
                                    ? 'Carregando…'
                                    : pdfObjectUrl
                                      ? 'Visível'
                                      : pdfReady
                                        ? 'Pronto'
                                        : 'Prévia ao vivo'
                            }}
                        </dd>
                    </div>
                    <div v-if="!publishMode" class="rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2">
                        <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Validação</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-slate-900">
                            {{
                                !hasVersion
                                    ? 'Liberar para validar'
                                    : document?.validated
                                      ? 'Validado'
                                      : document?.validatable
                                        ? 'Pendente'
                                        : '—'
                            }}
                        </dd>
                    </div>
                    <div v-else class="rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2">
                        <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Painel da empresa</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-slate-900">
                            {{ publishedToCompany ? 'Publicado' : 'Não publicado' }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="min-h-0 flex-1 overflow-auto px-6 py-4">
                <div
                    v-if="loadingPdf"
                    class="flex h-[min(55vh,32rem)] items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50 text-sm text-slate-600"
                >
                    Gerando pré-visualização do PDF…
                </div>
                <div
                    v-else-if="pdfError"
                    class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950"
                >
                    <p>{{ pdfError }}</p>
                    <SecondaryButton type="button" class="mt-3" @click="loadPdf">Tentar de novo</SecondaryButton>
                </div>
                <iframe
                    v-else-if="pdfObjectUrl"
                    :src="pdfObjectUrl"
                    :title="title"
                    class="h-[min(55vh,32rem)] w-full rounded-xl border border-slate-200 bg-white"
                />

                <div
                    v-if="!publishMode && canValidate && validateUrl && hasVersion"
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
                    v-if="registrationUrl"
                    :href="registrationUrl"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                    Cadastro técnico
                </a>
                <a
                    v-if="pdfReady && downloadUrl"
                    :href="downloadUrl"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                    {{ publishMode ? 'Baixar PDF publicado' : 'Baixar PDF liberado' }}
                </a>
                <SecondaryButton
                    v-if="canRelease && !publishMode"
                    type="button"
                    :disabled="releasing"
                    @click="release"
                >
                    {{ releaseLabel }}
                </SecondaryButton>
                <PrimaryButton
                    v-if="publishMode && canRelease"
                    type="button"
                    :disabled="releasing"
                    @click="release"
                >
                    {{ releaseLabel }}
                </PrimaryButton>
                <PrimaryButton
                    v-if="!publishMode && canValidate && validateUrl && hasVersion"
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
