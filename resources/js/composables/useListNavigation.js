import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import { isListNavigation } from '@/utils/financeLedger';

/**
 * «Carregando» das listas do Financeiro: verdadeiro enquanto uma visita GET para esta
 * mesma tela (filtros, abas, paginação, agrupar) está em andamento. A tela usa isso para
 * desabilitar controles e esmaecer a tabela — no ambiente local cada visita leva segundos.
 */
export function useListNavigation() {
    const navigating = ref(false);
    const listeners = [];

    onMounted(() => {
        listeners.push(
            router.on('start', (event) => {
                if (isListNavigation(event.detail.visit, window.location.href)) {
                    navigating.value = true;
                }
            }),
            router.on('finish', () => {
                navigating.value = false;
            }),
        );
    });

    onUnmounted(() => {
        listeners.splice(0).forEach((remove) => remove());
    });

    return { navigating };
}
