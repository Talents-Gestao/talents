<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { maskPhoneBr } from '@/utils/formatPhone';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    employee: { type: Object, required: true },
});

const formatDate = (iso) => {
    if (!iso) return '—';
    return new Date(`${iso}T12:00:00`).toLocaleDateString('pt-BR');
};

const display = (value) => {
    const text = typeof value === 'string' ? value.trim() : value;
    return text || '—';
};

const remove = () => {
    if (confirm('Remover este colaborador?')) {
        router.delete(route('admin.colaboradores.destroy', props.employee.id));
    }
};
</script>

<template>
    <Head :title="`Colaborador — ${employee.name}`" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.colaboradores.index', { company_id: employee.company_id })"
                back-label="Colaboradores"
                :title="employee.name"
                :subtitle="employee.company?.name ?? ''"
            >
                <template #trailing>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link :href="route('admin.colaboradores.edit', employee.id)">
                            <PrimaryButton type="button">Editar</PrimaryButton>
                        </Link>
                        <SecondaryButton type="button" @click="remove">Remover</SecondaryButton>
                    </div>
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
            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-talents-900">Dados pessoais</h3>
                    <span
                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset"
                        :class="
                            employee.is_active
                                ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
                                : 'bg-slate-100 text-slate-600 ring-slate-200'
                        "
                    >
                        {{ employee.is_active ? 'Ativo' : 'Inativo' }}
                    </span>
                </div>
                <dl class="grid gap-4 p-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome completo</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ display(employee.name) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Data de nascimento</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ formatDate(employee.birth_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Telefone</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ display(maskPhoneBr(employee.phone)) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">E-mail</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ display(employee.email) }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Endereço</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ display(employee.address) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-talents-900">Contato de emergência</h3>
                </div>
                <dl class="grid gap-4 p-6 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ display(employee.emergency_contact_name) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Parentesco</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ display(employee.emergency_contact_relationship) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Telefone</dt>
                        <dd class="mt-1 text-sm text-slate-900">
                            {{ display(maskPhoneBr(employee.emergency_contact_phone)) }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-talents-900">Dados profissionais</h3>
                </div>
                <dl class="grid gap-4 p-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cargo</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ display(employee.position?.name) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Setor</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ display(employee.department?.name) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Data de admissão</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ formatDate(employee.admission_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gestor responsável</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ display(employee.leader?.name) }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jornada de trabalho</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ display(employee.work_schedule) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-talents-900">Documentos</h3>
                </div>
                <dl class="grid gap-4 p-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">CPF</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ display(employee.cpf) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">RG</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ display(employee.rg) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-talents-900">Observações</h3>
                </div>
                <div class="p-6">
                    <p class="whitespace-pre-wrap text-sm leading-relaxed text-slate-700">
                        {{ display(employee.notes) }}
                    </p>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
