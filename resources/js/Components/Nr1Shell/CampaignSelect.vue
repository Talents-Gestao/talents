<script setup>
import { router } from '@inertiajs/vue3';
import { CalendarDaysIcon, CheckIcon, ChevronUpDownIcon } from '@heroicons/vue/24/outline';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import Nr1Button from './Nr1Button.vue';

/**
 * Seletor de campanha (dentro da mesma empresa): botão com rótulo «Campanha», lista suspensa estilizada com a campanha
 * ativa marcada e teclado completo (setas, Home, End, Enter, Esc). Com uma só campanha fica só de leitura.
 */
const props = defineProps({
    context: { type: Object, required: true },
});

const open = ref(false);
const root = ref(null);
const list = ref(null);
const highlighted = ref(0);

const surveys = computed(() => props.context.surveys ?? []);
const current = computed(() => surveys.value.find((survey) => survey.id === props.context.survey?.id) ?? null);
const interactive = computed(() => surveys.value.length > 1);

const close = () => {
    open.value = false;
};
const toggle = async () => {
    if (!interactive.value) return;
    open.value = !open.value;
    if (open.value) {
        highlighted.value = Math.max(0, surveys.value.findIndex((survey) => survey.id === current.value?.id));
        await nextTick();
        list.value?.focus();
    }
};
const choose = (survey) => {
    close();
    if (survey.id !== current.value?.id) {
        router.get(window.location.pathname, { empresa: props.context.company?.id, campanha: survey.id }, { preserveScroll: true });
    }
};
const onKey = (event) => {
    const last = surveys.value.length - 1;
    const keys = {
        ArrowDown: () => (highlighted.value = Math.min(last, highlighted.value + 1)),
        ArrowUp: () => (highlighted.value = Math.max(0, highlighted.value - 1)),
        Home: () => (highlighted.value = 0),
        End: () => (highlighted.value = last),
        Enter: () => choose(surveys.value[highlighted.value]),
        ' ': () => choose(surveys.value[highlighted.value]),
        Escape: close,
        Tab: close,
    };
    if (keys[event.key]) {
        event.preventDefault();
        keys[event.key]();
    }
};
const outside = (event) => {
    if (root.value && !root.value.contains(event.target)) close();
};
onMounted(() => document.addEventListener('click', outside));
onBeforeUnmount(() => document.removeEventListener('click', outside));
</script>

<template>
    <div ref="root" class="n1-campsel">
        <span id="n1-campsel-label" class="n1-campsel-label">Campanha</span>
        <Nr1Button
            variant="secondary"
            class="n1-campsel-btn"
            aria-haspopup="listbox"
            :aria-expanded="interactive ? open : undefined"
            aria-labelledby="n1-campsel-label n1-campsel-value"
            :disabled="!interactive"
            @click="toggle"
        >
            <CalendarDaysIcon class="n1-campsel-icon" aria-hidden="true" />
            <span id="n1-campsel-value" class="n1-campsel-value" :title="current?.title">{{ current?.title ?? 'Selecione' }}</span>
            <ChevronUpDownIcon v-if="interactive" class="n1-campsel-icon" aria-hidden="true" />
        </Nr1Button>

        <ul
            v-if="open"
            ref="list"
            class="n1-campsel-list"
            role="listbox"
            tabindex="-1"
            aria-labelledby="n1-campsel-label"
            :aria-activedescendant="'n1-camp-' + surveys[highlighted]?.id"
            @keydown="onKey"
        >
            <li
                v-for="(survey, index) in surveys"
                :id="'n1-camp-' + survey.id"
                :key="survey.id"
                role="option"
                :aria-selected="survey.id === current?.id"
                :class="{ active: survey.id === current?.id, hl: index === highlighted }"
                @click="choose(survey)"
                @mouseenter="highlighted = index"
            >
                <span class="n1-campsel-opt" :title="survey.title">{{ survey.title }}<small v-if="survey.year && !survey.title.includes(String(survey.year))"> · {{ survey.year }}</small><small v-if="survey.legacy" class="n1-campsel-legacy"> · modelo anterior</small></span>
                <CheckIcon v-if="survey.id === current?.id" class="n1-campsel-check" aria-hidden="true" />
            </li>
        </ul>
    </div>
</template>
