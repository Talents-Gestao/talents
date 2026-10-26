<script setup>
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    shares: { type: Array, default: () => [] },
});

const logout = () => router.post(route('logout'));
</script>

<template>
    <Head title="Documentos para revisão" />

    <main class="mx-auto max-w-3xl px-4 py-10">
        <header class="mb-6 flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold">Documentos para revisão</h1>
            <button type="button" class="text-sm underline" @click="logout">Sair</button>
        </header>

        <p v-if="!shares.length" class="text-sm opacity-70">
            Nenhum documento compartilhado com você no momento.
        </p>

        <ul v-else class="divide-y rounded-lg border">
            <li v-for="share in shares" :key="share.version_id" class="flex items-center justify-between gap-4 p-4">
                <div class="min-w-0">
                    <p class="font-medium">{{ share.document_label }} · {{ share.public_identifier }}</p>
                    <p class="text-sm opacity-70">
                        {{ share.company_name }} · compartilhado em {{ share.shared_at }} · válido até {{ share.expires_at }}
                    </p>
                </div>
                <Link :href="route('external-review.show', share.version_id)" class="shrink-0 text-sm underline">
                    Abrir
                </Link>
            </li>
        </ul>
    </main>
</template>
