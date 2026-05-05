<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useRhidPunchSchedule } from '@/composables/useRhidPunchSchedule';
import { Head, Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, ref, toRef, watch } from 'vue';

const props = defineProps({
    configured: { type: Boolean, required: true },
    settings: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    rhid_base_url: props.settings.rhid_base_url || '',
    rhid_email: props.settings.rhid_email || '',
    rhid_password: '',
    rhid_domain: props.settings.rhid_domain || '',
});

const testing = ref(false);
const testMessage = ref(null);
const testOk = ref(null);
const scheduleErr = ref(null);

const configuredRef = toRef(props, 'configured');

const {
    PUNCH_DAY_ORDER,
    scheduleForm,
    scheduleLoading,
    scheduleSaving,
    schedulePrefBatchIds,
    schedulePrefBatchSecond,
    schedulePrefBatchSaving,
    schedulePrefBatchMsg,
    schedulePrefListLoading,
    schedulePrefPeopleFilter,
    schedulePrefBatchPicked,
    schedulePrefPeopleFiltered,
    schedulePrefPickedSet,
    peopleRows,
    loadPunchScheduleSettings,
    savePunchScheduleSettings,
    loadPeopleForScheduleBatch,
    submitSchedulePreferenceBatch,
    restorePunchScheduleSettings,
    toggleSchedulePrefPick,
    selectAllSchedulePrefVisible,
    clearSchedulePrefPicks,
} = useRhidPunchSchedule(configuredRef, scheduleErr);

const submit = () => {
    form.put(route('client.rhid.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.rhid_password = '';
        },
    });
};

const testConnection = async () => {
    testing.value = true;
    testMessage.value = null;
    testOk.value = null;
    try {
        const { data } = await axios.post(route('client.rhid.settings.test'));
        testOk.value = data.ok === true;
        testMessage.value = data.message || (data.ok ? 'OK' : 'Falha');
        if (data.needs_domain && data.domains?.length) {
            testMessage.value += ' — Escolha um domínio e salve nas configurações.';
        }
    } catch (e) {
        testOk.value = false;
        testMessage.value = e.response?.data?.message || e.message || 'Erro ao testar';
    } finally {
        testing.value = false;
    }
};

watch(configuredRef, (ok) => {
    if (ok) {
        loadPunchScheduleSettings();
    }
});

onMounted(() => {
    if (props.configured) {
        loadPunchScheduleSettings();
    }
});
</script>

<template>
    <Head title="RHID — Configuração" />

    <ClientLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-talents-900">Integração RHID (Control iD)</h2>
                <Link
                    :href="route('client.rhid.compliance.index')"
                    class="text-sm font-medium text-talents-700 hover:underline"
                >
                    Voltar ao Compliance
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-2xl space-y-8">
            <div class="rounded-xl border border-talents-200 bg-white p-6 shadow-sm">
                <p class="text-sm text-slate-600">
                    Credenciais da API
                    <code class="rounded bg-slate-100 px-1 text-xs">https://www.rhid.com.br/v2</code>
                    . O token é renovado automaticamente (cache ~3,5h).
                </p>

                <form class="mt-6 space-y-4" @submit.prevent="submit">
                    <div>
                        <InputLabel for="rhid_base_url" value="URL base (opcional)" />
                        <TextInput
                            id="rhid_base_url"
                            v-model="form.rhid_base_url"
                            type="url"
                            class="mt-1 block w-full"
                            placeholder="https://www.rhid.com.br/v2"
                        />
                        <InputError class="mt-1" :message="form.errors.rhid_base_url" />
                    </div>
                    <div>
                        <InputLabel for="rhid_email" value="E-mail RHID" />
                        <TextInput
                            id="rhid_email"
                            v-model="form.rhid_email"
                            type="email"
                            class="mt-1 block w-full"
                            autocomplete="off"
                        />
                        <InputError class="mt-1" :message="form.errors.rhid_email" />
                    </div>
                    <div>
                        <InputLabel for="rhid_password" value="Senha" />
                        <TextInput
                            id="rhid_password"
                            v-model="form.rhid_password"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                            placeholder="Deixe em branco para manter a atual"
                        />
                        <p v-if="settings.has_password" class="mt-1 text-xs text-slate-500">Senha já cadastrada.</p>
                        <InputError class="mt-1" :message="form.errors.rhid_password" />
                    </div>
                    <div>
                        <InputLabel for="rhid_domain" value="Domínio (multi-cliente)" />
                        <TextInput
                            id="rhid_domain"
                            v-model="form.rhid_domain"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="ex.: minhaempresa"
                        />
                        <InputError class="mt-1" :message="form.errors.rhid_domain" />
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <PrimaryButton :disabled="form.processing">Salvar</PrimaryButton>
                        <button
                            type="button"
                            class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                            :disabled="testing"
                            @click="testConnection"
                        >
                            {{ testing ? 'Testando...' : 'Testar conexão' }}
                        </button>
                    </div>
                    <p
                        v-if="testMessage"
                        class="text-sm"
                        :class="testOk ? 'text-emerald-700' : 'text-red-700'"
                    >
                        {{ testMessage }}
                    </p>
                </form>
            </div>

            <div
                v-if="configured"
                class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
            >
                <h3 class="text-lg font-semibold text-slate-900">Horários da empresa (aderência)</h3>
                <p class="mt-2 text-sm text-slate-600">
                    Defina no Talents os horários de referência da jornada e intervalos. Esses dados ficam na plataforma e
                    são usados na análise de aderência (espelho importado vs escala). Eles não são enviados ao RHID.
                </p>
                <p v-if="scheduleErr" class="mt-3 rounded-md bg-red-50 p-3 text-sm text-red-800">{{ scheduleErr }}</p>
                <p v-if="scheduleLoading" class="mt-3 text-sm text-slate-500">Carregando configuração...</p>
                <div v-else class="mt-6 space-y-4">
                    <div class="flex flex-wrap gap-6">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-800">
                            <input
                                v-model="scheduleForm.segundo_trabalho"
                                type="checkbox"
                                class="rounded border-slate-300 text-talents-700 focus:ring-talents-600"
                            />
                            Segundo horário de trabalho no mesmo dia
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-800">
                            <input
                                v-model="scheduleForm.segundo_almoco"
                                type="checkbox"
                                class="rounded border-slate-300 text-talents-700 focus:ring-talents-600"
                            />
                            Segundo intervalo de almoço
                        </label>
                    </div>

                    <div class="max-w-xs">
                        <InputLabel value="Tolerância (minutos)" />
                        <input
                            v-model.number="scheduleForm.tolerancia_minutos"
                            type="number"
                            min="0"
                            max="120"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                        />
                        <p class="mt-1 text-xs text-slate-500">
                            Usada na análise de aderência (atrasos em torno dos horários de saída/volta do almoço e
                            duração do intervalo).
                        </p>
                    </div>

                    <div class="max-w-2xl rounded-lg border border-slate-200 bg-slate-50/80 p-4">
                        <h4 class="text-sm font-semibold text-slate-800">Intervalo de almoço por colaborador (lote)</h4>
                        <p class="mt-1 text-xs text-slate-600">
                            Escolha <span class="font-medium">quem participa</span> do horário (por nome). Exige
                            <span class="font-medium">Segundo intervalo de almoço</span> ativo e horários &quot;Início 2º /
                            Fim 2º&quot; nos dias úteis quando for aplicar o 2º intervalo.
                        </p>
                        <div class="mt-4 space-y-3 rounded-md border border-slate-200 bg-white p-3">
                            <p class="text-xs font-medium text-slate-700">O que aplicar aos selecionados</p>
                            <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-800">
                                <input
                                    v-model="schedulePrefBatchSecond"
                                    type="checkbox"
                                    class="mt-0.5 rounded border-slate-300 text-talents-700 focus:ring-talents-600"
                                />
                                <span>
                                    <span class="font-medium">Segundo intervalo de almoço</span>
                                    (comparar batidas com Início 2º / Fim 2º). Desmarque para voltar todos os
                                    selecionados ao <span class="font-medium">primeiro intervalo</span> (saída/volta
                                    do almoço).
                                </span>
                            </label>
                        </div>
                        <div class="mt-4">
                            <SecondaryButton
                                type="button"
                                :disabled="schedulePrefListLoading || scheduleLoading"
                                @click="loadPeopleForScheduleBatch"
                            >
                                {{
                                    peopleRows.length
                                        ? 'Recarregar lista de colaboradores'
                                        : 'Carregar lista de colaboradores'
                                }}
                            </SecondaryButton>
                            <span v-if="schedulePrefListLoading" class="ml-2 text-xs text-slate-500">Carregando…</span>
                        </div>
                        <template v-if="peopleRows.length">
                            <p
                                v-if="schedulePrefBatchSecond && !scheduleForm.segundo_almoco"
                                class="mt-3 rounded border border-amber-200 bg-amber-50 px-2 py-1.5 text-xs text-amber-900"
                            >
                                Ative também <span class="font-medium">Segundo intervalo de almoço</span> acima para
                                que o 2º horário exista na escala.
                            </p>
                            <div class="mt-3">
                                <InputLabel value="Filtrar por nome" />
                                <input
                                    v-model="schedulePrefPeopleFilter"
                                    type="search"
                                    class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                                    placeholder="Digite parte do nome..."
                                />
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="text-xs font-medium text-talents-800 underline"
                                    @click="selectAllSchedulePrefVisible"
                                >
                                    Marcar todos (filtrados)
                                </button>
                                <button
                                    type="button"
                                    class="text-xs font-medium text-slate-600 underline"
                                    @click="clearSchedulePrefPicks"
                                >
                                    Limpar seleção
                                </button>
                                <span class="text-xs text-slate-500">
                                    {{ schedulePrefBatchPicked.length }} selecionado(s) ·
                                    {{ schedulePrefPeopleFiltered.length }} na lista
                                </span>
                            </div>
                            <div
                                class="mt-2 max-h-64 overflow-y-auto rounded border border-slate-200 bg-white p-2 text-sm"
                            >
                                <label
                                    v-for="p in schedulePrefPeopleFiltered"
                                    :key="p.id"
                                    class="flex cursor-pointer items-center gap-2 border-b border-slate-50 py-1.5 last:border-b-0"
                                >
                                    <input
                                        type="checkbox"
                                        class="rounded border-slate-300 text-talents-700 focus:ring-talents-600"
                                        :checked="schedulePrefPickedSet.has(p.id)"
                                        @click.prevent="toggleSchedulePrefPick(p.id)"
                                    />
                                    <span class="min-w-0 flex-1 font-medium text-slate-800">{{ p.name }}</span>
                                    <span class="shrink-0 font-mono text-xs text-slate-500">{{ p.id }}</span>
                                </label>
                                <p v-if="!schedulePrefPeopleFiltered.length" class="py-4 text-center text-xs text-slate-500">
                                    Nenhum nome corresponde ao filtro.
                                </p>
                            </div>
                        </template>
                        <div class="mt-4">
                            <InputLabel value="Opcional: IDs RHID (coleção com a seleção acima)" />
                            <textarea
                                v-model="schedulePrefBatchIds"
                                rows="2"
                                class="mt-1 block w-full rounded-md border border-slate-300 font-mono text-xs shadow-sm"
                                placeholder="Um ou mais IDs extra, separados por vírgula ou linha"
                            />
                        </div>
                        <p v-if="schedulePrefBatchMsg" class="mt-3 text-xs text-emerald-800">{{ schedulePrefBatchMsg }}</p>
                        <PrimaryButton
                            type="button"
                            class="mt-3"
                            :disabled="schedulePrefBatchSaving || scheduleLoading || schedulePrefListLoading"
                            @click="submitSchedulePreferenceBatch"
                        >
                            Aplicar em lote aos selecionados
                        </PrimaryButton>
                    </div>

                    <div class="space-y-4">
                        <div
                            v-for="day in PUNCH_DAY_ORDER"
                            :key="day.key"
                            class="rounded-lg border border-slate-200 bg-slate-50/80 p-4"
                        >
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-900">
                                    <input
                                        v-model="scheduleForm.dias[day.key].ativo"
                                        type="checkbox"
                                        class="rounded border-slate-300 text-talents-700 focus:ring-talents-600"
                                    />
                                    {{ day.label }}
                                </label>
                            </div>
                            <div
                                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                                :class="scheduleForm.dias[day.key].ativo ? '' : 'opacity-60'"
                            >
                                <div>
                                    <label class="text-xs font-medium text-slate-600">Entrada</label>
                                    <input
                                        :value="scheduleForm.dias[day.key].entrada || ''"
                                        type="time"
                                        class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                                        @input="scheduleForm.dias[day.key].entrada = $event.target.value || null"
                                    />
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-slate-600">Saída para almoço</label>
                                    <input
                                        :value="scheduleForm.dias[day.key].saida_almoco || ''"
                                        type="time"
                                        class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                                        @input="scheduleForm.dias[day.key].saida_almoco = $event.target.value || null"
                                    />
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-slate-600">Volta do almoço</label>
                                    <input
                                        :value="scheduleForm.dias[day.key].volta_almoco || ''"
                                        type="time"
                                        class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                                        @input="scheduleForm.dias[day.key].volta_almoco = $event.target.value || null"
                                    />
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-slate-600">Saída</label>
                                    <input
                                        :value="scheduleForm.dias[day.key].saida || ''"
                                        type="time"
                                        class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                                        @input="scheduleForm.dias[day.key].saida = $event.target.value || null"
                                    />
                                </div>
                            </div>
                            <div
                                v-if="scheduleForm.segundo_almoco"
                                class="mt-3 grid gap-3 border-t border-slate-200 pt-3 sm:grid-cols-2"
                            >
                                <div>
                                    <label class="text-xs font-medium text-slate-600">Início 2º almoço</label>
                                    <input
                                        :value="scheduleForm.dias[day.key].almoco2_inicio || ''"
                                        type="time"
                                        class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                                        @input="scheduleForm.dias[day.key].almoco2_inicio = $event.target.value || null"
                                    />
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-slate-600">Fim 2º almoço</label>
                                    <input
                                        :value="scheduleForm.dias[day.key].almoco2_fim || ''"
                                        type="time"
                                        class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                                        @input="scheduleForm.dias[day.key].almoco2_fim = $event.target.value || null"
                                    />
                                </div>
                            </div>
                            <div
                                v-if="scheduleForm.segundo_trabalho"
                                class="mt-3 grid gap-3 border-t border-slate-200 pt-3 sm:grid-cols-2"
                            >
                                <div>
                                    <label class="text-xs font-medium text-slate-600">Entrada 2º trabalho</label>
                                    <input
                                        :value="scheduleForm.dias[day.key].trabalho2_entrada || ''"
                                        type="time"
                                        class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                                        @input="
                                            scheduleForm.dias[day.key].trabalho2_entrada = $event.target.value || null
                                        "
                                    />
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-slate-600">Saída 2º trabalho</label>
                                    <input
                                        :value="scheduleForm.dias[day.key].trabalho2_saida || ''"
                                        type="time"
                                        class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                                        @input="
                                            scheduleForm.dias[day.key].trabalho2_saida = $event.target.value || null
                                        "
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <PrimaryButton type="button" :disabled="scheduleSaving || scheduleLoading" @click="savePunchScheduleSettings">
                            Salvar
                        </PrimaryButton>
                        <SecondaryButton type="button" :disabled="scheduleLoading" @click="restorePunchScheduleSettings">
                            Restaurar
                        </SecondaryButton>
                    </div>
                </div>
            </div>

            <p v-else class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Configure e salve as credenciais RHID acima para editar horários da empresa.
            </p>
        </div>
    </ClientLayout>
</template>
