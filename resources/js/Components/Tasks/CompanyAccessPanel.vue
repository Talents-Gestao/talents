<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ExclamationTriangleIcon, EnvelopeIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { formatDateNumeric } from '@/utils/dateOnly';

/**
 * «Acesso da empresa» (só Admin): quem da empresa cliente acessa Tarefas no portal e quem recebe e-mail.
 * Somente leitura (rota admin.tarefas.empresas.acesso); a única ação é o reenvio de convite que já existe.
 * mode="summary": resumo curto antes de ativar um processo ou partilhar cartões.
 */
const props = defineProps({
    companyId: { type: [Number, String], required: true },
    boardId: { type: [Number, String], default: null },
    mode: { type: String, default: 'full', validator: (value) => ['full', 'summary'].includes(value) },
    /** 'board' | 'share' | 'activate': ajusta o texto sobre o envio de e-mail. */
    context: { type: String, default: 'board' },
});

const data = ref(null);
const loading = ref(false);
const failed = ref(false);
const resendingId = ref(null);
let requestSeq = 0;

async function load() {
    if (!props.companyId) {
        data.value = null;
        return;
    }
    const seq = ++requestSeq;
    loading.value = true;
    failed.value = false;
    try {
        const params = props.boardId ? { quadro: props.boardId } : {};
        const response = await window.axios.get(route('admin.tarefas.empresas.acesso', props.companyId), { params });
        if (seq === requestSeq) data.value = response.data;
    } catch (_e) {
        if (seq === requestSeq) failed.value = true;
    } finally {
        if (seq === requestSeq) loading.value = false;
    }
}

watch(() => [props.companyId, props.boardId], load, { immediate: true });

const companyName = computed(() => data.value?.company?.name || 'a empresa');
const withAccess = computed(() => data.value?.with_access_count ?? 0);
const nobody = computed(() => data.value && (!data.value.module.enabled || withAccess.value === 0));
const actionLabel = computed(
    () => ({ share: 'Partilhar os cartões', activate: 'Ativar o processo', board: 'Ativar o processo ou partilhar cartões' })[props.context]
        ?? 'Esta ação',
);
const accessSentence = computed(() => {
    const n = withAccess.value;
    const users = n === 1 ? '1 usuário' : `${n} usuários`;
    if (props.context === 'activate') {
        return `${users} da ${companyName.value} terão acesso ao novo quadro (administradores da empresa). Usuários comuns só veem o quadro quando forem adicionados como membros.`;
    }
    return `${users} da ${companyName.value} ${n === 1 ? 'tem' : 'têm'} acesso a este quadro no portal.`;
});

function statusClass(status) {
    return {
        active: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        pending: 'bg-amber-50 text-amber-800 ring-amber-200',
        inactive: 'bg-slate-100 text-slate-600 ring-slate-200',
    }[status];
}

function resend(user) {
    if (!data.value?.can?.resend_invitation || resendingId.value) return;
    resendingId.value = user.id;
    router.post(
        route('admin.companies.users.resend-invitation', [data.value.company.id, user.id]),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => load(),
            onFinish: () => {
                resendingId.value = null;
            },
        },
    );
}
</script>

<template>
    <div class="space-y-3 text-sm" data-company-access-panel>
        <p v-if="loading && !data" class="text-slate-500">Carregando acesso da empresa…</p>
        <p v-else-if="failed" class="text-rose-700">
            Não foi possível carregar o acesso da empresa.
            <button type="button" class="font-medium underline" @click="load">Tentar de novo</button>
        </p>

        <template v-else-if="data">
            <div
                v-if="nobody"
                class="flex gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-rose-900"
                role="alert"
                data-company-access-nobody
            >
                <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
                <div>
                    <p class="font-semibold">
                        <template v-if="!data.module.enabled">Ninguém da {{ companyName }} verá este quadro: o módulo Tarefas está desabilitado para a empresa.</template>
                        <template v-else>Ninguém da {{ companyName }} tem acesso a este quadro no portal.</template>
                    </p>
                    <p class="mt-0.5 text-xs">
                        {{ data.module.enabled ? 'Ative usuários, conceda a permissão de Tarefas ou adicione usuários como membros do quadro.' : data.module.label + '.' }}
                    </p>
                    <Link
                        v-if="data.can.manage_users"
                        :href="data.links.users"
                        class="mt-1 inline-block text-xs font-semibold underline"
                    >
                        Abrir usuários da empresa
                    </Link>
                </div>
            </div>
            <p v-else class="text-slate-700" data-company-access-count>{{ accessSentence }}</p>

            <div class="flex gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-slate-700">
                <EnvelopeIcon class="mt-0.5 h-5 w-5 shrink-0 text-slate-500" aria-hidden="true" />
                <div class="space-y-1 text-xs">
                    <p>
                        <strong>E-mail de acesso: não será enviado.</strong>
                        {{ actionLabel }} não envia e-mail; o quadro só aparece no portal para quem já tem acesso.
                    </p>
                    <p v-if="data.registration_contact">
                        O convite de acesso ao portal foi enviado ao contato principal ({{ data.registration_contact }}) no cadastro da empresa;
                        cada usuário recebe o próprio convite ao ser cadastrado em Usuários da empresa.
                    </p>
                    <p v-if="data.pending_emails.length" data-company-access-pending>
                        Convite pendente (ainda não entraram no portal):
                        <strong>{{ data.pending_emails.join(', ') }}</strong>.
                    </p>
                </div>
            </div>

            <template v-if="mode === 'full'">
                <p class="text-xs text-slate-600">
                    <span class="font-medium text-slate-800">Módulo Tarefas:</span>
                    {{ data.module.enabled ? 'habilitado' : 'desabilitado' }} · {{ data.module.label }}
                </p>

                <p v-if="!data.users.length" class="text-slate-600">A empresa ainda não tem usuários cadastrados.</p>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[40rem] text-left text-xs">
                        <thead class="text-[11px] uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="py-1.5 pr-3 font-semibold">Usuário</th>
                                <th class="py-1.5 pr-3 font-semibold">Papel</th>
                                <th class="py-1.5 pr-3 font-semibold">Tarefas</th>
                                <th class="py-1.5 pr-3 font-semibold">Situação</th>
                                <th class="py-1.5 pr-3 font-semibold">Este quadro</th>
                                <th class="py-1.5 font-semibold"><span class="sr-only">Ações</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="user in data.users" :key="user.id" data-company-access-user>
                                <td class="py-2 pr-3 align-top">
                                    <p class="font-medium text-slate-900">{{ user.name }}</p>
                                    <p class="text-slate-500">{{ user.email }}</p>
                                    <p v-if="user.is_registration_contact" class="mt-0.5 text-[11px] text-talents-700">
                                        Contato principal: recebeu o convite do cadastro da empresa
                                    </p>
                                </td>
                                <td class="py-2 pr-3 align-top text-slate-700">{{ user.role_label }}</td>
                                <td class="py-2 pr-3 align-top text-slate-700">{{ user.tasks_level_label }}</td>
                                <td class="py-2 pr-3 align-top">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium ring-1" :class="statusClass(user.status)">
                                        {{ user.status_label }}
                                    </span>
                                    <p v-if="user.last_invite_at" class="mt-0.5 text-[11px] text-slate-500">
                                        Último convite: {{ formatDateNumeric(user.last_invite_at.slice(0, 10)) }}
                                    </p>
                                </td>
                                <td class="py-2 pr-3 align-top">
                                    <p :class="user.board_access ? 'font-medium text-emerald-700' : 'text-slate-500'">
                                        {{ user.board_access ? 'Tem acesso' : 'Sem acesso' }}
                                    </p>
                                    <p class="text-[11px] text-slate-500">{{ user.board_access_reason }}</p>
                                </td>
                                <td class="whitespace-nowrap py-2 align-top text-right">
                                    <button
                                        v-if="user.status === 'pending' && data.can.resend_invitation"
                                        type="button"
                                        class="rounded-md border border-slate-300 px-2 py-1 text-[11px] font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-60"
                                        :disabled="resendingId === user.id"
                                        @click="resend(user)"
                                    >
                                        {{ resendingId === user.id ? 'Reenviando…' : 'Reenviar convite' }}
                                    </button>
                                    <Link
                                        v-if="data.can.manage_users"
                                        :href="route('admin.companies.users.edit', [data.company.id, user.id])"
                                        class="ml-2 text-[11px] font-medium text-talents-700 underline"
                                    >
                                        Editar permissões
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <Link
                    v-if="data.can.manage_users"
                    :href="data.links.users"
                    class="inline-block text-xs font-semibold text-talents-700 underline"
                >
                    Usuários da {{ companyName }}
                </Link>
            </template>
        </template>
    </div>
</template>
