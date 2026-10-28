const INTRO_WINDOW_MS = 1800;

let startedAt = null;

/**
 * `true` só durante a primeira renderização da Home na sessão (contagem animada e entrada em
 * sequência). Reordenar cartões, trocar de aba do funil ou receber dados novos remonta ou atualiza
 * componentes depois dessa janela e não repete a animação. Sempre `false` com prefers-reduced-motion.
 */
export function useHomeIntro() {
    if (typeof window === 'undefined') {
        return false;
    }

    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
        return false;
    }

    const now = performance.now();
    if (startedAt === null) {
        startedAt = now;
    }

    return now - startedAt < INTRO_WINDOW_MS;
}
