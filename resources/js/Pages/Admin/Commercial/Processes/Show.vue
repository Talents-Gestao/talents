<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    process: { type: Object, required: true },
});

const formatDate = (iso) => {
    if (!iso) {
        return '—';
    }
    try {
        return new Date(iso).toLocaleString('pt-BR', {
            dateStyle: 'short',
            timeStyle: 'short',
        });
    } catch {
        return '—';
    }
};
</script>

<template>
    <Head :title="`Processo — ${process.title}`" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.comercial.processos.index')"
                back-label="Processos"
                :title="process.title"
                :subtitle="process.summary || 'Documento oficial do processo comercial'"
            >
                <template #trailing>
                    <div class="flex flex-wrap items-center gap-2">
                        <a
                            v-if="process.has_file"
                            :href="route('admin.comercial.processos.download', process.id)"
                        >
                            <SecondaryButton type="button">Baixar anexo</SecondaryButton>
                        </a>
                        <Link :href="route('admin.comercial.processos.edit', process.id)">
                            <PrimaryButton type="button">Editar</PrimaryButton>
                        </Link>
                    </div>
                </template>
            </FormPageHeader>
        </template>

        <div class="mb-4 flex flex-wrap items-center gap-3 text-xs text-slate-500">
            <span
                class="inline-flex rounded-full px-2 py-0.5 font-semibold ring-1"
                :class="
                    process.is_published
                        ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
                        : 'bg-slate-100 text-slate-600 ring-slate-200'
                "
            >
                {{ process.is_published ? 'Publicado' : 'Rascunho' }}
            </span>
            <span>Atualizado em {{ formatDate(process.updated_at) }}</span>
            <span v-if="process.updated_by?.name">por {{ process.updated_by.name }}</span>
            <span v-if="process.file_name">Anexo: {{ process.file_name }}</span>
        </div>

        <article class="surface-card p-6 sm:p-8">
            <div
                v-if="process.body_html"
                class="commercial-process-body space-y-3 text-sm leading-relaxed text-slate-800 [&_h1]:text-xl [&_h1]:font-semibold [&_h1]:text-slate-900 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-slate-900 [&_h3]:text-base [&_h3]:font-semibold [&_h3]:text-slate-900 [&_li]:my-0.5 [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:my-2 [&_strong]:font-semibold [&_ul]:list-disc [&_ul]:pl-5"
                v-html="process.body_html"
            />
            <p v-else class="text-sm text-slate-500">
                Este processo ainda não tem conteúdo no editor.
                <template v-if="process.has_file">
                    Baixe o anexo oficial ou edite para importar o texto do DOCX.
                </template>
            </p>
        </article>
    </AdminLayout>
</template>
