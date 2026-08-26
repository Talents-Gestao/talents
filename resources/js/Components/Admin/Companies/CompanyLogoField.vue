<script setup>
import FullScreenOverlay from '@/Components/FullScreenOverlay.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    /** URL já persistida (edit/show) */
    existingUrl: { type: String, default: null },
    error: { type: String, default: null },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['change', 'remove']);

const MAX_BYTES = 2 * 1024 * 1024;
const OUTPUT_SIZE = 512;
const ACCEPTED = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

const fileInput = ref(null);
const previewUrl = ref(props.existingUrl || null);
const localError = ref('');
const cropOpen = ref(false);
const cropImageEl = ref(null);
const cropSourceUrl = ref(null);
const cropper = ref(null);
const applying = ref(false);

watch(
    () => props.existingUrl,
    (url) => {
        if (previewUrl.value && String(previewUrl.value).startsWith('blob:')) {
            return;
        }
        previewUrl.value = url || null;
    },
);

const revokeIfBlob = (url) => {
    if (url && String(url).startsWith('blob:')) {
        URL.revokeObjectURL(url);
    }
};

const destroyCropper = () => {
    if (cropper.value) {
        cropper.value.destroy();
        cropper.value = null;
    }
};

const closeCrop = () => {
    destroyCropper();
    cropOpen.value = false;
    revokeIfBlob(cropSourceUrl.value);
    cropSourceUrl.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const openFilePicker = () => {
    localError.value = '';
    fileInput.value?.click();
};

const onFileSelected = async (event) => {
    localError.value = '';
    const file = event.target.files?.[0] ?? null;
    if (!file) {
        return;
    }

    if (!ACCEPTED.includes(file.type) && !/\.(jpe?g|png|webp)$/i.test(file.name)) {
        localError.value = 'Use um arquivo JPG, PNG ou WebP.';
        event.target.value = '';
        return;
    }

    if (file.size > MAX_BYTES) {
        localError.value = 'O arquivo original deve ter no máximo 2 MB.';
        event.target.value = '';
        return;
    }

    destroyCropper();
    revokeIfBlob(cropSourceUrl.value);
    cropSourceUrl.value = URL.createObjectURL(file);
    cropOpen.value = true;

    await nextTick();
    if (!cropImageEl.value) {
        return;
    }

    cropper.value = new Cropper(cropImageEl.value, {
        aspectRatio: 1,
        viewMode: 1,
        dragMode: 'move',
        autoCropArea: 1,
        responsive: true,
        background: false,
        guides: true,
        center: true,
        highlight: false,
        cropBoxMovable: true,
        cropBoxResizable: true,
        toggleDragModeOnDblclick: false,
    });
};

const canvasToFile = (canvas) =>
    new Promise((resolve, reject) => {
        const preferWebp = canvas.toDataURL('image/webp').startsWith('data:image/webp');
        const mime = preferWebp ? 'image/webp' : 'image/png';
        const ext = preferWebp ? 'webp' : 'png';

        canvas.toBlob(
            (blob) => {
                if (!blob) {
                    reject(new Error('Falha ao gerar a imagem recortada.'));
                    return;
                }
                resolve(new File([blob], `logo.${ext}`, { type: mime, lastModified: Date.now() }));
            },
            mime,
            0.92,
        );
    });

const applyCrop = async () => {
    if (!cropper.value || applying.value) {
        return;
    }
    applying.value = true;
    localError.value = '';

    try {
        const canvas = cropper.value.getCroppedCanvas({
            width: OUTPUT_SIZE,
            height: OUTPUT_SIZE,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        });

        if (!canvas) {
            throw new Error('Não foi possível recortar a imagem.');
        }

        const file = await canvasToFile(canvas);
        revokeIfBlob(previewUrl.value);
        previewUrl.value = URL.createObjectURL(file);
        emit('change', file);
        closeCrop();
    } catch (e) {
        localError.value = e?.message || 'Não foi possível aplicar o recorte.';
    } finally {
        applying.value = false;
    }
};

const removeLogo = () => {
    localError.value = '';
    revokeIfBlob(previewUrl.value);
    previewUrl.value = null;
    emit('remove');
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

onBeforeUnmount(() => {
    destroyCropper();
    revokeIfBlob(previewUrl.value);
    revokeIfBlob(cropSourceUrl.value);
});
</script>

<template>
    <div>
        <InputLabel value="Logo da empresa" />
        <p class="mt-0.5 text-xs text-slate-500">
            JPG, PNG ou WebP · máx. 2 MB · recorte quadrado (1:1), exportado até 512×512.
        </p>

        <input
            ref="fileInput"
            type="file"
            class="hidden"
            accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
            :disabled="disabled"
            @change="onFileSelected"
        />

        <div class="mt-3 flex flex-wrap items-center gap-4">
            <div
                class="flex h-24 w-24 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-50"
            >
                <img
                    v-if="previewUrl"
                    :src="previewUrl"
                    alt="Pré-visualização do logo"
                    class="h-full w-full object-cover"
                />
                <span v-else class="px-2 text-center text-xs text-slate-400">Sem logo</span>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-50"
                    :disabled="disabled"
                    @click="openFilePicker"
                >
                    {{ previewUrl ? 'Trocar logo' : 'Escolher imagem' }}
                </button>
                <button
                    v-if="previewUrl"
                    type="button"
                    class="rounded-xl border border-rose-200 bg-white px-3 py-2 text-sm font-semibold text-rose-700 shadow-sm transition hover:bg-rose-50 disabled:opacity-50"
                    :disabled="disabled"
                    @click="removeLogo"
                >
                    Remover logo
                </button>
            </div>
        </div>

        <p v-if="localError" class="mt-2 text-sm text-rose-600">{{ localError }}</p>
        <InputError class="mt-2" :message="error" />

        <FullScreenOverlay :show="cropOpen" @close="closeCrop">
            <div class="flex w-full max-w-xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h3 class="text-lg font-semibold text-slate-900">Recortar</h3>
                    <p class="mt-1 text-sm text-slate-600">Ajuste o enquadramento no quadrado 1:1.</p>
                </div>
                <div class="max-h-[60vh] bg-slate-900">
                    <img
                        v-if="cropSourceUrl"
                        ref="cropImageEl"
                        :src="cropSourceUrl"
                        alt="Imagem para recortar"
                        class="block max-h-[60vh] w-full"
                    />
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        :disabled="applying"
                        @click="closeCrop"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-talents-600 px-4 py-2 text-sm font-semibold text-white hover:bg-talents-700 disabled:opacity-50"
                        :disabled="applying"
                        @click="applyCrop"
                    >
                        {{ applying ? 'A aplicar…' : 'Aplicar' }}
                    </button>
                </div>
            </div>
        </FullScreenOverlay>
    </div>
</template>
