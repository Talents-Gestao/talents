<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { duplicatesHeadline, duplicatesOutcome, duplicatesSummary } from '@/utils/nr1MeasureDuplicates';
import Nr1Button from './Nr1Button.vue';
import { useNr1Toast } from './useNr1Toast';

/**
 * «Unificar sugestões duplicadas» (só Talents): prévia por dimensão (mantida, juntada, removida, requer decisão
 * manual) e confirmação explícita. O servidor recusa se a prévia mudou desde que foi mostrada.
 */
const props = defineProps({
    duplicates: { type: Object, required: true },
});

const { push } = useNr1Toast();
const page = usePage();
const open = ref(false);
const confirmed = ref(false);
const busy = ref(false);

const summary = computed(() => duplicatesSummary(props.duplicates.groups));

const apply = () => {
    if (!confirmed.value || !summary.value.canApply) return;
    busy.value = true;
    router.patch(
        props.duplicates.url,
        { confirm: true, expected_ids: props.duplicates.removable_ids },
        {
            preserveScroll: true,
            onSuccess: () => {
                const flash = page.props.flash ?? {};
                if (flash.error) {
                    push(flash.error, 'error');
                } else {
                    push(flash.success ?? 'Sugestões unificadas.');
                    open.value = false;
                }
                confirmed.value = false;
            },
            onError: (errors) => push(Object.values(errors)[0] ?? 'Não foi possível unificar.', 'error'),
            onFinish: () => {
                busy.value = false;
            },
        },
    );
};
</script>

<template>
    <div class="n1-card c12 no-print" role="region" aria-labelledby="nr1-duplicates-title">
        <div class="n1-sectiontop">
            <h2 id="nr1-duplicates-title">Sugestões duplicadas</h2>
            <span class="n1-pill warn">Só Talents</span>
        </div>
        <p>{{ duplicatesHeadline(duplicates.groups) }} Só sai sugestão da Mia sem alteração; medidas manuais, editadas, confirmadas ou com evidência são sempre mantidas.</p>
        <Nr1Button v-if="!open" variant="primary" @click="open = true">Unificar sugestões duplicadas</Nr1Button>

        <div v-else>
            <div v-for="group in duplicates.groups" :key="group.dimension + group.kept.id" class="n1-card" style="margin-top: 10px">
                <h3 class="n1-dimname" style="font-size: 14px">{{ group.dimension }}</h3>
                <p class="n1-tiny"><b>Mantida:</b> #{{ group.kept.id }} · {{ group.kept.origin_label }} · {{ group.kept.status_label }} — {{ group.kept.measure_description }}</p>
                <p v-if="group.merged" class="n1-tiny"><b>Juntada:</b> a ação das sugestões removidas entra no texto da mantida, sem repetir frases.</p>
                <p v-for="item in group.removed" :key="'r' + item.id" class="n1-tiny"><b>Removida:</b> #{{ item.id }} · {{ item.origin_label }} — {{ item.measure_description }}</p>
                <p v-for="item in group.manual" :key="'m' + item.id" class="n1-tiny"><b>Requer decisão manual:</b> #{{ item.id }} · {{ item.origin_label }} · {{ item.status_label }} ({{ item.reason }})</p>
            </div>

            <p style="margin-top: 10px"><b>Resultado:</b> {{ duplicatesOutcome(duplicates.groups) }}</p>
            <p v-if="!summary.canApply" class="n1-tiny" role="status">Nenhuma sugestão pode ser removida automaticamente. Ajuste ou exclua as medidas indicadas no cadastro de medidas.</p>
            <template v-else>
                <label class="n1-tiny" style="display: flex; gap: 8px; align-items: center; margin: 8px 0">
                    <input v-model="confirmed" type="checkbox" />
                    Revisei a prévia e confirmo a unificação. A remoção não pode ser desfeita.
                </label>
                <Nr1Button variant="primary" :disabled="!confirmed" :loading="busy" @click="apply">Confirmar unificação</Nr1Button>
            </template>
            <Nr1Button variant="secondary" style="margin-left: 8px" @click="open = false; confirmed = false">Cancelar</Nr1Button>
        </div>
    </div>
</template>
