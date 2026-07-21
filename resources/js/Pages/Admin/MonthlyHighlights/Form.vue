<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { CameraIcon, PhotoIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    mode: { type: String, required: true },
    highlight: { type: Object, default: null },
    companies: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    selected_company_id: { type: Number, default: null },
    default_year: { type: Number, required: true },
    default_month: { type: Number, required: true },
});

const photoPreview = ref(props.highlight?.photo_url ?? null);
const photoFileName = ref('');
const photoInput = ref(null);
const dragOver = ref(false);

const form = useForm({
    company_id: props.highlight?.company_id ?? props.selected_company_id ?? '',
    person_name: props.highlight?.person_name ?? '',
    category: props.highlight?.category ?? '',
    year: props.highlight?.year ?? props.default_year,
    month: props.highlight?.month ?? props.default_month,
    description: props.highlight?.description ?? '',
    is_published: props.highlight?.is_published ?? false,
    photo: null,
    remove_photo: false,
});

const monthOptions = [
    { value: 1, label: 'Janeiro' },
    { value: 2, label: 'Fevereiro' },
    { value: 3, label: 'Março' },
    { value: 4, label: 'Abril' },
    { value: 5, label: 'Maio' },
    { value: 6, label: 'Junho' },
    { value: 7, label: 'Julho' },
    { value: 8, label: 'Agosto' },
    { value: 9, label: 'Setembro' },
    { value: 10, label: 'Outubro' },
    { value: 11, label: 'Novembro' },
    { value: 12, label: 'Dezembro' },
];

const photoRequired = computed(() => props.mode === 'create');

const photoStatusLabel = computed(() => {
    if (photoFileName.value) {
        return photoFileName.value;
    }
    if (photoPreview.value) {
        return 'Foto atual selecionada';
    }
    return photoRequired.value ? 'Obrigatória para criar o destaque' : 'Opcional';
});

const applyPhotoFile = (file) => {
    if (!file || !file.type.startsWith('image/')) {
        return;
    }

    form.photo = file;
    form.remove_photo = false;
    photoFileName.value = file.name.length > 36 ? `${file.name.slice(0, 28)}…${file.name.slice(-6)}` : file.name;

    if (photoPreview.value && photoPreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(photoPreview.value);
    }

    photoPreview.value = URL.createObjectURL(file);
};

const openPhotoPicker = () => {
    photoInput.value?.click();
};

const onPhotoDrop = (event) => {
    dragOver.value = false;
    const file = event.dataTransfer?.files?.[0] ?? null;
    applyPhotoFile(file);
};

const onPhotoChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    applyPhotoFile(file);
    if (event.target) {
        event.target.value = '';
    }
};

const clearPhoto = () => {
    form.photo = null;
    form.remove_photo = true;
    photoFileName.value = '';
    if (photoPreview.value && photoPreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(photoPreview.value);
    }
    photoPreview.value = null;
    if (photoInput.value) {
        photoInput.value.value = '';
    }
};

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('admin.destaques-mes.store'), { forceFormData: true });
        return;
    }

    form
        .transform((data) => ({
            ...data,
            _method: 'put',
        }))
        .post(route('admin.destaques-mes.update', props.highlight.id), {
            forceFormData: true,
        });
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Novo destaque' : 'Editar destaque'" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.destaques-mes.index')"
                back-label="Destaques do mês"
                :title="mode === 'create' ? 'Novo destaque do mês' : 'Editar destaque do mês'"
                :subtitle="
                    mode === 'edit' && highlight?.company?.name
                        ? highlight.company.name
                        : 'Foto de perfil, categoria e período do reconhecimento'
                "
            />
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>

        <form class="surface-card space-y-5 p-6 sm:p-7" @submit.prevent="submit">
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <InputLabel for="company_id" value="Empresa" />
                    <select
                        id="company_id"
                        v-model="form.company_id"
                        class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        required
                    >
                        <option value="" disabled>Selecione a empresa…</option>
                        <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.company_id" />
                </div>

                <div class="sm:col-span-2">
                    <InputLabel for="person_name" value="Nome do colaborador" />
                    <TextInput
                        id="person_name"
                        v-model="form.person_name"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        maxlength="255"
                        placeholder="Digite o nome completo…"
                    />
                    <InputError class="mt-1" :message="form.errors.person_name" />
                </div>

                <div>
                    <InputLabel for="category" value="Categoria" />
                    <select
                        id="category"
                        v-model="form.category"
                        class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        required
                    >
                        <option value="" disabled>Selecione…</option>
                        <option v-for="cat in categories" :key="cat.value" :value="cat.value">
                            {{ cat.label }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.category" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel for="month" value="Mês" />
                        <select
                            id="month"
                            v-model.number="form.month"
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            required
                        >
                            <option v-for="m in monthOptions" :key="m.value" :value="m.value">
                                {{ m.label }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.month" />
                    </div>
                    <div>
                        <InputLabel for="year" value="Ano" />
                        <TextInput
                            id="year"
                            v-model.number="form.year"
                            type="number"
                            min="2000"
                            max="2100"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.year" />
                    </div>
                </div>
            </div>

            <div>
                <InputLabel value="Foto de perfil" />
                <input
                    ref="photoInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="sr-only"
                    :required="photoRequired && !photoPreview"
                    @change="onPhotoChange"
                >

                <div
                    class="mt-2 rounded-2xl border border-dashed p-4 transition sm:p-5"
                    :class="
                        dragOver
                            ? 'border-talents-400 bg-talents-50/80'
                            : 'border-slate-200 bg-slate-50/60'
                    "
                    @dragenter.prevent="dragOver = true"
                    @dragover.prevent="dragOver = true"
                    @dragleave.prevent="dragOver = false"
                    @drop.prevent="onPhotoDrop"
                >
                    <div class="flex flex-col items-center gap-5 sm:flex-row sm:items-center">
                        <button
                            type="button"
                            class="group relative h-32 w-32 shrink-0 overflow-hidden rounded-full bg-white shadow-sm ring-2 ring-slate-200 transition hover:ring-talents-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500"
                            :aria-label="photoPreview ? 'Alterar foto de perfil' : 'Escolher foto de perfil'"
                            @click="openPhotoPicker"
                        >
                            <img
                                v-if="photoPreview"
                                :src="photoPreview"
                                alt="Pré-visualização da foto"
                                class="h-full w-full object-cover"
                            >
                            <span
                                v-else
                                class="flex h-full w-full flex-col items-center justify-center gap-1 text-slate-400"
                            >
                                <PhotoIcon class="h-9 w-9" aria-hidden="true" />
                                <span class="text-xs font-medium">Sem foto</span>
                            </span>
                            <span
                                class="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-slate-950/55 text-white opacity-0 transition group-hover:opacity-100 group-focus-visible:opacity-100"
                            >
                                <CameraIcon class="h-6 w-6" aria-hidden="true" />
                                <span class="text-xs font-semibold">
                                    {{ photoPreview ? 'Alterar' : 'Escolher' }}
                                </span>
                            </span>
                        </button>

                        <div class="min-w-0 flex-1 text-center sm:text-left">
                            <p class="text-sm font-medium text-slate-800">
                                {{ photoPreview ? 'Foto pronta para o destaque' : 'Arraste uma imagem ou escolha um ficheiro' }}
                            </p>
                            <p class="mt-1 truncate text-xs text-slate-500" :title="photoFileName || undefined">
                                {{ photoStatusLabel }}
                            </p>
                            <p class="mt-1 text-xs text-slate-400">JPG, PNG ou WebP · até 5 MB</p>

                            <div class="mt-3 flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-talents-700 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-400"
                                    @click="openPhotoPicker"
                                >
                                    <CameraIcon class="h-4 w-4" aria-hidden="true" />
                                    {{ photoPreview ? 'Trocar foto' : 'Escolher foto' }}
                                </button>
                                <button
                                    v-if="photoPreview"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-rose-600 shadow-sm transition hover:border-rose-200 hover:bg-rose-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-300"
                                    @click="clearPhoto"
                                >
                                    <TrashIcon class="h-4 w-4" aria-hidden="true" />
                                    Remover
                                </button>
                            </div>
                            <InputError class="mt-2" :message="form.errors.photo" />
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <InputLabel for="description" value="Descrição / motivo (opcional)" />
                <textarea
                    id="description"
                    v-model="form.description"
                    rows="4"
                    maxlength="5000"
                    class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    placeholder="Por que esta pessoa foi destaque neste mês…"
                />
                <InputError class="mt-1" :message="form.errors.description" />
            </div>

            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input
                    v-model="form.is_published"
                    type="checkbox"
                    class="rounded border-slate-300 text-talents-600 focus:ring-talents-500"
                >
                Publicado (visível para uso operacional)
            </label>

            <div class="flex flex-wrap gap-3 pt-2">
                <PrimaryButton type="submit" :disabled="form.processing">
                    {{ form.processing ? 'A guardar…' : mode === 'create' ? 'Criar' : 'Guardar' }}
                </PrimaryButton>
                <Link :href="route('admin.destaques-mes.index')">
                    <SecondaryButton type="button">Cancelar</SecondaryButton>
                </Link>
            </div>
        </form>
    </AdminLayout>
</template>
