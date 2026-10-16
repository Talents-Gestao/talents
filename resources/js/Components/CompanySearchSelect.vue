<script setup>
import { ChevronUpDownIcon } from '@heroicons/vue/24/outline';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    companies: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Pesquisar empresa' },
    emptyLabel: { type: String, default: '' },
    inputClass: { type: String, default: '' },
    id: { type: String, default: undefined },
    required: { type: Boolean, default: false },
    asString: { type: Boolean, default: false },
});

const model = defineModel({ type: [String, Number], default: '' });

const emit = defineEmits(['change']);

const open = ref(false);
const query = ref('');
const activeIndex = ref(0);
const navigated = ref(false);
const inputEl = ref(null);
const menuEl = ref(null);
const menuTarget = ref(typeof document === 'undefined' ? 'body' : document.body);
const menuStyle = ref({});

const normalize = (value) =>
    String(value ?? '')
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .toLowerCase()
        .trim();

const selected = computed(() =>
    props.companies.find((company) => String(company.id) === String(model.value ?? '')),
);

const filtered = computed(() => {
    const term = normalize(query.value);
    const selectedName = selected.value ? normalize(selected.value.name) : '';

    if (term === '' || term === selectedName) {
        return props.companies;
    }

    return props.companies.filter((company) => normalize(company.name).includes(term));
});

const isSearching = computed(() => {
    const term = normalize(query.value);
    const selectedName = selected.value ? normalize(selected.value.name) : '';

    return term !== '' && term !== selectedName;
});

const syncQuery = () => {
    query.value = selected.value?.name ?? '';
};

const syncValidity = () => {
    const el = inputEl.value;

    if (!el) {
        return;
    }

    if (!props.required) {
        el.setCustomValidity('');

        return;
    }

    const selectedName = selected.value ? normalize(selected.value.name) : '';
    const current = normalize(query.value);
    const matchesSelection = String(model.value ?? '') !== '' && current === selectedName;

    el.setCustomValidity(matchesSelection ? '' : 'Selecione uma empresa da lista.');
};

watch(
    model,
    () => {
        if (!open.value) {
            syncQuery();
        }
    },
    { immediate: true },
);

watch(
    () => props.companies,
    () => {
        if (!open.value) {
            syncQuery();
        }
    },
);

watch(filtered, () => {
    if (isSearching.value) {
        activeIndex.value = 0;
    }
});

const commit = (value, label) => {
    model.value = value;
    query.value = label;
    open.value = false;
    navigated.value = false;
    emit('change', value);
    syncValidity();
};

const selectCompany = (company) => {
    commit(props.asString ? String(company.id) : company.id, company.name);
};

const clearSelection = () => {
    commit('', '');
};

const placeMenu = () => {
    const el = inputEl.value;

    if (!el) {
        return;
    }

    const rect = el.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.bottom;
    const openUp = spaceBelow < 180 && rect.top > spaceBelow;

    menuStyle.value = {
        left: `${rect.left}px`,
        width: `${rect.width}px`,
        top: openUp ? 'auto' : `${rect.bottom + 4}px`,
        bottom: openUp ? `${window.innerHeight - rect.top + 4}px` : 'auto',
    };
};

const onReposition = () => {
    if (open.value) {
        placeMenu();
    }
};

watch(open, (isOpen) => {
    if (isOpen) {
        nextTick(() => {
            placeMenu();
            const option = menuEl.value?.querySelector('[aria-selected="true"]');

            if (option && menuEl.value) {
                menuEl.value.scrollTop = Math.max(option.offsetTop - menuEl.value.clientHeight / 2, 0);
            }
        });
        window.addEventListener('scroll', onReposition, true);
        window.addEventListener('resize', onReposition);

        return;
    }

    window.removeEventListener('scroll', onReposition, true);
    window.removeEventListener('resize', onReposition);
});

onUnmounted(() => {
    window.removeEventListener('scroll', onReposition, true);
    window.removeEventListener('resize', onReposition);
});

const highlightSelected = () => {
    const index = filtered.value.findIndex(
        (company) => String(company.id) === String(model.value ?? ''),
    );
    activeIndex.value = index >= 0 ? index : 0;
};

const onFocus = (event) => {
    menuTarget.value = event.target.closest('dialog') ?? document.body;
    highlightSelected();
    open.value = true;
    navigated.value = false;
    event.target.select();
};

const onInput = () => {
    open.value = true;
    navigated.value = false;
    syncValidity();
};

const onBlur = () => {
    window.setTimeout(() => {
        open.value = false;

        if (query.value.trim() === '') {
            if (String(model.value ?? '') !== '') {
                commit('', '');
            } else {
                syncValidity();
            }

            return;
        }

        syncQuery();
        syncValidity();
    }, 120);
};

const onKeydown = (event) => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        open.value = true;

        if (!navigated.value) {
            navigated.value = true;
            activeIndex.value = Math.max(Math.min(activeIndex.value, filtered.value.length - 1), 0);

            return;
        }

        activeIndex.value = Math.min(activeIndex.value + 1, Math.max(filtered.value.length - 1, 0));

        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        open.value = true;
        navigated.value = true;
        activeIndex.value = Math.max(activeIndex.value - 1, 0);

        return;
    }

    if (event.key === 'Escape') {
        open.value = false;
        navigated.value = false;
        syncQuery();
        syncValidity();

        return;
    }

    if (event.key === 'Enter' && open.value && (isSearching.value || navigated.value)) {
        event.preventDefault();
        const company = filtered.value[activeIndex.value];

        if (company) {
            selectCompany(company);
        }
    }
};

const defaultClass =
    'block w-full rounded-xl border border-slate-300 py-2.5 pl-3 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500';
</script>

<template>
    <div v-bind="$attrs">
        <div class="relative">
            <input
                :id="id"
                ref="inputEl"
                v-model="query"
                type="text"
                role="combobox"
                autocomplete="off"
                spellcheck="false"
                aria-autocomplete="list"
                :aria-expanded="open"
                :required="required"
                :placeholder="emptyLabel || placeholder"
                :class="[inputClass || defaultClass, 'w-full !pr-10']"
                @focus="onFocus"
                @input="onInput"
                @blur="onBlur"
                @keydown="onKeydown"
            />
            <ChevronUpDownIcon
                class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
            />

            <Teleport :to="menuTarget">
            <ul
                v-if="open"
                ref="menuEl"
                class="fixed z-[80] max-h-60 overflow-auto rounded-xl border border-slate-200 bg-white py-1 text-sm shadow-lg"
                :style="menuStyle"
                role="listbox"
            >
                <li v-if="emptyLabel">
                    <button
                        type="button"
                        class="block w-full px-3 py-2 text-left text-slate-500 hover:bg-slate-50"
                        @mousedown.prevent="clearSelection"
                    >
                        {{ emptyLabel }}
                    </button>
                </li>
                <li v-for="(company, index) in filtered" :key="company.id">
                    <button
                        type="button"
                        class="block w-full px-3 py-2 text-left"
                        :class="
                            index === activeIndex
                                ? 'bg-talents-600 text-white'
                                : 'text-slate-900 hover:bg-slate-50'
                        "
                        role="option"
                        :aria-selected="String(company.id) === String(model ?? '')"
                        @mouseenter="activeIndex = index"
                        @mousedown.prevent="selectCompany(company)"
                    >
                        {{ company.name }}
                    </button>
                </li>
                <li v-if="filtered.length === 0" class="px-3 py-2 text-slate-500">
                    Nenhuma empresa encontrada.
                </li>
            </ul>
            </Teleport>
        </div>
    </div>
</template>
