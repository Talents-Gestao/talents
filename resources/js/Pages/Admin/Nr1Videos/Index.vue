<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { confirmDialog } from '@/composables/useConfirmDialog';

const props = defineProps({
    videos: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    maxUploadMb: { type: Number, default: 200 },
    urls: { type: Object, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const form = useForm({
    title: '',
    role: 'intro',
    file: null,
});

const onFile = (event) => {
    form.file = event.target.files?.[0] ?? null;
};

const submit = () => {
    form.post(props.urls.store, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.role = 'intro';
            form.file = null;
            const input = document.getElementById('nr1-video-file');
            if (input) input.value = '';
        },
    });
};

const editingId = ref(null);
const editTitle = ref('');
const editActive = ref(true);

const startEdit = (video) => {
    editingId.value = video.id;
    editTitle.value = video.title;
    editActive.value = video.is_active;
};

const saveEdit = (video) => {
    router.put(
        route('admin.nr1-videos.update', video.id),
        { title: editTitle.value, is_active: editActive.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                editingId.value = null;
            },
        },
    );
};

const onReplaceFile = (video, event) => {
    const file = event.target.files?.[0] ?? null;
    if (!file) return;
    const data = new FormData();
    data.append('file', file);
    router.post(route('admin.nr1-videos.replace', video.id), data, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            event.target.value = '';
        },
    });
};

const remove = async (video) => {
    if (!(await confirmDialog(`Remover o vídeo «${video.title}» da biblioteca? Campanhas que o usavam deixam de exigir essa etapa.`))) {
        return;
    }
    router.delete(route('admin.nr1-videos.destroy', video.id), { preserveScroll: true });
};

const formatSize = (bytes) => {
    if (!bytes) return '—';
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};
</script>

<template>
    <Head title="Vídeos NR-1" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-900">Vídeos da jornada NR-1</h2>
        </template>

        <div class="space-y-6">
            <div
                v-if="flashSuccess"
                class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3 text-sm text-emerald-900"
            >
                {{ flashSuccess }}
            </div>
            <div
                v-if="flashError"
                class="rounded-2xl border border-red-200 bg-red-50 px-5 py-3 text-sm text-red-900"
            >
                {{ flashError }}
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Adicionar vídeo permanente</h3>
                <p class="mt-1 text-sm text-slate-600">
                    Os vídeos ficam na biblioteca da Talents e podem ser exigidos em qualquer campanha NR-1 (abertura ou encerramento).
                    Formatos: MP4 ou WebM · até {{ maxUploadMb }} MB.
                </p>

                <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                    <div>
                        <label for="nr1-video-title" class="text-xs font-medium text-slate-600">Título</label>
                        <input
                            id="nr1-video-title"
                            v-model="form.title"
                            type="text"
                            required
                            maxlength="255"
                            class="mt-1 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                        <p v-if="form.errors.title" class="mt-1 text-xs text-red-700">{{ form.errors.title }}</p>
                    </div>
                    <div>
                        <label for="nr1-video-role" class="text-xs font-medium text-slate-600">Etapa</label>
                        <select
                            id="nr1-video-role"
                            v-model="form.role"
                            class="mt-1 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        >
                            <option v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</option>
                        </select>
                        <p v-if="form.errors.role" class="mt-1 text-xs text-red-700">{{ form.errors.role }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="nr1-video-file" class="text-xs font-medium text-slate-600">Arquivo</label>
                        <input
                            id="nr1-video-file"
                            type="file"
                            accept="video/mp4,video/webm,.mp4,.webm"
                            required
                            class="mt-1 block w-full text-sm text-slate-700"
                            @change="onFile"
                        />
                        <p v-if="form.errors.file" class="mt-1 text-xs text-red-700">{{ form.errors.file }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <button
                            type="submit"
                            class="rounded-md bg-talents-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-talents-700 disabled:opacity-60"
                            :disabled="form.processing"
                        >
                            Enviar para a biblioteca
                        </button>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h3 class="text-base font-semibold text-slate-900">Biblioteca</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ videos.length }} vídeo{{ videos.length === 1 ? '' : 's' }}</p>
                </div>

                <div v-if="!videos.length" class="px-5 py-10 text-center text-sm text-slate-500">
                    Nenhum vídeo cadastrado ainda.
                </div>

                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="video in videos" :key="video.id" class="px-5 py-4">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0 flex-1 space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                        :class="video.role === 'intro' ? 'bg-talents-50 text-talents-800' : 'bg-slate-100 text-slate-700'"
                                    >
                                        {{ video.role_label }}
                                    </span>
                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                        :class="video.is_active ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'"
                                    >
                                        {{ video.is_active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </div>

                                <template v-if="editingId === video.id">
                                    <input
                                        v-model="editTitle"
                                        type="text"
                                        class="w-full max-w-md rounded-lg border-slate-200 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                    />
                                    <label class="flex items-center gap-2 text-sm text-slate-700">
                                        <input v-model="editActive" type="checkbox" class="rounded border-slate-300 text-talents-600 focus:ring-talents-500" />
                                        Disponível para novas campanhas
                                    </label>
                                    <div class="flex gap-2">
                                        <button
                                            type="button"
                                            class="rounded-md bg-talents-600 px-3 py-1.5 text-xs font-semibold text-white"
                                            @click="saveEdit(video)"
                                        >
                                            Salvar
                                        </button>
                                        <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700" @click="editingId = null">
                                            Cancelar
                                        </button>
                                    </div>
                                </template>
                                <template v-else>
                                    <h4 class="truncate text-sm font-semibold text-slate-900">{{ video.title }}</h4>
                                    <p class="text-xs text-slate-500">{{ video.original_name }} · {{ formatSize(video.size_bytes) }}</p>
                                </template>

                                <video
                                    :src="route('admin.nr1-videos.stream', video.id)"
                                    controls
                                    preload="metadata"
                                    class="mt-2 max-h-56 w-full max-w-xl rounded-lg border border-slate-200 bg-black"
                                >
                                    Seu navegador não reproduz este vídeo.
                                </video>
                            </div>

                            <div class="flex shrink-0 flex-wrap gap-2">
                                <button
                                    v-if="editingId !== video.id"
                                    type="button"
                                    class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                    @click="startEdit(video)"
                                >
                                    Editar
                                </button>
                                <label class="cursor-pointer rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                    Substituir arquivo
                                    <input type="file" accept="video/mp4,video/webm,.mp4,.webm" class="hidden" @change="onReplaceFile(video, $event)" />
                                </label>
                                <button
                                    type="button"
                                    class="rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100"
                                    @click="remove(video)"
                                >
                                    Remover
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </AdminLayout>
</template>
