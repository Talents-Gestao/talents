/**
 * Seção «Gravação e relatório» (só apresentação): qual etapa mostrar, a linha de resumo dos falantes e a leitura da
 * transcrição como conversa (uma fala por linha, com tempo e quem fala). Lê o que o servidor já manda
 * (recording, recordingAi e access); não decide permissão nem regra.
 */
import { speakerDisplay } from './feedbackSpeakerConfirm.js';

/** Selo e tom de cada estado da seção. */
export const RECORDING_STATES = {
    empty: { label: 'Sem gravação', tone: 'neutral' },
    speakers: { label: 'Confirmar falantes', tone: 'action' },
    waiting: { label: 'Aguardando liberação da IA', tone: 'neutral' },
    limit: { label: 'Limite de IA atingido', tone: 'warning' },
    queued: { label: 'Pronto para processar', tone: 'action' },
    processing: { label: 'Processando', tone: 'action' },
    ready: { label: 'Relatório pronto', tone: 'success' },
    failed: { label: 'Erro', tone: 'danger' },
    idle: { label: 'Transcrição guardada', tone: 'neutral' },
};

/**
 * Estado atual da seção.
 * - empty: sem gravação; speakers: falta confirmar quem fala (e o usuário pode confirmar);
 * - waiting: IA não liberada pela Talents; limit: limite mensal atingido; queued: pode processar;
 * - processing, ready, failed: andamento e resultado; idle: gravação guardada sem ação possível aqui.
 */
export function recordingState(recording, ai, access = {}) {
    if (!recording) return 'empty';
    if (!ai) return 'idle';
    if (ai.status === 'processing') return 'processing';
    if (ai.status === 'ready') return 'ready';
    if (ai.status === 'failed') return 'failed';

    const canAct = !!access.canProcess && !access.locked;
    const needsMapping = (ai.speakers?.length ?? 0) > 0 && !ai.speakers_mapped;
    if (needsMapping && canAct) return 'speakers';
    if (access.locked) return 'idle';
    if (ai.gate?.allowed !== true) return 'waiting';
    if (ai.limit_reached) return 'limit';

    return canAct ? 'queued' : 'idle';
}

/** «Alterar falantes»: só antes de processar (depois, o servidor recusa), para quem pode processar. */
export function canAdjustSpeakers(ai, access = {}) {
    return ai?.status === 'uploaded' && (ai?.speakers?.length ?? 0) > 0 && !ai?.clean && !!access.canProcess && !access.locked;
}

/** «Gerar relatório novamente»: mesmas condições de antes. */
export function canRegenerateReport(ai, access = {}) {
    return !!ai?.clean && ai?.gate?.allowed === true && !ai?.limit_reached && ['ready', 'failed'].includes(ai?.status) && !!access.canProcess && !access.locked;
}

/** Nome sem o texto entre parênteses do fim («Rafael Mendes (fictício)» → «Rafael Mendes»). */
export function plainName(name) {
    return String(name ?? '').replace(/\s*\([^)]*\)\s*$/u, '').trim();
}

const ROLE_NOUN = { leader: 'líder', employee: 'colaborador', other: 'outra pessoa' };

/** «Falantes: Falante 1 = Rafael Mendes (líder) · Falante 2 = Bruno Siqueira (colaborador)» (sem o «Falantes:»). */
export function speakersLine(speakers = [], people = {}) {
    return speakers
        .map((s) => {
            const who = speakerDisplay(s.label);
            const role = s.role ?? null;
            if (role === 'leader' || role === 'employee') {
                const name = plainName(role === 'leader' ? people.leader : people.employee);
                return name ? `${who} = ${name} (${ROLE_NOUN[role]})` : `${who} = ${ROLE_NOUN[role]}`;
            }
            if (role === 'other') return `${who} = outra pessoa`;

            return `${who} = não confirmado`;
        })
        .join(' · ');
}

// ── Transcrição como conversa ──

const TIME = String.raw`\[?\d{1,2}:\d{2}(?::\d{2})?(?:[.,]\d{1,3})?\]?`;
const GENERIC = String.raw`(?:[Ss][Pp][Ee][Aa][Kk][Ee][Rr]|[Ff]alante|[Oo]rador|[Ll]ocutor|[Pp]articipante|[Ii]nterlocutor)\s*\d+`;
const ROLE = String.raw`(?:Líder|Colaborador|Outra pessoa(?:\s+\d+)?)`;
const NAME = String.raw`\p{Lu}[\p{L}'.-]*(?:\s+\p{Lu}[\p{L}'.-]*){0,3}`;
const LABEL = `(${GENERIC}|${ROLE}|${NAME})`;

const PATTERNS = [
    // «[00:00:01] Speaker 1: texto» / «Speaker 1: texto»
    { re: new RegExp(String.raw`^\s*(?:(${TIME})\s*)?${LABEL}\s*:\s*(.*)$`, 'u'), time: 1, label: 2, speech: 3 },
    // «00:00:01 Speaker 1» (a fala vem nas linhas seguintes)
    { re: new RegExp(String.raw`^\s*(${TIME})\s+${LABEL}\s*$`, 'u'), time: 1, label: 2 },
    // «Speaker 1 00:00:01»
    { re: new RegExp(String.raw`^\s*${LABEL}\s+(${TIME})\s*$`, 'u'), time: 2, label: 1 },
    // «Speaker 1 texto» (só rótulo genérico)
    { re: new RegExp(String.raw`^\s*(?:(${TIME})\s*)?(${GENERIC})\s+(\S.*)$`, 'u'), time: 1, label: 2, speech: 3, genericOnly: true },
];
const TIME_ONLY = new RegExp(String.raw`^\s*(${TIME})\s*$`, 'u');
const TIME_TEXT = new RegExp(String.raw`^\s*\[(\d{1,2}:\d{2}(?::\d{2})?)\]\s+(\S.*)$`, 'u');
const IS_GENERIC = new RegExp(String.raw`^${GENERIC}$`, 'u');
const IS_ROLE = new RegExp(String.raw`^${ROLE}$`, 'u');

/** «00:00:01» / «[01:02:03,500]» → «00:01» / «62:03» (minutos totais e segundos). */
export function clock(raw) {
    const parts = String(raw ?? '').replace(/[[\]]/g, '').split(/[.,]/)[0].split(':').map((n) => parseInt(n, 10));
    if (parts.some(Number.isNaN) || parts.length < 2) return '';
    const [h, m, s] = parts.length === 3 ? parts : [0, parts[0], parts[1]];
    const minutes = h * 60 + m;

    return `${String(minutes).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

function parseLabelLine(line) {
    for (const p of PATTERNS) {
        const m = line.match(p.re);
        if (!m) continue;
        const label = m[p.label].trim().replace(/\s+/g, ' ');
        if (!IS_GENERIC.test(label) && !IS_ROLE.test(label) && label.length > 40) continue;

        return { time: p.time && m[p.time] ? m[p.time] : '', label, speech: p.speech ? (m[p.speech] ?? '').trim() : '' };
    }

    return null;
}

/** Quem fala, para exibir: nome mapeado (sem parênteses), papel ou «Falante N». */
function resolveSpeaker(label, roleByKey, people) {
    const key = label.toLowerCase();
    let role = roleByKey[key] ?? null;
    if (!role && /^líder$/iu.test(label)) role = 'leader';
    if (!role && /^colaborador$/iu.test(label)) role = 'employee';
    if (!role && /^outra pessoa/iu.test(label)) role = 'other';

    if (role === 'leader' || role === 'employee') {
        const name = plainName(role === 'leader' ? people.leader : people.employee);
        return { who: name || (role === 'leader' ? 'Líder' : 'Colaborador'), role, roleNoun: name ? ROLE_NOUN[role] : '' };
    }
    if (role === 'other') return { who: /^outra pessoa/iu.test(label) ? label : 'Outra pessoa', role, roleNoun: '' };

    return { who: speakerDisplay(label), role: null, roleNoun: '' };
}

/**
 * Transcrição (original ou limpa) em falas: { time: 'mm:ss' | '', who, role, roleNoun, text }.
 * Aceita os formatos do Plaud lidos no servidor (rótulo antes ou depois do tempo, fala na mesma linha ou na
 * seguinte, SRT convertido em «[hh:mm:ss] texto») e o texto limpo («[hh:mm:ss] Líder: …», parágrafos).
 * Rótulos com nome próprio só contam com duas falas ou mais (evita «Obs:» e afins), como no servidor.
 *
 * @param {string} text
 * @param {{ speakers?: Array<{label: string, role?: string|null}>, people?: {leader?: string, employee?: string} }} [context]
 */
export function parseTranscript(text, { speakers = [], people = {} } = {}) {
    const lines = String(text ?? '').split(/\r\n|\r|\n/);
    const parsed = lines.map((line) => parseLabelLine(line));

    const counts = {};
    parsed.forEach((p) => {
        if (p) counts[p.label.toLowerCase()] = (counts[p.label.toLowerCase()] ?? 0) + 1;
    });
    const isLabel = (p) => p && (IS_GENERIC.test(p.label) || IS_ROLE.test(p.label) || counts[p.label.toLowerCase()] >= 2);

    const roleByKey = {};
    speakers.forEach((s) => {
        if (s.role) roleByKey[String(s.label).toLowerCase()] = s.role;
    });

    const rows = [];
    let current = null;
    const close = () => {
        if (current && (current.text || current.who)) rows.push(current);
        current = null;
    };
    const start = (time, label, speech) => {
        close();
        current = { time: time ? clock(time) : '', who: '', role: null, roleNoun: '', text: speech, ...(label ? resolveSpeaker(label, roleByKey, people) : {}) };
        if (!label) current.who = '';
    };

    lines.forEach((line, i) => {
        const p = parsed[i];
        if (isLabel(p)) {
            start(p.time, p.label, p.speech);
            return;
        }
        if (TIME_ONLY.test(line)) {
            start(line.trim(), '', '');
            return;
        }
        const timed = line.match(TIME_TEXT);
        if (timed) {
            // SRT convertido: «[hh:mm:ss] Speaker 1: texto» já caiu acima; aqui o texto não tem rótulo.
            const inner = parseLabelLine(timed[2]);
            if (isLabel(inner)) start(timed[1], inner.label, inner.speech);
            else start(timed[1], '', timed[2].trim());
            return;
        }

        const trimmed = line.trim();
        if (trimmed === '') {
            // Linha em branco fecha a fala, salvo logo depois do rótulo (a fala ainda vem).
            if (current && current.text) close();
            return;
        }
        if (!current) start('', '', '');
        current.text = current.text ? `${current.text} ${trimmed}` : trimmed;
    });
    close();

    return rows;
}

const fold = (s) => s.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();

/** Falas que contêm o termo (sem diferenciar acentos e maiúsculas), no texto ou em quem fala. */
export function filterRows(rows, query) {
    const q = fold(String(query ?? '').trim());
    if (!q) return rows;

    return rows.filter((r) => fold(r.text).includes(q) || fold(r.who).includes(q));
}

/**
 * Partes do texto para marcar o termo buscado sem HTML: [{ text, match }]. Sem diferenciar acentos e maiúsculas.
 */
export function highlightParts(text, query) {
    const source = String(text ?? '');
    const q = fold(String(query ?? '').trim());
    if (!q) return [{ text: source, match: false }];

    // Mapa caractere a caractere (o «dobrado» de cada letra acentuada tem 1 caractere).
    const chars = [...source];
    const folded = chars.map((c) => fold(c));
    // Caso raro (ligaduras e afins): sem marcação, a fala continua no resultado da busca.
    if (folded.some((f) => f.length !== 1)) return [{ text: source, match: false }];
    const flat = folded.join('');
    const parts = [];
    let from = 0;
    let idx = flat.indexOf(q, from);
    while (idx >= 0) {
        if (idx > from) parts.push({ text: chars.slice(from, idx).join(''), match: false });
        parts.push({ text: chars.slice(idx, idx + q.length).join(''), match: true });
        from = idx + q.length;
        idx = flat.indexOf(q, from);
    }
    if (from < chars.length) parts.push({ text: chars.slice(from).join(''), match: false });

    return parts.length ? parts : [{ text: source, match: false }];
}
