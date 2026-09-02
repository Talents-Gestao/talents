import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { proposalContractSignature } from '../../resources/js/utils/proposalContractSignature.js';

describe('proposalContractSignature', () => {
    it('marca como assinado quando há contrato assinado', () => {
        const badge = proposalContractSignature({ contract_signed: true, zapsign_pending: true });

        assert.equal(badge.key, 'signed');
        assert.equal(badge.label, 'Assinado');
    });

    it('marca como aguardando quando o ZapSign foi enviado e ainda não assinou', () => {
        const badge = proposalContractSignature({ contract_signed: false, zapsign_pending: true });

        assert.equal(badge.key, 'pending');
        assert.equal(badge.label, 'Aguardando assinatura');
    });

    it('marca como não assinado sem contrato enviado nem assinado', () => {
        const badge = proposalContractSignature({ contract_signed: false, zapsign_pending: false });

        assert.equal(badge.key, 'unsigned');
        assert.equal(badge.label, 'Não assinado');
    });
});
