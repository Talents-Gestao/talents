<script setup>
import EmptyState from './EmptyState.vue';

/** Histórico em linha do tempo: evento, texto, autor e data (os eventos que o perfil pode ver). */
defineProps({
    history: { type: Array, required: true },
});
</script>

<template>
    <div>
        <EmptyState v-if="!history.length" title="Sem eventos ainda" text="O histórico aparece aqui conforme a revisão avança." />
        <ol v-else class="n1-timeline-v" aria-label="Histórico da revisão">
            <li v-for="event in history" :key="event.id">
                <i aria-hidden="true"></i>
                <div>
                    <b>{{ event.label }}</b>
                    <p v-if="event.body">{{ event.body }}</p>
                    <small>{{ event.author }} · {{ event.at }}<template v-if="event.from && event.to && event.from !== event.to"> · {{ event.from }} → {{ event.to }}</template></small>
                </div>
            </li>
        </ol>
    </div>
</template>
