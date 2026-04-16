<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    submissions: Object,
});

function mailErrorPresent(value) {
    return value !== null && value !== undefined && String(value).trim() !== '';
}

function mailErrorText(value) {
    if (value === null || value === undefined) {
        return '';
    }
    return typeof value === 'string' ? value : String(value);
}
</script>

<template>
    <Head title="Interessados — Landing" />

    <AdminLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-900">Interessados (landing)</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Registros enviados pelo formulário “Quer conhecer mais a Talents?” na página inicial pública.
                </p>
            </div>
        </template>

        <div class="surface-card overflow-hidden">
            <div v-if="!submissions.data.length" class="px-4 py-10 text-center text-sm text-gray-600">
                Nenhum envio ainda.
            </div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm text-gray-900">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">Data</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">Nome</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">E-mail</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">Telefone</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">Empresa</th>
                            <th class="min-w-[12rem] px-4 py-3 text-left font-medium text-gray-700">Mensagem</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">E-mail aviso</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="s in submissions.data" :key="s.id">
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                {{
                                    s.created_at
                                        ? new Date(s.created_at).toLocaleString('pt-BR', {
                                              dateStyle: 'short',
                                              timeStyle: 'short',
                                          })
                                        : '—'
                                }}
                            </td>
                            <td class="px-4 py-3">{{ s.name }}</td>
                            <td class="px-4 py-3">
                                <a :href="'mailto:' + s.email" class="font-medium text-talents-700 hover:underline">{{
                                    s.email
                                }}</a>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ s.phone || '—' }}</td>
                            <td class="max-w-xs truncate px-4 py-3">{{ s.company || '—' }}</td>
                            <td class="max-w-md px-4 py-3">
                                <span class="line-clamp-3 whitespace-pre-wrap">{{ s.message || '—' }}</span>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <span
                                    v-if="s.mail_sent_at"
                                    class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800"
                                >
                                    Enviado
                                </span>
                                <span
                                    v-else-if="mailErrorPresent(s.mail_error)"
                                    class="inline-flex max-w-[14rem] flex-col gap-1"
                                    :title="mailErrorText(s.mail_error)"
                                >
                                    <span
                                        class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900"
                                    >
                                        Falha SMTP
                                    </span>
                                    <span class="text-xs text-gray-500 line-clamp-2">{{ mailErrorText(s.mail_error) }}</span>
                                </span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="submissions.data.length && submissions.links && submissions.links.length > 3"
                class="flex flex-wrap justify-end gap-2 border-t border-gray-200 px-4 py-3"
            >
                <template v-for="(link, i) in submissions.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded px-2 py-1 text-sm"
                        :class="link.active ? 'bg-talents-600 text-white' : 'text-talents-700 hover:bg-talents-50'"
                        preserve-scroll
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="cursor-not-allowed rounded px-2 py-1 text-sm text-gray-400"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </AdminLayout>
</template>
