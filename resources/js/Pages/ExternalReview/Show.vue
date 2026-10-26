<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    share: { type: Object, required: true },
});

const commentForm = useForm({ body: '' });
const approveForm = useForm({});

const sendComment = () => {
    commentForm.post(route('external-review.comment', props.share.version_id), {
        preserveScroll: true,
        onSuccess: () => commentForm.reset(),
    });
};

const approve = () => {
    if (!window.confirm('Confirmar a aprovação desta versão? O registro não pode ser desfeito.')) {
        return;
    }
    approveForm.post(route('external-review.approve', props.share.version_id), { preserveScroll: true });
};
</script>

<template>
    <Head :title="share.document_label" />

    <main class="mx-auto max-w-3xl px-4 py-10">
        <Link :href="route('external-review.index')" class="text-sm underline">← Documentos para revisão</Link>

        <h1 class="mt-4 text-xl font-semibold">{{ share.document_label }} · {{ share.public_identifier }}</h1>
        <p class="text-sm opacity-70">
            {{ share.company_name }} · versão {{ share.version_number }} · válido até {{ share.expires_at }}
        </p>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <a
                v-if="share.pdf_ready"
                :href="route('external-review.download', share.version_id)"
                class="rounded border px-3 py-2 text-sm"
            >
                Baixar PDF
            </a>
            <p v-else class="text-sm opacity-70">O PDF desta versão ainda não está disponível.</p>

            <p v-if="share.approved" class="text-sm font-medium">Versão aprovada.</p>
            <button
                v-else
                type="button"
                class="rounded border px-3 py-2 text-sm"
                :disabled="approveForm.processing"
                @click="approve"
            >
                Aprovar versão
            </button>
        </div>

        <section class="mt-8">
            <h2 class="mb-2 font-medium">Comentários</h2>
            <p v-if="!share.reviews.length" class="text-sm opacity-70">Nenhum registro ainda.</p>
            <ul v-else class="space-y-3">
                <li v-for="review in share.reviews" :key="review.id" class="rounded border p-3 text-sm">
                    <p class="opacity-70">{{ review.author }} · {{ review.created_at }}</p>
                    <p>{{ review.kind === 'approval' ? 'Aprovou esta versão.' : review.body }}</p>
                </li>
            </ul>

            <form class="mt-4 space-y-2" @submit.prevent="sendComment">
                <label for="body" class="block text-sm">Novo comentário</label>
                <textarea id="body" v-model="commentForm.body" rows="4" class="w-full rounded border p-2" />
                <p v-if="commentForm.errors.body" class="text-sm text-red-600">{{ commentForm.errors.body }}</p>
                <button type="submit" class="rounded border px-3 py-2 text-sm" :disabled="commentForm.processing">
                    Enviar comentário
                </button>
            </form>
        </section>
    </main>
</template>
