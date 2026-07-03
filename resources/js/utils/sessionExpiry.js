/**
 * Redireciona para login com indicador de sessão expirada.
 */
export function redirectToLoginExpired() {
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
