/**
 * Redireciona para login com indicador de sessão expirada.
 * Evita reload em loop se já estiver na página de login.
 */
export function redirectToLoginExpired() {
    const path = window.location.pathname.replace(/\/$/, '') || '/';
    const onLogin = path === '/login' || path.endsWith('/login');

    if (onLogin) {
        const url = new URL(window.location.href);
        if (url.searchParams.get('session_expired') !== '1') {
            url.searchParams.set('session_expired', '1');
            window.history.replaceState({}, '', url.toString());
        }

        return;
    }

    const loginUrl = new URL(route('login'), window.location.origin);
    loginUrl.searchParams.set('session_expired', '1');
    window.location.assign(loginUrl.toString());
}

/**
 * @param {number} status
 */
export function isSessionExpiredHttpStatus(status) {
    return status === 401 || status === 419;
}
