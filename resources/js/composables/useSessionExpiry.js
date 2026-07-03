import { redirectToLoginExpired } from '@/utils/sessionExpiry';
import { router, usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, watch } from 'vue';

/**
 * Agenda aviso e expiração com base em `page.props.session` (atualizado a cada visita Inertia).
 *
 * @param {(minutesRemaining: number) => void} onWarning
 */
export function useSessionExpiry(onWarning) {
    const page = usePage();
    const warningVisible = ref(false);
    const minutesRemaining = ref(5);

    /** @type {ReturnType<typeof setTimeout> | null} */
    let warningTimer = null;
    /** @type {ReturnType<typeof setTimeout> | null} */
    let expiryTimer = null;

    function clearTimers() {
        if (warningTimer !== null) {
            clearTimeout(warningTimer);
            warningTimer = null;
        }
        if (expiryTimer !== null) {
            clearTimeout(expiryTimer);
            expiryTimer = null;
        }
    }

    function schedule(session) {
        clearTimers();
        warningVisible.value = false;

        if (!session?.expires_at) {
            return;
        }

        const now = Date.now();
        const expiresAt = session.expires_at;
        const warningMinutes = session.warning_minutes ?? 5;
        const warningMs = warningMinutes * 60 * 1000;
        const warningAt = expiresAt - warningMs;

        if (expiresAt <= now) {
            redirectToLoginExpired();
            return;
        }

        if (warningAt <= now) {
            minutesRemaining.value = Math.max(
                1,
                Math.ceil((expiresAt - now) / 60000),
            );
            warningVisible.value = true;
            onWarning?.(minutesRemaining.value);
        } else {
            warningTimer = setTimeout(() => {
                minutesRemaining.value = Math.max(
                    1,
                    Math.ceil((expiresAt - Date.now()) / 60000),
                );
                warningVisible.value = true;
                onWarning?.(minutesRemaining.value);
            }, warningAt - now);
        }

        expiryTimer = setTimeout(redirectToLoginExpired, expiresAt - now);
    }

    function dismissWarning() {
        warningVisible.value = false;
    }

    watch(
        () => page.props?.session,
        (session) => schedule(session),
        { immediate: true },
    );

    const removeSuccessListener = router.on('success', () => {
        schedule(page.props?.session);
    });

    onMounted(() => {
        schedule(page.props?.session);
    });

    onUnmounted(() => {
        clearTimers();
        removeSuccessListener();
    });

    return {
        warningVisible,
        minutesRemaining,
        dismissWarning,
    };
}
