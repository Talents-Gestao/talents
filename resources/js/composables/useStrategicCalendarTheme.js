import {
    calendarAccentPresets,
    calendarAccentValidationMessage,
    deriveBackgroundFromAccent,
    isValidCalendarAccent,
    monthDefaultPalettes,
    normalizeHexColor,
    resolveMonthVisual,
} from '@/utils/strategicCalendarThemes';
import { computed, ref } from 'vue';

const STORAGE_KEY = 'talents:strategic-calendar-theme';

const DEFAULT_PREFERENCES = {
    useCampaignColors: true,
    accent: monthDefaultPalettes[1].color,
};

const preferences = ref({ ...DEFAULT_PREFERENCES });
const accentValidationError = ref(null);
let loaded = false;

function sanitizeAccent(accent) {
    const normalized = normalizeHexColor(accent);
    if (normalized && isValidCalendarAccent(normalized)) {
        return normalized;
    }

    return DEFAULT_PREFERENCES.accent;
}

function normalizePreferences(raw) {
    if (!raw || typeof raw !== 'object') {
        return { ...DEFAULT_PREFERENCES };
    }

    return {
        useCampaignColors: raw.useCampaignColors !== false,
        accent: sanitizeAccent(raw.accent),
    };
}

function persistPreferences() {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(preferences.value));
    } catch (_error) {
        // ignore quota / modo privado
    }
}

function loadPreferencesFromStorage() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) {
            preferences.value = { ...DEFAULT_PREFERENCES };
            return;
        }

        preferences.value = normalizePreferences(JSON.parse(raw));
    } catch (_error) {
        preferences.value = { ...DEFAULT_PREFERENCES };
    }
}

function ensureLoaded() {
    if (!loaded) {
        loadPreferencesFromStorage();
        loaded = true;
    }
}

/**
 * Preferências visuais do calendário estratégico (persistidas em localStorage).
 */
export function useStrategicCalendarTheme() {
    ensureLoaded();

    const isCustomPalette = computed(() => preferences.value.useCampaignColors === false);

    function resolveForMonth(month) {
        return resolveMonthVisual(month, preferences.value);
    }

    function setUseCampaignColors(value) {
        accentValidationError.value = null;
        preferences.value = {
            ...preferences.value,
            useCampaignColors: Boolean(value),
        };
        persistPreferences();
    }

    /**
     * @returns {boolean} true se a cor foi aceita
     */
    function setAccent(accent) {
        const normalized = normalizeHexColor(accent);
        if (!normalized || !isValidCalendarAccent(normalized)) {
            accentValidationError.value = calendarAccentValidationMessage();
            return false;
        }

        accentValidationError.value = null;
        preferences.value = {
            useCampaignColors: false,
            accent: normalized,
        };
        persistPreferences();
        return true;
    }

    function restoreCampaignPalette() {
        accentValidationError.value = null;
        preferences.value = { ...DEFAULT_PREFERENCES };
        persistPreferences();
    }

    function previewBackground(accent) {
        return deriveBackgroundFromAccent(accent);
    }

    return {
        preferences,
        accentValidationError,
        isCustomPalette,
        accentPresets: calendarAccentPresets,
        resolveForMonth,
        setUseCampaignColors,
        setAccent,
        restoreCampaignPalette,
        previewBackground,
    };
}
