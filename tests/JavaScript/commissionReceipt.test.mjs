import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';
import {
    advancePartCents,
    advanceWarning,
    amountInputToCents,
    commissionSummaryRows,
    formatDueDate,
    paymentBlockedReason,
    paymentMaxCents,
    receivedPercent,
    rowNotes,
} from '../../resources/js/Components/Finance/commissionReceipt.js';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const read = (path) => readFileSync(join(root, path), 'utf8');
// toLocaleString usa espaço não separável entre «R$» e o número.
const plain = (text) => (text == null ? text : text.replace(/ /g, ' '));
const notesText = (c) => rowNotes(c).map((n) => [n.key, plain(n.text)]);

/** VENDA-2026-0017 como vem do servidor: 5%, teto R$ 191,45, 1ª parcela recebida, R$ 95,72 pagos. */
const sale0017 = (overrides = {}) => ({
    id: 1,
    percent: 5,
    amount_cents: 19_145,
    paid_cents: 9_572,
    remaining_cents: 9_573,
    payment_limit_cents: 9_573,
    releasable_cents: 0,
    earned_on_received_cents: 9_572,
    advance_cents: 0,
    chargeable_advance_cents: 0,
    awaiting_receipt_cents: 9_573,
    refund_pending_cents: 0,
    refunds: [],
    sale: {
        code: 'VENDA-2026-0017',
        total_cents: 382_900,
        received_cents: 191_450,
        outstanding_cents: 191_450,
        pending_installments: [{ id: 2, number: 2, due_date: '2026-12-10', amount_cents: 191_450, is_overdue: false }],
    },
    ...overrides,
});

describe('VENDA-2026-0017', () => {
    it('sem a permissão de adiantar: botão bloqueado com o motivo e texto de espera', () => {
        assert.equal(
            plain(paymentBlockedReason(sale0017(), false)),
            'Liberável = 5% do que já foi recebido (R$ 1.914,50) = R$ 95,72, valor já pago. '
                + 'O restante (R$ 95,73) é liberado conforme o cliente pagar. '
                + 'Pagar antes do recebimento exige a permissão «Pagar antes do recebimento».',
        );
        assert.deepEqual(notesText(sale0017()), [['awaiting', 'Aguardando recebimento de R$ 1.914,50']]);
    });

    it('com a permissão: botão ativo, limite no saldo e aviso de adiantamento', () => {
        assert.equal(paymentBlockedReason(sale0017(), true), null);
        assert.equal(paymentMaxCents(sale0017(), true), 9_573);
        assert.equal(paymentMaxCents(sale0017(), false), 0);
        assert.equal(advancePartCents(sale0017(), 9_573), 9_573);
        assert.equal(
            plain(advanceWarning(9_573)),
            'Você está pagando R$ 95,73 acima do que o cliente já pagou. '
                + 'Se o cliente não pagar, esse valor deverá ser devolvido pela colaboradora.',
        );
    });

    it('depois do adiantamento: «Adiantado» sempre visível na linha', () => {
        const paid = sale0017({ paid_cents: 19_145, remaining_cents: 0, payment_limit_cents: 0, advance_cents: 9_573, awaiting_receipt_cents: 0 });
        assert.deepEqual(notesText(paid), [['advance', 'Adiantado: R$ 95,73, aguardando o cliente pagar']]);
        assert.equal(paymentBlockedReason(paid, true), 'Comissão já paga integralmente.');
    });

    it('resumo da linha expandida na ordem combinada', () => {
        const rows = commissionSummaryRows(sale0017({ advance_cents: 0, refund_pending_cents: 0 })).map((r) => [r.label, plain(r.value)]);
        assert.deepEqual(rows, [
            ['Total da venda', 'R$ 3.829,00'],
            ['Recebido', 'R$ 1.914,50'],
            ['% recebido', '50%'],
            ['Teto da comissão (5%)', 'R$ 191,45'],
            ['Pago', 'R$ 95,72'],
            ['Liberável', 'R$ 0,00'],
            ['Adiantado', 'R$ 0,00'],
            ['Devolução pendente', 'R$ 0,00'],
        ]);
    });
});

describe('outros estados', () => {
    it('devolução pendente aparece na linha', () => {
        const c = sale0017({ refund_pending_cents: 1_000, remaining_cents: 0, releasable_cents: 0 });
        assert.deepEqual(notesText(c), [['refund', 'Devolução pendente: R$ 10,00']]);
    });

    it('liberável > 0: nada bloqueado nem aguardando', () => {
        const c = sale0017({ paid_cents: 0, remaining_cents: 19_145, payment_limit_cents: 19_145, releasable_cents: 9_572 });
        assert.equal(paymentBlockedReason(c, false), null);
        assert.deepEqual(rowNotes(c), []);
        assert.equal(advancePartCents(c, 9_572), 0);
        assert.equal(advancePartCents(c, 9_600), 28);
    });

    it('valor digitado em reais vira centavos', () => {
        assert.equal(amountInputToCents('95,73'), 9_573);
        assert.equal(amountInputToCents('95.73'), 9_573);
        assert.equal(amountInputToCents(191.45), 19_145);
        assert.ok(Number.isNaN(amountInputToCents('')));
        assert.ok(Number.isNaN(amountInputToCents('abc')));
    });

    it('percentual recebido com uma casa e data sem fuso', () => {
        assert.equal(receivedPercent({ sale: { total_cents: 10_000, received_cents: 3_334 } }), 33.3);
        assert.equal(receivedPercent({ sale: { total_cents: 0, received_cents: 0 } }), 0);
        assert.equal(formatDueDate('2026-12-10'), '10/12/2026');
        assert.equal(formatDueDate(null), '—');
    });
});

describe('componentes', () => {
    const button = read('resources/js/Components/Finance/CommissionPayButton.vue');
    const modal = read('resources/js/Components/Finance/CommissionPaymentModal.vue');
    const refundModal = read('resources/js/Components/Finance/CommissionRefundModal.vue');
    const refundsPanel = read('resources/js/Components/Finance/CommissionRefundsPanel.vue');
    const page = read('resources/js/Pages/Admin/Finance/Commissions/Index.vue');
    const sale = read('resources/js/Pages/Admin/Finance/Sales/Show.vue');

    it('botão bloqueado é acessível (aria-disabled + aria-describedby + tooltip por foco/toque/Esc)', () => {
        assert.match(button, /:aria-disabled="blocked \? 'true' : undefined"/);
        assert.match(button, /:aria-describedby="blocked \? tooltipId : undefined"/);
        assert.match(button, /role="tooltip"/);
        assert.doesNotMatch(button, /:disabled=/);
        for (const handler of ['@mouseenter="show"', '@focus="show"', '@blur="hide"', '@click="onClick"']) {
            assert.ok(button.includes(handler), handler);
        }
        assert.match(button, /event\.key === 'Escape'/);
    });

    it('modal de pagamento: limite pela permissão e confirmação obrigatória do adiantamento', () => {
        assert.match(modal, /canAdvance: \{ type: Boolean, default: false \}/);
        assert.match(modal, /paymentMaxCents\(props\.commission, props\.canAdvance\)/);
        assert.match(modal, /confirm_advance: false/);
        assert.match(modal, /v-if="needsConfirmation"/);
        assert.match(modal, /advanceWarning\(advanceCents\)/);
        assert.match(modal, /\(!needsConfirmation\.value \|\| form\.confirm_advance\)/);
        assert.match(modal, /id="commission-payment-notes"/);
    });

    it('devoluções: cobrar, registrar (conta e data) e assumir perda (justificativa)', () => {
        assert.match(refundModal, /admin\.financeiro\.comissoes\.devolucoes\.store/);
        assert.match(refundModal, /admin\.financeiro\.comissoes\.devolucoes\.registros\.store/);
        assert.match(refundModal, /admin\.financeiro\.comissoes\.devolucoes\.perda\.store/);
        assert.match(refundModal, /id="commission-refund-account"/);
        assert.match(refundModal, /id="commission-refund-justification"/);
        assert.match(refundsPanel, /can\.charge_refund && commission\.chargeable_advance_cents > 0/);
        assert.match(refundsPanel, /v-if="can\.register_refund"/);
        assert.match(refundsPanel, /v-if="can\.assume_loss"/);
        assert.ok(refundsPanel.includes('Cobrar devolução'));
        assert.ok(refundsPanel.includes('Assumir como perda'));
    });

    it('telas usam os mesmos valores e permissões', () => {
        assert.match(page, /paymentBlockedReason\(c, can\.pay_advance\)/);
        assert.match(page, /rowNotes\(c\)/);
        assert.match(page, /:can-advance="can\.pay_advance"/);
        assert.ok(page.includes('Adiantado em aberto'));
        assert.ok(page.includes('>Saldo da comissão<'));
        assert.match(page, /status: 'devolucoes'/);
        assert.match(sale, /paymentBlockedReason\(item, commission_can\.pay_advance\)/);
        assert.ok(!sale.includes('Nada liberável agora'));
        assert.ok(!page.includes('Nada liberável agora'));
    });
});
