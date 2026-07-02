<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { formatRelativeDate } from '@/utils/dateOnly';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    notices: Object,
});

function markRead(notice) {
    if (notice.read) return;

    router.post(route('client.notices.mark-read', notice.id), {}, {
        preserveScroll: true,
        preserveState: false,
    });
}

function markAllRead() {
    router.post(route('client.notices.mark-all-read'), {}, { preserveScroll: true });
}

function formatPublished(iso) {
    if (!iso) return '';
    const date = iso.slice(0, 10);
    return formatRelativeDate(date);
}
</script>

<template>
    <Head title="Avisos" />

    <ClientLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-talents-900">Avisos e novidades</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Atualizações do calendário estratégico e comunicados da Talents.
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    @click="markAllRead"
                >
                    Marcar todos como lidos
                </button>
            </div>
        </template>

        <div class="space-y-3">
            <article
                v-for="notice in notices.data"
                :key="notice.id"
                class="surface-card cursor-pointer overflow-hidden p-5 transition hover:shadow-md"
                :class="notice.read ? 'opacity-90' : 'ring-1 ring-rose-200/80'"
                @click="markRead(notice)"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold text-slate-900">{{ notice.title }}</h3>
                            <span
                                v-if="!notice.read"
                                class="inline-flex h-2 w-2 shrink-0 rounded-full bg-rose-600"
                                title="Não lido"
                                aria-hidden="true"
                            />
                        </div>
                        <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-600">
                            {{ notice.body }}
                        </p>
                    </div>
                    <time class="shrink-0 text-xs text-slate-500" :datetime="notice.published_at">
                        {{ formatPublished(notice.published_at) }}
                    </time>
                </div>
            </article>

            <p v-if="!notices.data?.length" class="surface-card px-6 py-10 text-center text-sm text-slate-500">
                Nenhum aviso por enquanto.
            </p>
        </div>

        <div v-if="notices.links?.length > 3" class="mt-6 flex flex-wrap justify-center gap-2">
            <Link
                v-for="link in notices.links"
                :key="link.label"
                :href="link.url || '#'"
                class="rounded-lg px-3 py-1.5 text-sm"
                :class="link.active ? 'bg-talents-700 text-white' : 'text-slate-600 hover:bg-slate-100'"
                v-html="link.label"
            />
        </div>
    </ClientLayout>
</template>
