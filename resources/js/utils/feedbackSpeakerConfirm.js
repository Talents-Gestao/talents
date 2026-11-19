/**
 * Etapa «Confirmar falantes» (só apresentação): nomes de exibição («Falante 1» no lugar de «Speaker 1»), rótulo
 * «Nome — papel», frase de confirmação, inversão e escolha exclusiva de líder e colaborador. A sugestão vem do
 * servidor (SpeakerRoleSuggestion) e o servidor continua validando o mapa como antes (os rótulos enviados são os
 * originais).
 */
const ROLE_NOUN = { leader: 'líder', employee: 'colaborador' };
const UNIQUE_ROLES = ['leader', 'employee'];

/** «Speaker 1» → «Falante 1»; rótulos com nome ficam como estão. */
export function speakerDisplay(label) {
    return String(label ?? '').replace(/\bspeaker\s*(\d+)\b/gi, 'Falante $1');
}

/** Texto do servidor com «Speaker N» trocado por «Falante N». */
export function displayText(text) {
    return text ? speakerDisplay(text) : '';
}

/** Opção do select: «Rafael Mendes — líder», «Líder» (sem nome) ou «Outra pessoa». */
export function personLabel(role, people = {}) {
    if (!UNIQUE_ROLES.includes(role)) return 'Outra pessoa';
    const name = (role === 'leader' ? people.leader : people.employee)?.trim();
    const noun = ROLE_NOUN[role];

    return name ? `${name} — ${noun}` : noun.charAt(0).toUpperCase() + noun.slice(1);
}

/** Papel inicial de cada rótulo: o já confirmado, senão a sugestão, senão a ordem (1º líder, 2º colaborador). */
export function initialRoles(speakers = [], suggestion = null) {
    const roles = {};
    speakers.forEach((s, i) => {
        roles[s.label] = s.role ?? suggestion?.map?.[s.label] ?? (i === 0 ? 'leader' : i === 1 ? 'employee' : 'other');
    });

    return roles;
}

/** Troca líder e colaborador (os demais ficam como estão). */
export function invertRoles(roles) {
    const out = {};
    for (const [label, role] of Object.entries(roles)) {
        out[label] = role === 'leader' ? 'employee' : role === 'employee' ? 'leader' : role;
    }

    return out;
}

/**
 * Escolha exclusiva: líder e colaborador não se repetem. Escolher para um falante a pessoa que já está em outro troca
 * os dois (o outro fica com o papel que este tinha). «Outra pessoa» pode repetir.
 *
 * @returns {{ roles: Record<string, string>, swappedWith: string|null }}
 */
export function chooseRole(roles, label, role) {
    const previous = roles[label];
    const out = { ...roles, [label]: role };
    if (!UNIQUE_ROLES.includes(role) || previous === role) return { roles: out, swappedWith: null };

    const other = Object.keys(roles).find((l) => l !== label && roles[l] === role);
    if (!other) return { roles: out, swappedWith: null };
    out[other] = previous ?? 'other';

    return { roles: out, swappedWith: other };
}

/** «Identificamos Falante 1 como Rafael Mendes, líder, e Falante 2 como Bruno Siqueira, colaborador.» */
export function confirmSentence(speakers, roles, people = {}, prefix = 'Identificamos') {
    const part = (s) => {
        const role = roles[s.label] ?? 'other';
        const who = speakerDisplay(s.label);
        if (!UNIQUE_ROLES.includes(role)) return `${who} como outra pessoa`;
        const name = (role === 'leader' ? people.leader : people.employee)?.trim();

        return name ? `${who} como ${name}, ${ROLE_NOUN[role]}` : `${who} como ${ROLE_NOUN[role]}`;
    };
    const parts = speakers.map(part);
    const named = speakers.some((s) => UNIQUE_ROLES.includes(roles[s.label]) && (roles[s.label] === 'leader' ? people.leader : people.employee)?.trim());
    const joined = parts.length === 2 ? parts.join(named ? ', e ' : ' e ') : parts.join('; ');

    return `${prefix} ${joined}.`;
}

/** Aviso (aria-live) depois de uma troca automática. */
export function swapAnnouncement(otherLabel, roles, people = {}) {
    return `Troca automática: ${speakerDisplay(otherLabel)} agora é ${personLabel(roles[otherLabel], people)}.`;
}

/** Confirmação rápida só com exatamente 2 falantes e confiança alta, e se o usuário não pediu o ajuste. */
export function useQuickConfirm(ai, manual) {
    return !manual && (ai?.speakers?.length ?? 0) === 2 && ai?.speaker_suggestion?.confidence === 'high';
}

/** Mapa válido para enviar: pelo menos um líder e um colaborador. */
export function missingRolesText(roles) {
    const values = Object.values(roles);
    const missing = [];
    if (!values.includes('leader')) missing.push('um líder');
    if (!values.includes('employee')) missing.push('um colaborador');

    return missing.length ? `Falta indicar ${missing.join(' e ')}.` : '';
}
