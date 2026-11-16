/**
 * Lógica pura do ícone de ajuda do painel NR-1 (testada em tests/JavaScript/nr1HelpTip.test.mjs).
 */
let sequence = 0;

/** Id único e estável por instância, para ligar o botão ao balão por aria-describedby. */
export function helpTipId() {
    sequence += 1;

    return `n1-ajuda-${sequence}`;
}
