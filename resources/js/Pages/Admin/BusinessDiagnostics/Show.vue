<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { confirmDialog } from '@/composables/useConfirmDialog';

const props = defineProps({
    diagnostic: { type: Object, required: true },
});

const remove = async () => {
    if (await confirmDialog('Remover este diagnóstico?')) {
        router.delete(route('admin.diagnostico-empresarial.destroy', props.diagnostic.id));
    }
};

const formatDate = (iso) => {
    if (!iso) {
        return '—';
    }
    return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
};
</script>

<template>
    <Head :title="`Diagnóstico — ${diagnostic.company_name}`" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.diagnostico-empresarial.index')"
                back-label="Diagnósticos"
                :title="diagnostic.company_name"
                subtitle="Diagnóstico empresarial"
            >
                <template #trailing>
                    <Link :href="route('admin.diagnostico-empresarial.edit', diagnostic.id)">
                        <PrimaryButton type="button">Editar</PrimaryButton>
                    </Link>
                    <SecondaryButton type="button" class="ml-2" @click="remove">Remover</SecondaryButton>
                </template>
            </FormPageHeader>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>

        <div class="mx-auto max-w-3xl space-y-4">
            <section class="surface-card p-6 sm:p-7">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Empresa</dt>
                        <dd class="mt-1 font-medium text-slate-900">{{ diagnostic.company_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">CNPJ</dt>
                        <dd class="mt-1 text-slate-800">{{ diagnostic.cnpj || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Segmento</dt>
                        <dd class="mt-1 text-slate-800">{{ diagnostic.segment || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Colaboradores</dt>
                        <dd class="mt-1 text-slate-800">{{ diagnostic.employee_count || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Responsável</dt>
                        <dd class="mt-1 text-slate-800">{{ diagnostic.responsible_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">E-mail</dt>
                        <dd class="mt-1">
                            <a :href="'mailto:' + diagnostic.email" class="text-talents-700 hover:underline">{{
                                diagnostic.email
                            }}</a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">WhatsApp/Telefone</dt>
                        <dd class="mt-1 text-slate-800">{{ diagnostic.phone || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Maturidade do RH</dt>
                        <dd class="mt-1">
                            <span
                                v-if="diagnostic.hr_maturity != null"
                                class="inline-flex rounded-full bg-talents-50 px-2.5 py-0.5 text-xs font-semibold text-talents-800 ring-1 ring-talents-200/80"
                            >
                                {{ diagnostic.hr_maturity }}/10
                            </span>
                            <span v-else>—</span>
                        </dd>
                    </div>
                    <div v-if="diagnostic.company">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Cliente vinculado</dt>
                        <dd class="mt-1">
                            <Link
                                :href="route('admin.companies.show', diagnostic.company.id)"
                                class="font-medium text-talents-700 hover:underline"
                            >
                                {{ diagnostic.company.name }}
                            </Link>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Registado por</dt>
                        <dd class="mt-1 text-slate-800">{{ diagnostic.creator?.name || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Criado em</dt>
                        <dd class="mt-1 text-slate-800">{{ formatDate(diagnostic.created_at) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="surface-card p-6 sm:p-7">
                <h3 class="text-sm font-semibold text-slate-900">Sobre a empresa</h3>
                <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">
                    {{ diagnostic.company_history || '—' }}
                </p>
            </section>

            <section class="surface-card p-6 sm:p-7">
                <h3 class="text-sm font-semibold text-slate-900">Maior desafio em Gestão de Pessoas</h3>
                <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">
                    {{ diagnostic.biggest_challenge || '—' }}
                </p>
            </section>
        </div>
    </AdminLayout>
</template>
