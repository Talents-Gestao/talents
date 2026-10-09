/** URL do portal quando a sessão entrou por /entrar/{slug}. */
let portalLoginUrl = null;

export function rememberPortalLoginUrl(url) {
    portalLoginUrl = url || null;
}

/**
 * Redireciona para login com indicador de sessão expirada.
 * Evita reload em loop se já estiver na página de login.
 */
export function redirectToLoginExpired() {
    const loginUrl = portalLoginUrl
        ? new URL(portalLoginUrl, window.location.origin)
        : new URL(route('login'), window.location.origin);

    const path = window.location.pathname.replace(/\/$/, '') || '/';
    const targetPath = loginUrl.pathname.replace(/\/$/, '') || '/';

    if (path === targetPath) {
        if (loginUrl.searchParams.get('session_expired') !== '1') {
            const url = new URL(window.location.href);
            url.searchParams.set('session_expired', '1');
            window.history.replaceState({}, '', url.toString());
        }

        return;
    }

    loginUrl.searchParams.set('session_expired', '1');
    window.location.assign(loginUrl.toString());
}

/**
 * @param {number} status
 */
export function isSessionExpiredHttpStatus(status) {
    return status === 401 || status === 419;
}
