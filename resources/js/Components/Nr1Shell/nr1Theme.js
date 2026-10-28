import {
    AdjustmentsHorizontalIcon,
    BoltIcon,
    CheckCircleIcon,
    ExclamationCircleIcon,
    ExclamationTriangleIcon,
    EyeIcon,
    FireIcon,
    FlagIcon,
    HeartIcon,
    ShieldCheckIcon,
} from '@heroicons/vue/24/outline';

/**
 * Paletas do shell NR-1 (valores espelhados em nr1-shell-pages.css).
 * (a) Níveis: verde / âmbar / vermelho suave, sempre com texto e ícone. Só «Elevado» usa vermelho.
 * (b) Dimensões e grupos: família roxo, azul, rosa e turquesa da marca, sem conotação de risco.
 */
export const LEVELS = {
    green: { key: 'ok', icon: CheckCircleIcon, color: '#2f8f6f' },
    yellow: { key: 'warn', icon: ExclamationTriangleIcon, color: '#b97a14' },
    red: { key: 'red', icon: ExclamationCircleIcon, color: '#c2475f' },
};

/** Rótulos de faixa adotados (Caderno), por nível. */
export const BAND_BY_RISK = { green: 'Menor sinalização', yellow: 'Atenção', red: 'Maior sinalização' };

export const levelOf = (risk) => LEVELS[risk] ?? LEVELS.yellow;

/** Cor e ícone próprios de cada uma das 7 dimensões (consistentes em todas as telas). */
export const DIMENSIONS = {
    'Demandas e Sobrecarga': { color: '#7c4dbd', tint: '#f0e8fb', icon: BoltIcon },
    'Autonomia e Organização': { color: '#3662b5', tint: '#e6edfa', icon: AdjustmentsHorizontalIcon },
    'Liderança e Gestão': { color: '#9661b3', tint: '#f3eaf8', icon: FlagIcon },
    'Segurança Psicológica': { color: '#168a80', tint: '#def4f1', icon: ShieldCheckIcon },
    'Relacionamentos e Clima': { color: '#c0558f', tint: '#fbe8f2', icon: HeartIcon },
    'Esgotamento e Saúde Emocional': { color: '#5a6bd0', tint: '#e8eafa', icon: FireIcon },
    'Percepção Organizacional': { color: '#1f93ad', tint: '#dff2f7', icon: EyeIcon },
};

const FALLBACK = { color: '#623277', tint: '#f5ecfa', icon: EyeIcon };

export const dimensionMeta = (title) => DIMENSIONS[title] ?? FALLBACK;

/** Cores dos grupos (sem conotação de risco). */
export const GROUP_COLORS = ['#7c4dbd', '#3662b5', '#c0558f', '#168a80', '#5a6bd0', '#1f93ad'];

/** Escala Nunca → Sempre das perguntas (cores do protótipo). */
export const ANSWERS = [
    { label: 'Nunca', color: '#543e96' },
    { label: 'Raramente', color: '#9873c9' },
    { label: 'Às vezes', color: '#ecc27e' },
    { label: 'Frequentemente', color: '#59b6a7' },
    { label: 'Sempre', color: '#287966' },
];

export const reducedMotion = () =>
    typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;
