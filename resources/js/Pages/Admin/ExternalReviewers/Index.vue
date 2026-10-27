<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

defineProps({
    reviewers: { type: Array, default: () => [] },
    organizations: { type: Array, default: () => [] },
    externalReviewEnabled: { type: Boolean, default: false },
    canResetMfa: { type: Boolean, default: false },
});

const form = useForm({ name: '', email: '', organization_id: '', new_organization: '' });

const submit = () => {
    form.post(route('admin.external-reviewers.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const resend = (user) => router.post(route('admin.external-reviewers.resend', user.id), {}, { preserveScroll: true });
const resetMfa = (user) => {
    if (window.confirm('Resetar a verificação em duas etapas deste revisor? Ele será avisado por e-mail e fará nova inscrição.')) {
        router.post(route('admin.users.mfa.reset', user.id), {}, { preserveScroll: true });
    }
};
const toggle = (user, active) => router.post(route('admin.external-reviewers.toggle', user.id), { active }, { preserveScroll: true });
</script>

<template>
    <Head title="Revisores externos" />

    <AdminLayout>
        <template #header>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-talents-700">Equipe</p>
                <h2 class="text-xl font-semibold leading-tight text-gray-900">Revisores externos (PASSEG)</h2>
            </div>
        </template>

        <div class="space-y-6">
            <div v-if="$page.props.flash?.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ $page.props.flash.success }}</div>
            <div v-if="$page.props.flash?.error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ $page.props.flash.error }}</div>

            <p class="rounded-lg border px-4 py-3 text-sm" :class="externalReviewEnabled ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-300 bg-amber-50 text-amber-900'">
                <template v-if="externalReviewEnabled">A revisão externa está ligada: revisores com senha definida acessam os documentos compartilhados com eles.</template>
                <template v-else>A revisão externa está desligada (aguarda o MFA). Os cadastros abaixo <strong>não têm acesso</strong> aos documentos até ela ser ligada.</template>
            </p>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="new-reviewer">
                <h3 id="new-reviewer" class="text-base font-semibold text-talents-900">Cadastrar revisor e enviar convite</h3>
                <form class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="submit">
                    <div>
                        <label for="reviewer-name" class="text-xs font-semibold text-gray-600">Nome</label>
                        <input id="reviewer-name" v-model="form.name" type="text" required class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                        <p v-if="form.errors.name" class="text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label for="reviewer-email" class="text-xs font-semibold text-gray-600">E-mail</label>
                        <input id="reviewer-email" v-model="form.email" type="email" required class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                        <p v-if="form.errors.email" class="text-xs text-red-600">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label for="reviewer-org" class="text-xs font-semibold text-gray-600">Organização aprovadora</label>
                        <select id="reviewer-org" v-model="form.organization_id" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            <option value="">Selecione</option>
                            <option v-for="organization in organizations" :key="organization.id" :value="organization.id">{{ organization.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="reviewer-new-org" class="text-xs font-semibold text-gray-600">Ou uma nova organização</label>
                        <input id="reviewer-new-org" v-model="form.new_organization" type="text" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="Nome da organização" />
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="rounded-md bg-talents-700 px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-60" :disabled="form.processing">Cadastrar e enviar convite</button>
                    </div>
                </form>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="reviewer-list">
                <h3 id="reviewer-list" class="text-base font-semibold text-talents-900">Revisores cadastrados</h3>
                <p v-if="!reviewers.length" class="mt-3 text-sm text-gray-600">Nenhum revisor cadastrado.</p>
                <ul v-else class="mt-3 divide-y divide-gray-100">
                    <li v-for="reviewer in reviewers" :key="reviewer.id" class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ reviewer.name }} <span class="text-xs text-gray-500">· {{ reviewer.email }}</span></p>
                            <p class="text-xs text-gray-600">{{ reviewer.organization ?? 'Sem organização' }} · {{ reviewer.status_label }} · {{ reviewer.mfa_active ? 'MFA ativo' : 'MFA não ativado' }}</p>
                        </div>
                        <div class="flex gap-2">
                            <button v-if="reviewer.status === 'pending'" type="button" class="rounded-md border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-800" @click="resend(reviewer)">Reenviar convite</button>
                            <button v-if="canResetMfa && reviewer.mfa_active" type="button" class="rounded-md border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-800" @click="resetMfa(reviewer)">Resetar MFA</button>
                            <button v-if="reviewer.status !== 'inactive'" type="button" class="rounded-md border border-red-300 px-3 py-1 text-xs font-semibold text-red-800" @click="toggle(reviewer, false)">Desativar</button>
                            <button v-else type="button" class="rounded-md border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-800" @click="toggle(reviewer, true)">Reativar</button>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </AdminLayout>
</template>
