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

const statusLabel = computed(() => {
    if (!props.document?.version_number) {
        return 'Ainda não liberado';
    }
    if (props.document.validated) {
        return `Validado (v${props.document.version_number})`;
    }
    if (props.document.validatable) {
        return `Liberado — pendente de validação (v${props.document.version_number})`;
    }
    return `Versão ${props.document.version_number}`;
});

const pdfReady = computed(() => props.document?.ready === true || props.document?.pdf_status === 'ready');

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

const release = () => {
    if (!props.releaseUrl || releasing.value) {
        return;
    }
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
        },
    );
};

const validate = () => {
    if (!props.validateUrl || !props.canValidate) {
        return;
    }
    validateForm.declaration_accepted = declarationAccepted.value;
    validateForm.post(props.validateUrl, {
        preserveScroll: true,
        onSuccess: () => {
            declarationAccepted.value = false;
            validateForm.reset();
            emit('validated');
        },
    });
};
</script>

<template>
    <Modal :show="show" max-width="5xl" @close="close">
        <div class="flex max-h-[min(90vh,52rem)] flex-col">
            <div class="border-b border-slate-100 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900">{{ title }}</h2>
                <p v-if="description" class="mt-1 text-sm text-gray-600">{{ description }}</p>
                <p class="mt-2 text-sm font-medium text-slate-800">{{ statusLabel }}</p>
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
                    class="flex h-40 items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 text-center text-sm text-slate-600"
                >
                    <template v-if="!document?.version_number">
                        Ainda não há versão liberada. Use «Liberar» para gerar o documento.
                    </template>
                    <template v-else>
                        O PDF desta versão ainda não está pronto. Tente liberar novamente em instantes.
                    </template>
                </div>

                <div
                    v-if="canValidate && validateUrl"
                    class="mt-4 rounded-xl border border-talents-200 bg-talents-50/60 p-4"
                >
                    <p class="text-sm font-semibold text-talents-950">Validar (Talents)</p>
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
                <PrimaryButton
                    v-if="releaseUrl && !document?.version_number"
                    type="button"
                    :disabled="releasing"
                    @click="release"
                >
                    {{ releasing ? 'Liberando…' : 'Liberar' }}
                </PrimaryButton>
                <PrimaryButton
                    v-else-if="releaseUrl && document?.validatable === false && !document?.validated"
                    type="button"
                    :disabled="releasing"
                    @click="release"
                >
                    {{ releasing ? 'Liberando…' : 'Liberar nova versão' }}
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
