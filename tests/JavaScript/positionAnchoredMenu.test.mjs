import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { positionAnchoredMenu } from '../../resources/js/utils/positionAnchoredMenu.js';

describe('positionAnchoredMenu', () => {
    it('abre abaixo do âncora quando há espaço no viewport', () => {
        const pos = positionAnchoredMenu({
            anchorRect: { top: 120, bottom: 148, right: 400 },
            menuWidth: 176,
            menuHeight: 180,
            viewportWidth: 1280,
            viewportHeight: 800,
        });

        assert.deepEqual(pos, { top: 154, left: 224 });
    });

    it('não sobe só porque uma altura estimada inflada não caberia abaixo', () => {
        const anchorRect = { top: 498, bottom: 526, right: 400 };
        const viewport = { viewportWidth: 1280, viewportHeight: 720 };

        const inflated = positionAnchoredMenu({
            anchorRect,
            menuWidth: 176,
            menuHeight: 260,
            ...viewport,
        });
        const measured = positionAnchoredMenu({
            anchorRect,
            menuWidth: 176,
            menuHeight: 180,
            ...viewport,
        });

        assert.equal(inflated.top, 232, 'estimativa 260px virava para cima');
        assert.equal(measured.top, 532, 'altura real cabe abaixo do botão');
    });

    it('sobe quando realmente não cabe abaixo e limita ao padding do viewport', () => {
        const pos = positionAnchoredMenu({
            anchorRect: { top: 640, bottom: 668, right: 400 },
            menuWidth: 176,
            menuHeight: 180,
            viewportWidth: 1280,
            viewportHeight: 720,
        });

        assert.equal(pos.top, 454);
        assert.equal(pos.top + 180 + 6 <= 640, true);
    });
});
