import { onBeforeUnmount, onMounted } from 'vue';

/**
 * Entrada ao rolar (reveal) para os filhos `.lg-reveal` de um elemento.
 * Sem IntersectionObserver ou com prefers-reduced-motion, o conteúdo permanece visível.
 */
export function useReveal(rootRef) {
    let io = null;

    onMounted(() => {
        const root = rootRef.value;
        if (!root || typeof IntersectionObserver === 'undefined') return;
        if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;

        const targets = root.querySelectorAll('.lg-reveal');
        io = new IntersectionObserver(
            (entries) => {
                entries.forEach((e) => {
                    if (e.isIntersecting) {
                        e.target.classList.add('is-in');
                        io.unobserve(e.target);
                    }
                });
            },
            { rootMargin: '0px 0px -8% 0px', threshold: 0.08 },
        );
        targets.forEach((el, i) => {
            if (!el.style.getPropertyValue('--lg-delay')) {
                el.style.setProperty('--lg-delay', `${(i % 4) * 70}ms`);
            }
            io.observe(el);
        });
        root.classList.add('lg-reveal-ready');
    });

    onBeforeUnmount(() => io?.disconnect());
}

/** Navegação por setas/Home/End em um tablist (foco e seleção seguem juntos). */
export function onTablistKeydown(event, count, current, select) {
    const keys = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 };
    let next = null;
    if (event.key in keys) next = (current + keys[event.key] + count) % count;
    else if (event.key === 'Home') next = 0;
    else if (event.key === 'End') next = count - 1;
    if (next === null) return;
    event.preventDefault();
    select(next);
}
