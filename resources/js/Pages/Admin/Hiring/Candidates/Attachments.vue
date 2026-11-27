<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirmDialog } from '@/composables/useConfirmDialog';
import { formatDateTime } from '@/utils/dateOnly';
import { formatFileSize } from '@/utils/talentCandidate';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowDownTrayIcon, LockClosedIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';

const props = defineProps({
    candidate: { type: Object, required: true },
    attachments: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const fileInput = ref(null);
const form = useForm({
    file: null,
    sender_name: '',
    received_on: '',
    purpose: '',
    privacy_confirmed: false,
});

const onFile = (event) => {
    form.file = event.target.files?.[0] ?? null;
};

const submit = () => {
    form.post(route('admin.contratacao.candidatos.anexos.store', props.candidate.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
};

const destroy = async (attachment) => {
    if (!(await confirmDialog(`Excluir o anexo «${attachment.original_name}»?`))) {
        return;
    }
    router.delete(route('admin.contratacao.candidatos.anexos.destroy', [props.candidate.id, attachment.id]), {
        preserveScroll: true,
    });
};

const fieldClass =
    'mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70';
</script>

<template>
    <Head :title="`Anexos internos · ${candidate.name}`" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.contratacao.candidatos.show', candidate.id)"
                back-label="Ficha"
                title="Anexos internos"
                :subtitle="candidate.name"
            />
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            role="status"
        >
            {{ $page.props.flash.success }}
        </div>

        <p class="mb-4 flex items-start gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            <LockClosedIcon class="mt-0.5 h-4 w-4 shrink-0 text-slate-500" aria-hidden="true" />
            Uso restrito da administração. Os anexos não entram em exportações, relatórios, IA, dossiê nem no portal da
            empresa, e não são analisados automaticamente. Cada download e exclusão fica registrado.
        </p>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
            <section class="surface-card overflow-hidden" aria-labelledby="lista-anexos">
                <h3 id="lista-anexos" class="border-b border-slate-100 px-5 py-4 text-base font-semibold text-talents-900">
                    Arquivos ({{ attachments.length }})
                </h3>
                <ul v-if="attachments.length" class="divide-y divide-slate-100">
                    <li v-for="a in attachments" :key="a.id" class="flex flex-wrap items-start justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ a.original_name }}</p>
                            <p class="text-xs text-slate-500">
                                {{ formatFileSize(a.size) }} · entregue por {{ a.sender_name }} em
                                {{ a.received_on ? new Date(a.received_on + 'T12:00:00').toLocaleDateString('pt-BR') : '—' }}
                            </p>
                            <p class="mt-1 whitespace-pre-wrap text-xs text-slate-600">{{ a.purpose }}</p>
                            <p class="mt-1 text-xs text-slate-400">
                                Enviado<template v-if="a.uploaded_by_name"> por {{ a.uploaded_by_name }}</template>
                                em {{ formatDateTime(a.created_at) }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <a
                                :href="a.download_url"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-talents-50 hover:text-talents-700"
                                :title="`Baixar ${a.original_name}`"
                                :aria-label="`Baixar ${a.original_name}`"
                            >
                                <ArrowDownTrayIcon class="h-4 w-4" aria-hidden="true" />
                            </a>
                            <button
                                v-if="can.delete"
                                type="button"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-600 hover:bg-red-50"
                                :title="`Excluir ${a.original_name}`"
                                :aria-label="`Excluir ${a.original_name}`"
                                @click="destroy(a)"
                            >
                                <TrashIcon class="h-4 w-4" aria-hidden="true" />
                            </button>
                        </div>
                    </li>
                </ul>
                <p v-else class="px-5 py-8 text-sm text-slate-500">Nenhum anexo interno.</p>
            </section>

            <section v-if="can.create" class="surface-card p-5" aria-labelledby="novo-anexo">
                <h3 id="novo-anexo" class="text-base font-semibold text-talents-900">Novo anexo</h3>
                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div>
                        <InputLabel for="file" value="Arquivo (PDF, PNG ou JPG · até 5 MB)" />
                        <input
                            id="file"
                            ref="fileInput"
                            type="file"
                            accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg"
                            class="mt-1 block w-full text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-talents-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-talents-800"
                            required
                            @change="onFile"
                        />
                        <InputError class="mt-1" :message="form.errors.file" />
                    </div>
                    <div>
                        <InputLabel for="sender_name" value="Profissional ou pessoa que entregou o documento" />
                        <TextInput id="sender_name" v-model="form.sender_name" :class="fieldClass" maxlength="255" required />
                        <InputError class="mt-1" :message="form.errors.sender_name" />
                    </div>
                    <div>
                        <InputLabel for="received_on" value="Data de recebimento" />
                        <TextInput id="received_on" v-model="form.received_on" type="date" :class="fieldClass" required />
                        <InputError class="mt-1" :message="form.errors.received_on" />
                    </div>
                    <div>
                        <InputLabel for="purpose" value="Finalidade administrativa do arquivamento" />
                        <textarea id="purpose" v-model="form.purpose" rows="3" maxlength="1000" :class="fieldClass" required />
                        <InputError class="mt-1" :message="form.errors.purpose" />
                    </div>
                    <div>
                        <label class="flex items-start gap-2 text-sm text-slate-700">
                            <input
                                v-model="form.privacy_confirmed"
                                type="checkbox"
                                class="mt-0.5 h-4 w-4 rounded border-slate-300 text-talents-600 focus:ring-talents-500"
                                required
                            />
                            Confirmo que o arquivamento deste documento tem finalidade administrativa e foi conferido quanto à
                            privacidade
                        </label>
                        <InputError class="mt-1" :message="form.errors.privacy_confirmed" />
                    </div>
                    <div class="flex justify-end">
                        <PrimaryButton :disabled="form.processing">Guardar anexo</PrimaryButton>
                    </div>
                </form>
            </section>
        </div>
    </AdminLayout>
</template>
