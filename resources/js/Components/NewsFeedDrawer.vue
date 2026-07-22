<script setup>
import { NewspaperIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { formatRelativeDate } from '@/utils/dateOnly';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const page = usePage();

const open = ref(false);
const loading = ref(false);
const items = ref([]);
const categories = ref([
    { value: 'all', label: 'Todas', emoji: '📰' },
]);
const selectedCategory = ref('all');

const noticesContext = computed(() => page.props.nav?.notices_context ?? null);
const isTalentsContext = computed(() => noticesContext.value === 'talents');
const showNews = computed(() => noticesContext.value !== null);

const feedUrl = computed(() => {
    const name = isTalentsContext.value ? 'admin.news.feed' : 'client.news.feed';
    return route(name);
});

function formatPublished(iso) {
    if (!iso) return '';
    return formatRelativeDate(iso.slice(0, 10));
}

async function fetchNews() {
    loading.value = true;
    try {
        const { data } = await axios.get(feedUrl.value, {
            params: {
                category: selectedCategory.value !== 'all' ? selectedCategory.value : 'all',
            },
        });
        items.value = data.items ?? [];
        if (Array.isArray(data.categories) && data.categories.length) {
            categories.value = data.categories;
        }
    } catch {
        items.value = [];
    } finally {
        loading.value = false;
    }
}

function toggle() {
    open.value = !open.value;
}

function close() {
    open.value = false;
}

function closeOnEscape(event) {
    if (open.value && event.key === 'Escape') {
        close();
    }
}

function selectCategory(value) {
    if (selectedCategory.value === value) return;
    selectedCategory.value = value;
    fetchNews();
}

watch(open, (isOpen) => {
    if (isOpen) {
        fetchNews();
    }
});

onMounted(() => {
    document.addEventListener('keydown', closeOnEscape);
});

onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
    <div v-if="showNews" class="relative">
        <button
            type="button"
            class="relative rounded-full p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-talents-500/30"
            :aria-expanded="open"
            aria-haspopup="dialog"
            aria-label="Notícias"
            @click.stop="toggle"
        >
            <NewspaperIcon class="h-6 w-6" />
        </button>

        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-300 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-200 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="open"
                    class="fixed inset-0 z-[200] bg-slate-900/40 backdrop-blur-sm"
                    aria-hidden="true"
                    @click="close"
                />
            </Transition>

            <Transition
                enter-active-class="transform transition-transform duration-300 ease-out"
                enter-from-class="translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transform transition-transform duration-200 ease-in"
                leave-from-class="translate-x-0"
                leave-to-class="translate-x-full"
            >
                <aside
                    v-if="open"
                    class="fixed inset-y-0 right-0 z-[210] flex w-full flex-col overflow-hidden bg-white shadow-2xl ring-1 ring-slate-200/70 sm:max-w-md sm:rounded-l-2xl lg:max-w-lg"
                    role="dialog"
                    aria-label="Notícias"
                >
                    <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-talents-50 text-talents-700">
                                <NewspaperIcon class="h-5 w-5" />
                            </span>
                            <div>
                                <h2 class="text-sm font-semibold text-slate-900">Notícias</h2>
                                <p class="text-xs text-slate-500">5 destaques relevantes para a sua agenda</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="rounded-full p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-talents-500/30"
                            aria-label="Fechar"
                            @click="close"
                        >
                            <XMarkIcon class="h-5 w-5" />
                        </button>
                    </header>

                    <div class="border-b border-slate-100 px-4 py-3">
                        <div class="flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                            <button
                                v-for="cat in categories"
                                :key="cat.value"
                                type="button"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition focus:outline-none focus:ring-2 focus:ring-talents-500/30"
                                :class="
                                    selectedCategory === cat.value
                                        ? 'bg-talents-600 text-white shadow-sm'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 hover:text-slate-800'
                                "
                                @click="selectCategory(cat.value)"
                            >
                                <span aria-hidden="true">{{ cat.emoji }}</span>
                                <span>{{ cat.label }}</span>
                            </button>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto">
                        <div v-if="loading" class="px-5 py-12 text-center text-sm text-slate-500">
                            A carregar notícias…
                        </div>

                        <ul v-else-if="items.length" class="divide-y divide-slate-100">
                            <li v-for="item in items" :key="item.id">
                                <a
                                    :href="item.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex gap-3 px-5 py-4 transition hover:bg-slate-50"
                                >
                                    <div
                                        v-if="item.image_url"
                                        class="h-16 w-20 shrink-0 overflow-hidden rounded-lg bg-slate-100"
                                    >
                                        <img
                                            :src="item.image_url"
                                            :alt="item.title"
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                            referrerpolicy="no-referrer"
                                        />
                                    </div>
                                    <div
                                        v-else
                                        class="flex h-16 w-20 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-lg"
                                        aria-hidden="true"
                                    >
                                        {{ item.category_emoji || '📰' }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">
                                            <span>{{ item.category_emoji }}</span>
                                            {{ item.category_label }}
                                            <span v-if="item.source"> · {{ item.source }}</span>
                                        </p>
                                        <p class="mt-0.5 text-sm font-semibold leading-snug text-slate-900">
                                            {{ item.title }}
                                        </p>
                                        <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-slate-600">
                                            {{ item.summary }}
                                        </p>
                                        <time
                                            v-if="item.published_at"
                                            class="mt-1.5 block text-[11px] text-slate-400"
                                            :datetime="item.published_at"
                                        >
                                            {{ formatPublished(item.published_at) }}
                                        </time>
                                    </div>
                                </a>
                            </li>
                        </ul>

                        <div v-else class="flex flex-col items-center justify-center px-5 py-16 text-center">
                            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                <NewspaperIcon class="h-6 w-6" />
                            </span>
                            <p class="mt-3 text-sm text-slate-500">Nenhuma notícia disponível neste momento.</p>
                        </div>
                    </div>
                </aside>
            </Transition>
        </Teleport>
    </div>
</template>
