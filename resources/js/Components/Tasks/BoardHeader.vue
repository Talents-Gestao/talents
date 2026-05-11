<script setup>
import { router, useForm } from '@inertiajs/vue3';
import {
    PencilSquareIcon,
    StarIcon as StarOutlineIcon,
    UserPlusIcon,
} from '@heroicons/vue/24/outline';
import { StarIcon as StarSolidIcon } from '@heroicons/vue/24/solid';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    boardPayload: { type: Object, required: true },
    isAdmin: { type: Boolean, default: false },
    companyUsers: { type: Array, default: () => [] },
    /** Quando false, não repete o nome do quadro (ex.: já exibido num hero acima). */
    showBoardTitle: { type: Boolean, default: true },
});

const emit = defineEmits(['refresh']);

const isStarred = ref(Boolean(props.boardPayload?.is_starred));
const inviting = ref(false);
const inviteUserId = ref(null);
const editingName = ref(false);

const boardForm = useForm({
    name: props.boardPayload?.name || '',
    description: props.boardPayload?.description || '',
});

watch(
    () => props.boardPayload,
    (val) => {
        boardForm.name = val?.name || '';
        boardForm.description = val?.description || '';
    },
    { deep: true },
);

const members = computed(() => props.boardPayload?.members ?? []);
const visibleMembers = computed(() => members.value.slice(0, 4));
const extraMembers = computed(() => Math.max(0, members.value.length - visibleMembers.value.length));

const availableToInvite = computed(() => {
    const memberIds = new Set(members.value.map((m) => m.id));
    return (props.companyUsers || []).filter((u) => !memberIds.has(u.id));
});

const palette = [
    'bg-amber-500',
    'bg-rose-500',
    'bg-fuchsia-500',
    'bg-violet-500',
    'bg-indigo-500',
    'bg-sky-500',
    'bg-emerald-500',
    'bg-teal-500',
    'bg-orange-500',
];

function avatarInitials(name) {
    if (!name) return '?';
    const parts = String(name).trim().split(/\s+/);
    const first = parts[0]?.[0] ?? '';
    const last = parts.length > 1 ? parts[parts.length - 1][0] : '';
    return (first + last).toUpperCase().slice(0, 2);
}

function avatarColor(seed) {
    const n = Number(seed);
    if (Number.isFinite(n)) return palette[Math.abs(Math.trunc(n)) % palette.length];
    let hash = 0;
    for (const ch of String(seed ?? '')) hash = (hash * 31 + ch.charCodeAt(0)) | 0;
    return palette[Math.abs(hash) % palette.length];
}

function toggleStar() {
    const willStar = !isStarred.value;
    isStarred.value = willStar;

    const routeName = props.isAdmin
        ? willStar
            ? 'admin.tarefas.quadros.favoritar'
            : 'admin.tarefas.quadros.desfavoritar'
        : willStar
            ? 'client.tarefas.favoritar'
            : 'client.tarefas.desfavoritar';

    const method = willStar ? 'post' : 'delete';

    router[method](
        route(routeName, props.boardPayload.id),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                isStarred.value = !willStar;
            },
        },
    );
}

function inviteSelected() {
    if (!inviteUserId.value) return;
    router.post(
        route('admin.tarefas.quadros.membros.store', props.boardPayload.id),
        { user_id: inviteUserId.value, role: 'editor' },
        {
            preserveScroll: true,
            onSuccess: () => {
                inviteUserId.value = null;
                inviting.value = false;
                emit('refresh');
            },
        },
    );
}

function removeMember(userId) {
    if (!confirm('Remover este membro do quadro?')) return;
    router.delete(
        route('admin.tarefas.quadros.membros.destroy', [props.boardPayload.id, userId]),
        {
            preserveScroll: true,
            onSuccess: () => emit('refresh'),
        },
    );
}

function saveBoardName() {
    if (!props.isAdmin) return;
    boardForm.patch(route('admin.tarefas.quadros.update', props.boardPayload.id), {
        preserveScroll: true,
        onSuccess: () => {
            editingName.value = false;
            emit('refresh');
        },
    });
}

</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white/70 px-3 py-2 shadow-sm ring-1 ring-slate-200 backdrop-blur">
        <div class="flex min-w-0 items-center gap-2">
            <template v-if="editingName && isAdmin">
                <form class="flex items-center gap-2" @submit.prevent="saveBoardName">
                    <input
                        v-model="boardForm.name"
                        class="w-64 rounded-md border border-slate-300 px-2 py-1 text-sm font-semibold text-slate-900"
                        maxlength="255"
                        required
                    />
                    <button
                        type="submit"
                        class="rounded bg-talents-600 px-2 py-1 text-xs font-semibold text-white hover:bg-talents-700"
                    >
                        Salvar
                    </button>
                    <button
                        type="button"
                        class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-100"
                        @click="editingName = false"
                    >
                        Cancelar
                    </button>
                </form>
            </template>
            <h2 v-else-if="showBoardTitle" class="truncate text-base font-semibold text-slate-900 sm:text-lg">
                {{ boardPayload.name }}
            </h2>

            <button
                v-if="isAdmin && !editingName"
                type="button"
                class="rounded-md p-1 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                title="Alterar nome do quadro"
                @click="editingName = true"
            >
                <PencilSquareIcon class="h-4 w-4" />
            </button>

            <button
                type="button"
                class="rounded-md p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-amber-500"
                :title="isStarred ? 'Remover dos favoritos' : 'Adicionar aos favoritos'"
                @click="toggleStar"
            >
                <StarSolidIcon v-if="isStarred" class="h-4 w-4 text-amber-400" />
                <StarOutlineIcon v-else class="h-4 w-4" />
            </button>

            <span
                v-if="boardPayload.company"
                class="hidden items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 sm:inline-flex"
            >
                {{ boardPayload.company.name }}
            </span>
            <span
                v-else-if="boardPayload.is_internal"
                class="hidden items-center rounded-md bg-talents-50 px-2 py-0.5 text-xs font-medium text-talents-700 sm:inline-flex"
            >
                Interno
            </span>
        </div>

        <div class="flex items-center gap-2">
            <div v-if="members.length" class="flex -space-x-1.5">
                <span
                    v-for="m in visibleMembers"
                    :key="m.id"
                    :title="m.name + (m.role ? ' · ' + m.role : '')"
                    class="inline-flex h-7 w-7 items-center justify-center rounded-full text-[11px] font-semibold text-white ring-2 ring-white"
                    :class="avatarColor(m.id)"
                >
                    {{ avatarInitials(m.name) }}
                </span>
                <span
                    v-if="extraMembers"
                    class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-300 text-[11px] font-semibold text-slate-700 ring-2 ring-white"
                    :title="`+${extraMembers} membros`"
                >
                    +{{ extraMembers }}
                </span>
            </div>

            <button
                v-if="isAdmin"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg bg-talents-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700"
                @click="inviting = true"
            >
                <UserPlusIcon class="h-4 w-4" />
                Convidar
            </button>

        </div>

        <Teleport to="body">
            <div
                v-if="inviting"
                class="fixed inset-0 z-40 flex items-start justify-center bg-slate-900/40 px-4 py-12"
                @click.self="inviting = false"
            >
                <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-slate-900">Convidar para o quadro</h3>
                        <button
                            type="button"
                            class="rounded-md p-1 text-slate-500 hover:bg-slate-100"
                            @click="inviting = false"
                        >
                            <span class="sr-only">Fechar</span>
                            ✕
                        </button>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="text-xs font-medium text-slate-600">Adicionar usuário</label>
                            <div class="mt-1 flex gap-2">
                                <select
                                    v-model="inviteUserId"
                                    class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                >
                                    <option :value="null">Selecione…</option>
                                    <option v-for="u in availableToInvite" :key="u.id" :value="u.id">
                                        {{ u.name }} <span v-if="u.email">— {{ u.email }}</span>
                                    </option>
                                </select>
                                <button
                                    type="button"
                                    class="rounded-lg bg-talents-600 px-3 text-sm font-semibold text-white shadow hover:bg-talents-700 disabled:opacity-50"
                                    :disabled="!inviteUserId"
                                    @click="inviteSelected"
                                >
                                    Adicionar
                                </button>
                            </div>
                            <p v-if="!availableToInvite.length" class="mt-2 text-xs text-slate-500">
                                Não há usuários elegíveis para adicionar.
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Membros atuais ({{ members.length }})
                            </p>
                            <ul class="mt-2 max-h-72 space-y-1 overflow-y-auto pr-1">
                                <li
                                    v-for="m in members"
                                    :key="m.id"
                                    class="flex items-center justify-between gap-2 rounded-lg border border-slate-100 px-2 py-1.5"
                                >
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-full text-[11px] font-semibold text-white"
                                            :class="avatarColor(m.id)"
                                        >
                                            {{ avatarInitials(m.name) }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-slate-800">{{ m.name }}</p>
                                            <p class="truncate text-xs text-slate-500">{{ m.email }}</p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="text-xs font-medium text-rose-600 hover:underline"
                                        @click="removeMember(m.id)"
                                    >
                                        Remover
                                    </button>
                                </li>
                                <li v-if="!members.length" class="text-xs text-slate-500">
                                    Nenhum membro vinculado ainda.
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
