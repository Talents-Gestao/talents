<script setup>
import FullScreenOverlay from '@/Components/FullScreenOverlay.vue';
import LandingInterestSourceField from '@/Components/Landing/LandingInterestSourceField.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

defineProps({
    submissions: Object,
    sourceOptions: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const showCreateModal = ref(false);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    company: '',
    message: '',
    source: '',
});

const flashSuccess = computed(() => page.props.flash?.success ?? null);

function mailErrorPresent(value) {
    return value !== null && value !== undefined && String(value).trim() !== '';
}

function mailErrorText(value) {
    if (value === null || value === undefined) {
        return '';
    }
    return typeof value === 'string' ? value : String(value);
}

function openCreateModal() {
    form.clearErrors();
    form.reset();
    form.source = '';
    showCreateModal.value = true;
}

function closeCreateModal() {
    showCreateModal.value = false;
}

function submitLead() {
    form.post(route('admin.landing-interest.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeCreateModal();
            form.reset();
            form.source = '';
        },
    });
}

function onKeydown(e) {
    if (e.key === 'Escape' && showCreateModal.value) {
        closeCreateModal();
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Head title="Leads" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-900">Leads</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Interessados captados pelo site ou cadastrados manualmente pelo time comercial.
                    </p>
                </div>
                <button type="button" class="btn-primary shrink-0" @click="openCreateModal">Novo Lead</button>
            </div>
        </template>

        <div
            v-if="flashSuccess"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
        >
            {{ flashSuccess }}
        </div>

        <div class="surface-card overflow-hidden">
            <div v-if="!submissions.data.length" class="px-4 py-10 text-center text-sm text-gray-600">
                Nenhum lead encontrado.
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
                            <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">Origem</th>
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
                            <td class="whitespace-nowrap px-4 py-3">
                                <span
                                    class="inline-flex rounded-full bg-talents-50 px-2 py-0.5 text-xs font-medium text-talents-800 ring-1 ring-talents-100"
                                >
                                    {{ s.source_label || '—' }}
                                </span>
                            </td>
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
                                    <span class="line-clamp-2 text-xs text-gray-500">{{ mailErrorText(s.mail_error) }}</span>
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

        <FullScreenOverlay :show="showCreateModal" @close="closeCreateModal">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900">Novo Lead</h3>
                <p class="mt-1 text-sm text-gray-600">Cadastro manual para leads captados fora do site.</p>

                <form class="mt-5 space-y-4" @submit.prevent="submitLead">
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="lead-name">Nome</label>
                        <input id="lead-name" v-model="form.name" type="text" required class="field-input" />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="lead-email">E-mail</label>
                        <input id="lead-email" v-model="form.email" type="email" required class="field-input" />
                        <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="lead-phone">
                            Telefone / WhatsApp <span class="font-normal text-gray-500">(opcional)</span>
                        </label>
                        <input id="lead-phone" v-model="form.phone" type="tel" class="field-input" />
                        <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="lead-company">
                            Empresa <span class="font-normal text-gray-500">(opcional)</span>
                        </label>
                        <input id="lead-company" v-model="form.company" type="text" class="field-input" />
                        <p v-if="form.errors.company" class="mt-1 text-sm text-red-600">{{ form.errors.company }}</p>
                    </div>
                    <LandingInterestSourceField
                        id="lead-source"
                        v-model="form.source"
                        empty-option
                        :error="form.errors.source"
                    />
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="lead-message">
                            Mensagem <span class="font-normal text-gray-500">(opcional)</span>
                        </label>
                        <textarea id="lead-message" v-model="form.message" rows="3" class="field-input" />
                        <p v-if="form.errors.message" class="mt-1 text-sm text-red-600">{{ form.errors.message }}</p>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="btn-secondary" @click="closeCreateModal">Cancelar</button>
                        <button type="submit" class="btn-primary" :disabled="form.processing">
                            {{ form.processing ? 'Salvando…' : 'Salvar lead' }}
                        </button>
                    </div>
                </form>
            </div>
        </FullScreenOverlay>
    </AdminLayout>
</template>
