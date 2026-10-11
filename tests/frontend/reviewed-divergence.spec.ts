import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import SourceComparison from '../../resources/js/components/SourceComparison.vue';
import type { ResultProjection } from '../../resources/js/core/contracts';

describe('SourceComparison - Divergência revisada', () => {
  it('reviewed divergence permanece neutro e nao adota aparencia de matched', () => {
    const divergenceReviewed: ResultProjection = {
      id: 'res-2',
      orderId: 'ORD-100',
      classification: 'amountMismatch',
      difference: '-10.00',
      reviewStatus: 'reviewed',
      version: 2,
      order: {
        line: 4,
        orderId: 'ORD-100',
        payoutId: null,
        businessDate: '2026-10-02',
        gross: '100.00',
        fee: null,
        net: null,
        sourceId: 'src-orders-1',
        normalizedHash: 'ordhash1',
        displayFilename: 'pedidos.csv',
      },
      payout: {
        line: 8,
        orderId: 'ORD-100',
        payoutId: 'PAY-88',
        businessDate: '2026-10-02',
        gross: '90.00',
        fee: '5.00',
        net: '85.00',
        sourceId: 'src-payouts-1',
        normalizedHash: 'payhash1',
        displayFilename: 'repasses.csv',
      },
      reviewEvents: [
        {
          id: 'rev-1',
          action: 'reviewed',
          note: 'Divergência confirmada com o intermediador.',
          actorName: 'Analista',
          createdAt: '2026-10-10T14:00:00Z',
        },
      ],
    };

    const wrapper = mount(SourceComparison, {
      props: {
        result: divergenceReviewed,
      },
    });

    // Check review badge
    const reviewBadge = wrapper.find('[data-test="review-status-badge"]');
    expect(reviewBadge.exists()).toBe(true);
    expect(reviewBadge.text()).toContain('Revisada');

    // It MUST NOT have matched styling or class
    expect(reviewBadge.classes()).not.toContain('badge-matched');
    expect(reviewBadge.classes()).not.toContain('is-matched');

    // Classification badge must remain amountMismatch / divergence, NOT matched
    const classBadge = wrapper.find('[data-test="classification-badge"]');
    expect(classBadge.text()).toContain('Divergência de valor');
    expect(classBadge.classes()).not.toContain('badge-matched');
  });
});
