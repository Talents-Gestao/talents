<script setup>
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    videos: { type: Object, required: true },
});

const form = useForm({
    intro_video_required: Boolean(props.videos.intro_video_required),
    closing_video_required: Boolean(props.videos.closing_video_required),
    intro_video_id: props.videos.intro_video_id ?? '',
    closing_video_id: props.videos.closing_video_id ?? '',
});

const save = () => {
    form
        .transform((data) => ({
            ...data,
            intro_video_id: data.intro_video_id === '' ? null : Number(data.intro_video_id),
            closing_video_id: data.closing_video_id === '' ? null : Number(data.closing_video_id),
        }))
        .post(props.videos.save_url, { preserveScroll: true });
};
</script>

<template>
    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="videos-title">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="videos-title" class="text-base font-semibold text-talents-900">Vídeos da jornada</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Exija um vídeo de abertura e/ou de encerramento. O colaborador precisa assistir até o fim para avançar.
                </p>
            </div>
            <Link v-if="videos.library_url" :href="videos.library_url" class="text-xs font-semibold text-talents-700 underline">
                Gerir biblioteca
            </Link>
        </div>

        <form class="mt-4 space-y-4" @submit.prevent="save">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-lg border border-slate-100 bg-slate-50/70 p-3">
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                        <input v-model="form.intro_video_required" type="checkbox" class="rounded border-slate-300 text-talents-600 focus:ring-talents-500" />
                        Exigir vídeo de abertura
                    </label>
                    <label for="intro-video-id" class="mt-3 block text-xs font-medium text-slate-600">Vídeo de abertura</label>
                    <select
                        id="intro-video-id"
                        v-model="form.intro_video_id"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm"
                        :disabled="!form.intro_video_required && !videos.intro_options?.length"
                    >
                        <option value="">Selecione</option>
                        <option v-for="opt in videos.intro_options" :key="opt.id" :value="opt.id">{{ opt.title }}</option>
                    </select>
                    <p v-if="form.errors.intro_video_id" class="mt-1 text-xs text-red-600">{{ form.errors.intro_video_id }}</p>
                    <p v-if="!videos.intro_options?.length" class="mt-1 text-xs text-amber-700">Nenhum vídeo de abertura ativo na biblioteca.</p>
                </div>

                <div class="rounded-lg border border-slate-100 bg-slate-50/70 p-3">
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                        <input v-model="form.closing_video_required" type="checkbox" class="rounded border-slate-300 text-talents-600 focus:ring-talents-500" />
                        Exigir vídeo de encerramento
                    </label>
                    <label for="closing-video-id" class="mt-3 block text-xs font-medium text-slate-600">Vídeo de encerramento</label>
                    <select
                        id="closing-video-id"
                        v-model="form.closing_video_id"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm"
                        :disabled="!form.closing_video_required && !videos.closing_options?.length"
                    >
                        <option value="">Selecione</option>
                        <option v-for="opt in videos.closing_options" :key="opt.id" :value="opt.id">{{ opt.title }}</option>
                    </select>
                    <p v-if="form.errors.closing_video_id" class="mt-1 text-xs text-red-600">{{ form.errors.closing_video_id }}</p>
                    <p v-if="!videos.closing_options?.length" class="mt-1 text-xs text-amber-700">Nenhum vídeo de encerramento ativo na biblioteca.</p>
                </div>
            </div>

            <button
                type="submit"
                class="rounded-md bg-talents-700 px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-60"
                :disabled="form.processing"
            >
                Salvar vídeos da jornada
            </button>
        </form>
    </section>
</template>
