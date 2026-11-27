<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { applicationLabel } from '@/utils/interviewLink';
import { onMounted, ref, watch } from 'vue';

/**
 * Escolha do candidato cadastrado (busca nos ativos) e, opcionalmente, de uma das candidaturas dele.
 * Nada é escolhido pelo nome: o usuário seleciona o cadastro na lista.
 */
const props = defineProps({
    candidate: { type: Object, default: null },
    applicationId: { type: [String, Number], default: '' },
    error: { type: String, default: null },
    applicationError: { type: String, default: null },
    idPrefix: { type: String, default: 'link' },
});

const emit = defineEmits(['update:candidate', 'update:applicationId']);

const term = ref('');
const results = ref([]);
const searching = ref(false);
const searchError = ref(null);
let timer = null;
let seq = 0;

const fieldClass =
    'mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70';

const search = async () => {
    const current = ++seq;
    searching.value = true;
    searchError.value = null;
    try {
        const { data } = await window.axios.get(route('admin.entrevistas.candidates'), {
            params: { q: term.value || undefined },
        });
        if (current === seq) {
            results.value = data?.data ?? [];
        }
    } catch {
        if (current === seq) {
            searchError.value = 'Não foi possível buscar candidatos.';
        }
    } finally {
        if (current === seq) {
            searching.value = false;
        }
    }
};

watch(term, () => {
    clearTimeout(timer);
    timer = setTimeout(search, 300);
});

onMounted(() => {
    if (!props.candidate) {
        search();
    }
});

const choose = (candidate) => {
    emit('update:candidate', candidate);
    emit('update:applicationId', '');
};

const clear = () => {
    emit('update:candidate', null);
    emit('update:applicationId', '');
    search();
};
</script>

<template>
    <div class="space-y-3">
        <div v-if="candidate" class="rounded-xl border border-talents-200 bg-talents-50/50 px-3 py-2.5">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-talents-700">Candidato</p>
                    <p class="truncate text-sm font-semibold text-slate-900">{{ candidate.name }}</p>
                    <p class="truncate text-xs text-slate-500">
                        {{ [candidate.email, candidate.current_role].filter(Boolean).join(' · ') || 'Sem e-mail ou cargo informado' }}
                    </p>
                </div>
                <button type="button" class="text-xs font-semibold text-talents-700 hover:underline" @click="clear">
                    Trocar candidato
                </button>
            </div>
        </div>

        <div v-else>
            <InputLabel :for="`${idPrefix}-search`" value="Candidato cadastrado" />
            <TextInput
                :id="`${idPrefix}-search`"
                v-model="term"
                type="search"
                :class="fieldClass"
                placeholder="Busque por nome, e-mail ou telefone"
                autocomplete="off"
            />
            <p class="mt-1 text-xs text-slate-500">Só aparecem cadastros ativos. Escolha a pessoa na lista; o nome sozinho não identifica.</p>
            <InputError class="mt-1" :message="error" />
            <p v-if="searchError" class="mt-2 text-sm text-red-600">{{ searchError }}</p>
            <p v-else-if="searching" class="mt-2 text-sm text-slate-500" role="status">Buscando…</p>
            <ul v-else-if="results.length" class="mt-2 max-h-60 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200">
                <li v-for="c in results" :key="c.id">
                    <button
                        type="button"
                        class="flex w-full flex-col items-start px-3 py-2 text-left hover:bg-talents-50 focus:bg-talents-50 focus:outline-none"
                        @click="choose(c)"
                    >
                        <span class="text-sm font-semibold text-slate-900">{{ c.name }}</span>
                        <span class="text-xs text-slate-500">
                            {{ [c.email, c.current_role].filter(Boolean).join(' · ') || 'Sem e-mail ou cargo informado' }}
                            · {{ c.applications.length }} {{ c.applications.length === 1 ? 'vaga' : 'vagas' }}
                        </span>
                    </button>
                </li>
            </ul>
            <p v-else class="mt-2 text-sm text-slate-500">Nenhum candidato encontrado. Cadastre-o em Contratação → Candidatos.</p>
        </div>

        <div v-if="candidate">
            <InputLabel :for="`${idPrefix}-application`" value="Vaga (opcional)" />
            <select
                :id="`${idPrefix}-application`"
                :value="applicationId"
                :class="fieldClass"
                @change="emit('update:applicationId', $event.target.value)"
            >
                <option value="">Sem vaga vinculada</option>
                <option v-for="a in candidate.applications" :key="a.id" :value="String(a.id)">{{ applicationLabel(a) }}</option>
            </select>
            <p class="mt-1 text-xs text-slate-500">A empresa da entrevista vem da vaga escolhida.</p>
            <InputError class="mt-1" :message="applicationError" />
        </div>
    </div>
</template>
