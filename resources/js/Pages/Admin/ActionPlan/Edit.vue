<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    company: Object,
    survey: Object,
    plan: { type: Object, default: null },
    items: { type: Array, default: () => [] },
});

const form = useForm({
    items:
        props.items.length > 0
            ? props.items.map((i) => ({ title: i.title, description: i.description ?? '' }))
            : [{ title: '', description: '' }],
});

const addRow = () => {
    form.items.push({ title: '', description: '' });
};

const removeRow = (index) => {
    form.items.splice(index, 1);
    if (form.items.length === 0) {
        form.items.push({ title: '', description: '' });
    }
};

const submit = () => {
    form
        .transform((data) => ({
            items: data.items.filter((row) => String(row.title).trim() !== ''),
        }))
        .put(route('admin.companies.surveys.action-plan.update', [props.company.id, props.survey.id]));
};
</script>

<template>
    <Head :title="`Plano de ação — ${survey.title}`" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-900">Plano de ação (NR-1)</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ company.name }} — {{ survey.title }}
                    </p>
                </div>
                <Link
                    :href="route('admin.companies.show', company.id)"
                    class="text-sm font-medium text-talents-700 hover:underline"
                >
                    Voltar à empresa
                </Link>
            </div>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>

        <div class="mb-6 rounded-lg border border-sky-100 bg-sky-50/80 p-4 text-sm text-sky-950">
            <p>
                Preencha os itens abaixo. Ao salvar com pelo menos um título preenchido, o plano fica
                <strong>visível para a empresa</strong> na página de Plano de ação da pesquisa. Itens vazios são ignorados. Para ocultar o
                plano do cliente, remova todos os itens e salve.
            </p>
            <p v-if="plan?.admin_published_at" class="mt-2 text-xs text-sky-900/80">
                Última publicação: {{ plan.admin_published_at }}
            </p>
        </div>

        <form class="space-y-6 rounded-xl border border-gray-200 bg-white p-6 text-gray-900 shadow-sm" @submit.prevent="submit">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-talents-800">Itens do plano</h3>
                <button type="button" class="text-sm font-medium text-talents-700 hover:underline" @click="addRow">+ Adicionar item</button>
            </div>

            <div v-for="(row, index) in form.items" :key="index" class="rounded-lg border border-gray-200 p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="grid flex-1 gap-3 sm:grid-cols-1">
                        <div>
                            <InputLabel :for="'title-' + index" value="Título" />
                            <TextInput
                                :id="'title-' + index"
                                v-model="row.title"
                                class="mt-1 block w-full"
                                placeholder="Ex.: Revisar dimensão demanda psicológica"
                            />
                        </div>
                        <div>
                            <InputLabel :for="'desc-' + index" value="Descrição" />
                            <textarea
                                :id="'desc-' + index"
                                v-model="row.description"
                                rows="3"
                                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                placeholder="Orientações e próximos passos sugeridos pela Talents..."
                            />
                        </div>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded-md border border-red-200 bg-white px-3 py-1.5 text-sm text-red-700 hover:bg-red-50"
                        @click="removeRow(index)"
                    >
                        Remover
                    </button>
                </div>
            </div>

            <div v-if="form.errors.items" class="text-sm text-red-600">{{ form.errors.items }}</div>

            <div class="flex gap-3">
                <PrimaryButton :disabled="form.processing">Salvar e publicar para a empresa</PrimaryButton>
            </div>
        </form>
    </AdminLayout>
</template>
