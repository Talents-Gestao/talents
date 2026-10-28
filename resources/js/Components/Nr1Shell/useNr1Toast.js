import { reactive } from 'vue';

const state = reactive({ items: [] });
let next = 1;

/** Toasts do shell NR-1 (estado do módulo; o ToastHost fica no Nr1Layout). */
export function useNr1Toast() {
    const push = (message, tone = 'ok') => {
        const id = next++;
        state.items.push({ id, message, tone });
        setTimeout(() => {
            const index = state.items.findIndex((item) => item.id === id);
            if (index >= 0) state.items.splice(index, 1);
        }, 3800);
    };

    return { toasts: state.items, push };
}
