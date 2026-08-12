import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    createBackdropCloseTracker,
    isPointerDownOnBackdrop,
    shouldCloseOnBackdropClick,
} from '../../resources/js/utils/backdropCloseGesture.js';

describe('backdropCloseGesture', () => {
    it('não fecha se o gesto começou no painel e o click terminou no backdrop (seleção arrastada para fora)', () => {
        assert.equal(shouldCloseOnBackdropClick(false, true), false);

        const tracker = createBackdropCloseTracker();
        tracker.pointerDownOnPanel();
        assert.equal(tracker.click(true), false);
    });

    it('fecha se mousedown e click ocorrerem no backdrop (clique deliberado no fundo)', () => {
        assert.equal(shouldCloseOnBackdropClick(true, true), true);

        const tracker = createBackdropCloseTracker();
        tracker.pointerDown(true);
        assert.equal(tracker.click(true), true);
    });

    it('não fecha se o click não terminou no backdrop', () => {
        assert.equal(shouldCloseOnBackdropClick(true, false), false);

        const tracker = createBackdropCloseTracker();
        tracker.pointerDown(true);
        assert.equal(tracker.click(false), false);
    });

    it('isPointerDownOnBackdrop só é true quando target === currentTarget', () => {
        const backdrop = { id: 'backdrop' };
        const panel = { id: 'panel' };

        assert.equal(
            isPointerDownOnBackdrop({ target: backdrop, currentTarget: backdrop }),
            true,
        );
        assert.equal(
            isPointerDownOnBackdrop({ target: panel, currentTarget: backdrop }),
            false,
        );
    });

    it('após um click, a flag de início no backdrop é limpa', () => {
        const tracker = createBackdropCloseTracker();
        tracker.pointerDown(true);
        assert.equal(tracker.startedOnBackdrop, true);
        tracker.click(true);
        assert.equal(tracker.startedOnBackdrop, false);
    });
});
