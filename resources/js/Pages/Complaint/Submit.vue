<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    token: String,
    companyName: String,
    categories: Object,
    departments: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    category: '',
    description: '',
    department_id: '',
    is_anonymous: true,
    reporter_name: '',
    reporter_email: '',
});

const submit = () => {
    form.post(route('denuncia.store', props.token));
};

const needsIdentity = computed(() => !form.is_anonymous);
</script>

<template>
    <Head title="Canal de denúncias" />

    <div class="min-h-screen bg-slate-100 text-gray-900">
        <header class="border-b border-gray-200 bg-white px-4 py-4 shadow-sm">
            <div class="mx-auto max-w-2xl">
                <p class="text-xs uppercase tracking-widest text-talents-600">Canal sigiloso</p>
                <h1 class="text-lg font-semibold text-gray-900">{{ companyName }}</h1>
                <p class="text-sm text-gray-600">Lei nº 14.457/2022 — denúncia com protocolo e acompanhamento.</p>
            </div>
        </header>

        <div class="mx-auto max-w-2xl px-4 py-8">
            <form class="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipo de ocorrência</label>
                    <select
                        v-model="form.category"
                        required
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option value="" disabled>Selecione</option>
                        <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <p v-if="form.errors.category" class="mt-1 text-sm text-red-600">{{ form.errors.category }}</p>
                </div>

                <div v-if="departments.length">
                    <label class="block text-sm font-medium text-gray-700">Setor / departamento (opcional)</label>
                    <select
                        v-model="form.department_id"
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option value="">Não informar</option>
                        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                    <p v-if="form.errors.department_id" class="mt-1 text-sm text-red-600">{{ form.errors.department_id }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Descrição (mín. 20 caracteres)</label>
                    <textarea
                        v-model="form.description"
                        required
                        rows="8"
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        placeholder="Descreva os fatos com o máximo de detalhes possível."
                    />
                    <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.is_anonymous" type="checkbox" class="rounded border-gray-300 text-talents-600" />
                    Quero permanecer anônimo(a)
                </label>

                <div v-if="needsIdentity" class="space-y-4 border-t border-gray-100 pt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nome</label>
                        <input
                            v-model="form.reporter_name"
                            type="text"
                            class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                        <p v-if="form.errors.reporter_name" class="mt-1 text-sm text-red-600">{{ form.errors.reporter_name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">E-mail para retorno</label>
                        <input
                            v-model="form.reporter_email"
                            type="email"
                            class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                        <p v-if="form.errors.reporter_email" class="mt-1 text-sm text-red-600">{{ form.errors.reporter_email }}</p>
                    </div>
                </div>

                <p class="text-xs text-gray-500">
                    Os dados são armazenados de forma segura. O acesso ao painel da empresa é registrado para auditoria.
                </p>

                <button
                    type="submit"
                    class="w-full rounded-lg bg-talents-700 py-3 text-sm font-semibold text-white hover:bg-talents-800 disabled:opacity-50"
                    :disabled="form.processing"
                >
                    Enviar denúncia
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-gray-600">
                <a :href="route('denuncia.track', token)" class="font-medium text-talents-700 hover:underline">Já tenho um protocolo — acompanhar</a>
            </p>
        </div>
    </div>
</template>
